<?php

namespace Tests\Feature;

use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use App\Models\BestForSubject;
use App\Models\Category;
use App\Models\PublishedReviewPayload;
use App\Models\ReviewPublicationDisposition;
use App\Services\BestForPublisher;
use App\Services\ReviewDispositionService;
use App\Support\BestFor\PublicationConfirmations;
use App\Support\BestFor\SelectionSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every assertion here reads the STORED columns, because those are the authoritative
 * effective state. A test that asked a resolver would only prove the resolver agreed with
 * itself; it would say nothing about the sitemap query that reads the column directly.
 *
 * The failures these tests exist to prevent:
 *   - a product going temporarily out of stock reading as a material withdrawal;
 *   - an ordinary editorial retirement suppressing a healthy collection;
 *   - a PROVISIONAL material suspension outliving the decision that resolved it;
 *   - one resolved selection releasing a collection while another still blocks it;
 *   - a recomputation quietly clearing an operator's own hold;
 *   - a severe unpublication undoing itself;
 *   - a removed edition storing `indexable`.
 */
class ReviewDispositionServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $reviewIds = ['PR-1', 'PR-2', 'PR-3', 'PR-4', 'PR-5'];

    private function service(): ReviewDispositionService
    {
        return new ReviewDispositionService();
    }

    private function publishedEdition(): BestForEdition
    {
        // Region is part of subject identity since BR-IMPL-03, and every payload below
        // needs explicit evidence for it — the gate refuses an empty region set.
        $region = \App\Models\Region::firstOrCreate(
            ['region_code' => 'AU'],
            ['region_name' => 'AU region', 'currency' => 'AUD', 'is_active' => true]
        );

        $subject = BestForSubject::create([
            'category_id' => Category::create(['name' => 'Automatic Dog Feeders'])->id,
            'year' => 2026,
            'region_id' => $region->id,
        ]);

        $edition = BestForEdition::create([
            'subject_id' => $subject->id,
            'category_name_as_published' => 'x',
            'year_as_published' => 1999,
            'methodology_text' => 'Five products, scored on the published BeastieScore method.',
            'video_asset_id' => 'vimeo:100001',
        ]);

        $superlatives = ['Best overall', 'Best value', 'Best for large dogs', 'Best budget', 'Best tech'];

        foreach ($this->reviewIds as $i => $reviewId) {
            $payload = new PublishedReviewPayload();
            $payload->forceFill([
                'published_review_id' => $reviewId,
                'review_slug' => 'slug-' . strtolower($reviewId),
                'publish_status' => 'published',
                'source' => 'pipeline',
                'product_uid' => 'ASIN_' . strtoupper(str_replace('-', '', $reviewId)),
                'final_beastie_score' => 8.5,
                'public_score' => 4.4,
                'public_rating_count' => 1200,
                'review_page_json' => [
                    'payload_schema_version' => SelectionSnapshotBuilder::REQUIRED_PAYLOAD_SCHEMA_VERSION,
                    'product' => ['product_name' => 'Product ' . $reviewId],
                    'review_facts' => ['public_signal' => 'Strong'],
                ],
            ])->save();

            $payload->regions()->sync([$region->id]);

            BestForEditionSelection::create([
                'edition_id' => $edition->id,
                'position' => $i + 1,
                'published_review_id' => $reviewId,
                'review_slug_as_published' => 'x',
                'product_name_as_published' => 'x',
                'superlative' => $superlatives[$i],
                'selection_reason' => 'Scored highest on the published method.',
                'beastie_score_as_published' => 0.0,
                'public_rating_as_published' => 0.0,
                'public_rating_count_as_published' => 0,
                'public_signal_as_published' => 'Limited',
            ]);
        }

        return (new BestForPublisher())->publish($edition, new PublicationConfirmations(
            notDivergenceHeld: $this->reviewIds,
            categoryConfirmed: $this->reviewIds,
            productDistinctnessConfirmed: true,
            videoConsistencyConfirmed: true,
        ));
    }

    private function assertStored(BestForEdition $edition, string $recommendation, string $indexing, string $because = ''): void
    {
        $fresh = $edition->fresh();
        $this->assertSame($recommendation, $fresh->recommendation_state, $because);
        $this->assertSame($indexing, $fresh->indexing_state, $because);
    }

    private function assertRestricted(BestForEdition $edition, string $because = ''): void
    {
        $this->assertStored($edition, BestForEdition::RECOMMENDATION_SUSPENDED, BestForEdition::INDEXING_NOINDEX, $because);
    }

    private function assertUnrestricted(BestForEdition $edition, string $because = ''): void
    {
        $this->assertStored($edition, BestForEdition::RECOMMENDATION_ACTIVE, BestForEdition::INDEXING_INDEXABLE, $because);
    }

    private function snapshotOf(BestForEdition $edition): array
    {
        return $edition->fresh()->selections()->get()->map->only([
            'published_review_id', 'position', 'product_name_as_published',
            'beastie_score_as_published', 'public_rating_count_as_published', 'public_signal_as_published',
        ])->toArray();
    }

    // ---- 12. Publication settles the stored state -------------------------

    public function test_publication_persists_operational_state_consistent_with_current_restrictions(): void
    {
        $edition = $this->publishedEdition();

        $this->assertUnrestricted($edition, 'a clean edition publishes active and indexable');
        $this->assertNull($edition->fresh()->manual_recommendation_suspended_at);
        $this->assertNull($edition->fresh()->manual_noindex_at);
        $this->assertSame(BestForEdition::PUBLICATION_LIVE, $edition->fresh()->publication_state);
    }

    // ---- Nothing that is not a withdrawal ---------------------------------

    public function test_temporary_unavailability_restricts_nothing(): void
    {
        $edition = $this->publishedEdition();

        $disposition = $this->service()->setAvailability(
            'PR-1',
            ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE
        );

        $this->assertTrue($disposition->isTemporarilyUnavailable());
        $this->assertFalse($disposition->isWithdrawn());
        $this->assertUnrestricted($edition);
    }

    public function test_material_invalidity_restricts(): void
    {
        $edition = $this->publishedEdition();

        $disposition = $this->service()->setValidity(
            'PR-1',
            ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID
        );

        $this->assertTrue($disposition->isMateriallyInvalid());
        $this->assertFalse($disposition->isWithdrawn());
        $this->assertRestricted($edition);
    }

    // ---- 1-3. Provisional material treatment and its release --------------

    /** REQUIRED 1. */
    public function test_an_unclassified_withdrawal_persists_suspended_and_noindex(): void
    {
        $edition = $this->publishedEdition();

        $disposition = $this->service()->recordWithdrawal('PR-2', null, 'Pending review.');

        $this->assertTrue($disposition->isPendingDisposition());
        $this->assertSame(ReviewPublicationDisposition::CLASS_MATERIAL, $disposition->effectiveWithdrawalClass());
        $this->assertRestricted($edition);
    }

    public function test_a_proposed_but_unconfirmed_ordinary_class_is_still_treated_as_material(): void
    {
        $edition = $this->publishedEdition();

        $disposition = $this->service()->recordWithdrawal(
            'PR-2',
            ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL
        );

        $this->assertFalse($disposition->classification_confirmed);
        $this->assertSame(ReviewPublicationDisposition::CLASS_MATERIAL, $disposition->effectiveWithdrawalClass());
        $this->assertRestricted($edition);
    }

    /** REQUIRED 2. */
    public function test_confirming_ordinary_editorial_releases_the_disposition_contribution(): void
    {
        $edition = $this->publishedEdition();

        $this->service()->recordWithdrawal('PR-3');
        $this->assertRestricted($edition, 'an unclassified withdrawal restricts');

        $this->service()->confirmClassification(
            'PR-3',
            ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL,
            'Routine retirement.'
        );

        $this->assertUnrestricted($edition, 'Option A: ordinary editorial must not suppress the collection');

        // The entry is still withdrawn — its historical treatment is derived from this row
        // at read time, never written onto the immutable selection.
        $this->assertTrue($this->service()->for('PR-3')->isWithdrawn());
    }

    /** REQUIRED 3. */
    public function test_confirming_temporary_technical_releases_the_disposition_contribution(): void
    {
        $edition = $this->publishedEdition();

        $this->service()->recordWithdrawal('PR-2');
        $this->assertRestricted($edition);

        $this->service()->confirmClassification('PR-2', ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL);

        $this->assertUnrestricted($edition, 'a confirmed temporary-technical withdrawal must not keep the edition suspended');
    }

    public function test_a_confirmed_material_withdrawal_restricts_without_removing_publication(): void
    {
        $edition = $this->publishedEdition();

        $this->service()->confirmClassification('PR-4', ReviewPublicationDisposition::CLASS_MATERIAL, 'Contested claim.');

        $this->assertRestricted($edition);
        $this->assertSame(BestForEdition::PUBLICATION_LIVE, $edition->fresh()->publication_state);
    }

    public function test_reinstating_a_review_releases_the_disposition_contribution(): void
    {
        $edition = $this->publishedEdition();
        $this->service()->recordWithdrawal('PR-2');
        $this->assertRestricted($edition);

        $disposition = $this->service()->reinstate('PR-2', 'Resolved.');

        $this->assertFalse($disposition->isWithdrawn());
        $this->assertUnrestricted($edition);
    }

    // ---- 4. Overlapping blockers -----------------------------------------

    /** REQUIRED 4. */
    public function test_another_blocking_selection_keeps_the_edition_restricted(): void
    {
        $edition = $this->publishedEdition();

        $this->service()->recordWithdrawal('PR-2');
        $this->service()->recordWithdrawal('PR-4');
        $this->assertRestricted($edition);

        $this->service()->confirmClassification('PR-2', ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL);

        $restriction = $this->service()->dispositionRestrictionFor($edition->fresh());
        $this->assertTrue($restriction['material_required']);
        $this->assertSame(['PR-4'], $restriction['blocking_review_ids']);
        $this->assertRestricted($edition, 'PR-4 is still unresolved');

        $this->service()->confirmClassification('PR-4', ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL);
        $this->assertUnrestricted($edition, 'the last blocker is resolved');
    }

    public function test_a_materially_invalid_selection_keeps_blocking_after_a_withdrawal_is_resolved(): void
    {
        $edition = $this->publishedEdition();

        $this->service()->recordWithdrawal('PR-2');
        $this->service()->setValidity('PR-5', ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID);
        $this->service()->confirmClassification('PR-2', ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL);

        $this->assertSame(['PR-5'], $this->service()->dispositionRestrictionFor($edition->fresh())['blocking_review_ids']);
        $this->assertRestricted($edition);
    }

    // ---- 5-8. Manual provenance ------------------------------------------

    /** REQUIRED 7a: a manual recommendation hold does not force noindex. */
    public function test_a_manual_recommendation_suspension_does_not_force_noindex(): void
    {
        $edition = $this->publishedEdition();

        $this->service()->applyManualRecommendationSuspension($edition, 'Commercial review pending.');

        $this->assertStored($edition, BestForEdition::RECOMMENDATION_SUSPENDED, BestForEdition::INDEXING_INDEXABLE);
        $this->assertNotNull($edition->fresh()->manual_recommendation_suspended_at);
        $this->assertNull($edition->fresh()->manual_noindex_at);
    }

    /** REQUIRED 7b: a manual noindex does not force recommendation suspension. */
    public function test_a_manual_noindex_does_not_force_recommendation_suspension(): void
    {
        $edition = $this->publishedEdition();

        $this->service()->applyManualNoindex($edition, 'Duplicate content investigation.');

        $this->assertStored($edition, BestForEdition::RECOMMENDATION_ACTIVE, BestForEdition::INDEXING_NOINDEX);
        $this->assertNull($edition->fresh()->manual_recommendation_suspended_at);
        $this->assertNotNull($edition->fresh()->manual_noindex_at);
    }

    /** REQUIRED 5: a manual recommendation hold survives a full disposition cycle. */
    public function test_a_manual_recommendation_suspension_survives_a_disposition_restrict_and_release_cycle(): void
    {
        $edition = $this->publishedEdition();
        $this->service()->applyManualRecommendationSuspension($edition, 'Operator hold.');

        $this->service()->recordWithdrawal('PR-2');
        $this->assertRestricted($edition, 'both planes restrict');

        $this->service()->confirmClassification('PR-2', ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL);

        // Disposition released; the operator's own hold must survive untouched.
        $this->assertNotNull($edition->fresh()->manual_recommendation_suspended_at);
        $this->assertStored(
            $edition,
            BestForEdition::RECOMMENDATION_SUSPENDED,
            BestForEdition::INDEXING_INDEXABLE,
            'the manual recommendation hold outlives the disposition'
        );
    }

    /** REQUIRED 6: a manual noindex survives a full disposition cycle. */
    public function test_a_manual_noindex_survives_a_disposition_restrict_and_release_cycle(): void
    {
        $edition = $this->publishedEdition();
        $this->service()->applyManualNoindex($edition, 'SEO hold.');

        $this->service()->recordWithdrawal('PR-2');
        $this->assertRestricted($edition);

        $this->service()->confirmClassification('PR-2', ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL);

        $this->assertNotNull($edition->fresh()->manual_noindex_at);
        $this->assertStored(
            $edition,
            BestForEdition::RECOMMENDATION_ACTIVE,
            BestForEdition::INDEXING_NOINDEX,
            'the manual noindex outlives the disposition'
        );
    }

    /** REQUIRED 8: releasing a manual marker does not release an overlapping disposition. */
    public function test_releasing_a_manual_noindex_leaves_an_overlapping_disposition_restriction_in_place(): void
    {
        $edition = $this->publishedEdition();
        $this->service()->applyManualNoindex($edition, 'SEO hold.');
        $this->service()->recordWithdrawal('PR-2');
        $this->assertRestricted($edition);

        $this->service()->releaseManualNoindex($edition, 'SEO investigation closed.');

        $this->assertNull($edition->fresh()->manual_noindex_at);
        $this->assertRestricted($edition, 'PR-2 still requires material treatment');
    }

    public function test_releasing_a_manual_recommendation_hold_leaves_an_overlapping_disposition_in_place(): void
    {
        $edition = $this->publishedEdition();
        $this->service()->applyManualRecommendationSuspension($edition, 'Operator hold.');
        $this->service()->recordWithdrawal('PR-2');

        $this->service()->releaseManualRecommendationSuspension($edition, 'Commercial review closed.');

        $this->assertNull($edition->fresh()->manual_recommendation_suspended_at);
        $this->assertRestricted($edition, 'PR-2 still requires material treatment');
    }

    public function test_releasing_a_manual_hold_with_no_disposition_restriction_returns_the_edition_to_normal(): void
    {
        $edition = $this->publishedEdition();
        $this->service()->applyManualNoindex($edition, 'SEO hold.');
        $this->assertStored($edition, BestForEdition::RECOMMENDATION_ACTIVE, BestForEdition::INDEXING_NOINDEX);

        $this->service()->releaseManualNoindex($edition, 'Closed.');

        $this->assertUnrestricted($edition);
    }

    // ---- 9. Idempotency ---------------------------------------------------

    /** REQUIRED 9. */
    public function test_recomputation_is_idempotent(): void
    {
        $edition = $this->publishedEdition();
        $this->service()->recordWithdrawal('PR-2');
        $this->assertRestricted($edition);

        $firstTouch = $edition->fresh()->updated_at;

        $this->travel(5)->seconds();
        $this->service()->recomputeOperationalState($edition->fresh());

        $this->assertRestricted($edition);
        $this->assertEquals(
            $firstTouch->toDateTimeString(),
            $edition->fresh()->updated_at->toDateTimeString(),
            'an unchanged recomputation must write nothing at all'
        );
    }

    // ---- 10. Severe removal ----------------------------------------------

    /** REQUIRED 10. */
    public function test_severe_removal_survives_every_ordinary_recomputation(): void
    {
        $edition = $this->publishedEdition();
        $subject = $edition->subject;

        $this->service()->confirmClassification(
            'PR-4',
            ReviewPublicationDisposition::CLASS_MATERIAL,
            'Recall.',
            null,
            true
        );

        $this->assertSame(BestForEdition::PUBLICATION_REMOVED, $edition->fresh()->publication_state);
        $this->assertNull($edition->fresh()->current_for_subject_id);
        $this->assertNull($subject->fresh()->currentEdition);
        $this->assertRestricted($edition, 'a removed edition must never store indexable');

        // Resolving the underlying review must not bring a removed edition back.
        $this->service()->confirmClassification('PR-4', ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL);
        $this->assertSame(BestForEdition::PUBLICATION_REMOVED, $edition->fresh()->publication_state);
        $this->assertRestricted($edition);

        $this->service()->reinstate('PR-4');
        $this->assertSame(BestForEdition::PUBLICATION_REMOVED, $edition->fresh()->publication_state);
        $this->assertRestricted($edition);
        $this->assertNull($edition->fresh()->current_for_subject_id);

        // And a recompute driven by an unrelated selection cannot lift it either.
        $this->service()->recomputeOperationalState($edition->fresh());
        $this->assertSame(BestForEdition::PUBLICATION_REMOVED, $edition->fresh()->publication_state);
        $this->assertRestricted($edition);
    }

    public function test_an_explicit_severe_restore_is_required_and_does_not_make_the_edition_current(): void
    {
        $edition = $this->publishedEdition();
        $subject = $edition->subject;

        $this->service()->confirmClassification('PR-4', ReviewPublicationDisposition::CLASS_MATERIAL, 'Recall.', null, true);
        $this->service()->reinstate('PR-4', 'Cleared.');

        $restored = (new BestForPublisher())->restoreFromSevereUnpublication($edition->fresh(), 'Cleared for display.');

        $this->assertSame(BestForEdition::PUBLICATION_LIVE, $restored->publication_state);
        $this->assertUnrestricted($edition, 'no restriction remains once the withdrawal is cleared');

        // No older edition automatically becomes current.
        $this->assertNull($subject->fresh()->currentEdition);
    }

    public function test_a_severe_restore_cannot_overrule_an_unresolved_withdrawal(): void
    {
        $edition = $this->publishedEdition();

        $this->service()->confirmClassification('PR-4', ReviewPublicationDisposition::CLASS_MATERIAL, 'Recall.', null, true);
        (new BestForPublisher())->restoreFromSevereUnpublication($edition->fresh(), 'Publication restored.');

        $this->assertSame(BestForEdition::PUBLICATION_LIVE, $edition->fresh()->publication_state);
        $this->assertRestricted($edition, 'the material withdrawal is still unresolved');
    }

    // ---- Orthogonality and snapshot integrity ----------------------------

    public function test_validity_and_availability_move_independently(): void
    {
        $this->publishedEdition();

        $this->service()->setAvailability('PR-5', ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE);
        $this->service()->setValidity('PR-5', ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID);

        $disposition = $this->service()->for('PR-5');
        $this->assertTrue($disposition->isTemporarilyUnavailable());
        $this->assertTrue($disposition->isMateriallyInvalid());
        $this->assertFalse($disposition->isWithdrawn());

        $this->service()->setAvailability('PR-5', ReviewPublicationDisposition::AVAILABILITY_AVAILABLE);
        $disposition = $this->service()->for('PR-5');
        $this->assertFalse($disposition->isTemporarilyUnavailable());
        $this->assertTrue($disposition->isMateriallyInvalid());
    }

    /** REQUIRED 11. */
    public function test_no_transition_ever_alters_the_editorial_snapshot(): void
    {
        $edition = $this->publishedEdition();
        $before = $this->snapshotOf($edition);

        $this->service()->setAvailability('PR-1', ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE);
        $this->service()->recordWithdrawal('PR-2');
        $this->service()->confirmClassification('PR-2', ReviewPublicationDisposition::CLASS_TEMPORARY_TECHNICAL);
        $this->service()->recordWithdrawal('PR-3');
        $this->service()->confirmClassification('PR-3', ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL);
        $this->service()->applyManualNoindex($edition->fresh(), 'SEO hold.');
        $this->service()->applyManualRecommendationSuspension($edition->fresh(), 'Operator hold.');
        $this->service()->releaseManualNoindex($edition->fresh(), 'Closed.');
        $this->service()->releaseManualRecommendationSuspension($edition->fresh(), 'Closed.');
        $this->service()->confirmClassification('PR-4', ReviewPublicationDisposition::CLASS_MATERIAL, 'Recall.', null, true);
        $this->service()->setValidity('PR-5', ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID);
        $this->service()->reinstate('PR-2');
        (new BestForPublisher())->restoreFromSevereUnpublication($edition->fresh(), 'Cleared.');

        $this->assertSame($before, $this->snapshotOf($edition));
    }

    public function test_a_review_with_no_disposition_row_restricts_nothing(): void
    {
        $edition = $this->publishedEdition();

        $this->assertSame(1, $this->service()->recomputeForReview('PR-1'));
        $this->assertUnrestricted($edition);
        $this->assertSame([], $this->service()->dispositionRestrictionFor($edition)['blocking_review_ids']);
    }
}
