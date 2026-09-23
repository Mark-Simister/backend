<?php

namespace Tests\Feature;

use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use App\Models\BestForSubject;
use App\Models\Category;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\ReviewPublicationDisposition;
use App\Services\ReviewDispositionService;
use App\Support\BestFor\BestForPublicationGate;
use App\Support\BestFor\PublicationConfirmations;
use App\Support\BestFor\SelectionSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The gate refuses by default and passes only on evidence.
 *
 * Most of these tests assert a refusal, which is the point: absent evidence is never
 * silently read as satisfaction, and a condition this backend cannot decide is escalated
 * to a human rather than assumed away.
 */
class BestForPublicationGateTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $reviewIds = ['PR-1', 'PR-2', 'PR-3', 'PR-4', 'PR-5'];

    /**
     * A test-only region fixture. Creating a GLOBAL row here proves only that the gate
     * reads one correctly — it is not evidence that a real GLOBAL row exists in any
     * persistent environment, which remains an outstanding reference-data prerequisite.
     */
    private function region(string $code = 'AU', bool $active = true): Region
    {
        $region = Region::firstOrCreate(
            ['region_code' => $code],
            ['region_name' => $code . ' region', 'currency' => 'AUD', 'is_active' => $active]
        );

        if ((bool) $region->is_active !== $active) {
            $region->is_active = $active;
            $region->save();
        }

        return $region;
    }

    /**
     * @param  string[]  $regionCodes  explicit regional evidence. The default is the AU
     *                                 row that matches the default subject, so the common
     *                                 path passes; pass [] to test the empty-set refusal.
     */
    private function payload(string $id, array $overrides = [], array $pageOverrides = [], array $regionCodes = ['AU']): PublishedReviewPayload
    {
        $page = array_replace_recursive([
            'payload_schema_version' => SelectionSnapshotBuilder::REQUIRED_PAYLOAD_SCHEMA_VERSION,
            'product' => ['product_name' => 'Product ' . $id, 'hero_image_url' => 'https://example.test/' . $id . '.jpg'],
            'review_facts' => ['public_signal' => 'Strong'],
        ], $pageOverrides);

        $payload = new PublishedReviewPayload();
        $payload->forceFill(array_merge([
            'published_review_id' => $id,
            'review_slug' => 'slug-' . strtolower($id),
            'publish_status' => 'published',
            'source' => 'pipeline',
            'product_uid' => 'ASIN_' . strtoupper(str_replace('-', '', $id)),
            'final_beastie_score' => 8.5,
            'public_score' => 4.4,
            'public_rating_count' => 1200,
            'review_page_json' => $page,
        ], $overrides))->save();

        $payload->regions()->sync(
            array_map(fn (string $code) => $this->region($code)->id, $regionCodes)
        );

        return $payload;
    }

    private function subject(string $categoryName = 'Automatic Dog Feeders', int $year = 2026, string $regionCode = 'AU'): BestForSubject
    {
        return BestForSubject::create([
            'category_id' => Category::create(['name' => $categoryName])->id,
            'year' => $year,
            'region_id' => $this->region($regionCode)->id,
        ]);
    }

    /**
     * @param  string[]  $regionCodes  regional evidence applied to every generated payload
     */
    private function draft(?BestForSubject $subject = null, int $selectionCount = 5, array $regionCodes = ['AU']): BestForEdition
    {
        $subject = $subject ?? $this->subject();

        $edition = BestForEdition::create([
            'subject_id' => $subject->id,
            'category_name_as_published' => 'placeholder',
            'year_as_published' => 1999,
            'methodology_text' => 'Five products, scored on the published BeastieScore method.',
            'video_asset_id' => 'vimeo:100001',
        ]);

        $superlatives = ['Best overall', 'Best value', 'Best for large dogs', 'Best budget', 'Best tech'];

        for ($i = 0; $i < $selectionCount; $i++) {
            $reviewId = $this->reviewIds[$i];
            $this->payload($reviewId, [], [], $regionCodes);
            $this->selection($edition, $i + 1, $reviewId, $superlatives[$i]);
        }

        return $edition;
    }

    private function selection(BestForEdition $edition, int $position, string $reviewId, string $superlative): BestForEditionSelection
    {
        return BestForEditionSelection::create([
            'edition_id' => $edition->id,
            'position' => $position,
            'published_review_id' => $reviewId,
            'review_slug_as_published' => 'draft-slug',
            'product_name_as_published' => 'draft product',
            'superlative' => $superlative,
            'selection_reason' => 'Scored highest on the published method.',
            'beastie_score_as_published' => 0.0,
            'public_rating_as_published' => 0.0,
            'public_rating_count_as_published' => 0,
            'public_signal_as_published' => 'Limited',
        ]);
    }

    private function fullConfirmations(): PublicationConfirmations
    {
        return new PublicationConfirmations(
            notDivergenceHeld: $this->reviewIds,
            categoryConfirmed: $this->reviewIds,
            productDistinctnessConfirmed: true,
            videoConsistencyConfirmed: true,
        );
    }

    private function errors(BestForEdition $edition, ?PublicationConfirmations $confirmations = null): string
    {
        return implode(' | ', (new BestForPublicationGate())->errorsFor(
            $edition,
            $confirmations ?? $this->fullConfirmations()
        ));
    }

    public function test_a_complete_edition_with_full_confirmations_passes(): void
    {
        $this->assertSame('', $this->errors($this->draft()));
    }

    public function test_it_refuses_without_the_divergence_confirmation(): void
    {
        $confirmations = new PublicationConfirmations(
            categoryConfirmed: $this->reviewIds,
            videoConsistencyConfirmed: true,
        );

        $errors = $this->errors($this->draft(), $confirmations);

        $this->assertStringContainsString('not confirmed free of a divergence hold', $errors);
    }

    public function test_it_refuses_without_the_video_consistency_confirmation(): void
    {
        $confirmations = new PublicationConfirmations(
            notDivergenceHeld: $this->reviewIds,
            categoryConfirmed: $this->reviewIds,
        );

        $this->assertStringContainsString(
            'Video-to-collection consistency has not been confirmed',
            $this->errors($this->draft(), $confirmations)
        );
    }

    public function test_an_empty_confirmation_object_confirms_nothing(): void
    {
        $errors = $this->errors($this->draft(), PublicationConfirmations::none());

        $this->assertStringContainsString('divergence', $errors);
        $this->assertStringContainsString('Video-to-collection consistency', $errors);
        $this->assertStringContainsString('category membership cannot be determined', $errors);
    }

    public function test_it_refuses_fewer_than_five_selections(): void
    {
        $this->assertStringContainsString(
            'publishes exactly 5 selections; this edition has 4',
            $this->errors($this->draft(null, 4))
        );
    }

    public function test_it_refuses_duplicate_superlatives(): void
    {
        $edition = $this->draft();
        $edition->selections()->first()->forceFill(['superlative' => 'Best value'])->save();

        $this->assertStringContainsString('share the same superlative', $this->errors($edition));
    }

    public function test_it_refuses_a_missing_selection_reason(): void
    {
        $edition = $this->draft();
        $edition->selections()->first()->forceFill(['selection_reason' => ''])->save();

        $this->assertStringContainsString('selection reason is missing', $this->errors($edition));
    }

    public function test_it_refuses_a_missing_methodology_statement(): void
    {
        $edition = $this->draft();
        $edition->forceFill(['methodology_text' => ''])->save();

        $this->assertStringContainsString('selection-method statement', $this->errors($edition));
    }

    public function test_it_refuses_a_review_that_is_not_publicly_visible(): void
    {
        $this->draft();
        PublishedReviewPayload::where('published_review_id', 'PR-2')
            ->first()->forceFill(['publish_status' => 'withdrawn'])->save();

        $this->assertStringContainsString(
            'publish_status "withdrawn" is not publicly visible',
            $this->errors(BestForEdition::first())
        );
    }

    public function test_it_refuses_a_withdrawn_disposition(): void
    {
        $edition = $this->draft();
        (new ReviewDispositionService())->recordWithdrawal('PR-3');

        $this->assertStringContainsString('PR-3 is withdrawn', $this->errors($edition));
    }

    public function test_it_refuses_a_materially_invalid_review(): void
    {
        $edition = $this->draft();
        (new ReviewDispositionService())->setValidity(
            'PR-4',
            ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID
        );

        $this->assertStringContainsString('PR-4 is materially invalid', $this->errors($edition));
    }

    public function test_temporary_unavailability_alone_does_not_block_publication(): void
    {
        $edition = $this->draft();
        (new ReviewDispositionService())->setAvailability(
            'PR-5',
            ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE
        );

        $this->assertSame('', $this->errors($edition));
    }

    public function test_it_refuses_the_same_product_in_two_slots(): void
    {
        $edition = $this->draft();
        PublishedReviewPayload::where('published_review_id', 'PR-2')
            ->first()->forceFill(['product_uid' => 'ASIN_PR1'])->save();

        $this->assertStringContainsString('same product appears in more than one slot', $this->errors($edition));
    }

    public function test_undecidable_distinctness_refuses_without_confirmation(): void
    {
        $edition = $this->draft();
        PublishedReviewPayload::where('published_review_id', 'PR-2')
            ->first()->forceFill(['product_uid' => null])->save();

        $confirmations = new PublicationConfirmations(
            notDivergenceHeld: $this->reviewIds,
            categoryConfirmed: $this->reviewIds,
            productDistinctnessConfirmed: false,
            videoConsistencyConfirmed: true,
        );

        $this->assertStringContainsString(
            'Product distinctness cannot be determined',
            $this->errors($edition, $confirmations)
        );
        $this->assertSame('', $this->errors($edition, $this->fullConfirmations()));
    }

    public function test_category_is_checked_deterministically_when_the_payload_links_to_a_video(): void
    {
        $subject = $this->subject('Automatic Dog Feeders', 2026);
        $edition = $this->draft($subject);

        $otherCategory = Category::create(['name' => 'Cat Trees']);
        $videoId = DB::table('videos')->insertGetId([
            'title' => 'A review',
            'type' => 'youtube',
            'video_url' => 'https://example.test/v',
            'character_id' => 1,
            'channel_id' => 1,
            'category_id' => $otherCategory->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PublishedReviewPayload::where('published_review_id', 'PR-1')
            ->first()->forceFill(['video_id' => $videoId, 'source' => 'admin'])->save();

        // Deterministic mismatch wins over the human confirmation.
        $this->assertStringContainsString(
            'PR-1 belongs to a different category',
            $this->errors($edition, $this->fullConfirmations())
        );
    }

    public function test_an_automatically_allocated_permanent_key_is_a_valid_ulid_and_passes(): void
    {
        $edition = $this->draft();

        $this->assertTrue(Str::isUlid($edition->public_edition_key));
        $this->assertSame('', $this->errors($edition));
    }

    public function test_an_explicit_non_ulid_permanent_key_is_refused_before_publication(): void
    {
        $edition = $this->draft();

        // A slug-shaped key would become a permanent public identifier at publication,
        // and there is no second chance to reject it afterwards.
        $edition->forceFill(['public_edition_key' => 'best-automatic-dog-feeders-2026'])->save();

        $this->assertStringContainsString('is not a valid ULID', $this->errors($edition));
    }

    public function test_a_blank_permanent_key_is_refused(): void
    {
        $edition = $this->draft();
        $edition->forceFill(['public_edition_key' => ''])->save();

        $this->assertStringContainsString('permanent edition key is missing', $this->errors($edition));
    }

    public function test_an_already_published_edition_is_refused_outright(): void
    {
        $edition = $this->draft();
        $edition->published_at = now();
        $edition->save();

        $this->assertStringContainsString('already published', $this->errors($edition->refresh()));
    }

    // ---- Regional eligibility (BR-IMPL-03) ---------------------------------------

    public function test_a_regional_subject_passes_when_every_payload_carries_that_region(): void
    {
        $edition = $this->draft($this->subject('Automatic Dog Feeders', 2026, 'AU'), 5, ['AU']);

        $this->assertSame('', $this->errors($edition));
    }

    public function test_a_regional_subject_passes_when_every_payload_carries_global(): void
    {
        $edition = $this->draft($this->subject('Automatic Dog Feeders', 2026, 'AU'), 5, ['GLOBAL']);

        $this->assertSame('', $this->errors($edition));
    }

    public function test_a_global_subject_passes_when_every_payload_carries_global(): void
    {
        $edition = $this->draft($this->subject('Automatic Dog Feeders', 2026, 'GLOBAL'), 5, ['GLOBAL']);

        $this->assertSame('', $this->errors($edition));
    }

    public function test_it_refuses_a_payload_with_no_region_rows_at_all(): void
    {
        $edition = $this->draft($this->subject('Automatic Dog Feeders', 2026, 'AU'), 5, []);

        $errors = $this->errors($edition);

        $this->assertStringContainsString('no explicit region rows', $errors);
        // The whole point: the review-page contract would read this as "all regions".
        $this->assertStringContainsString('not read here as "all regions"', $errors);
    }

    public function test_it_refuses_a_payload_evidenced_only_for_a_different_region(): void
    {
        $edition = $this->draft($this->subject('Automatic Dog Feeders', 2026, 'AU'), 5, ['US']);

        $errors = $this->errors($edition);

        $this->assertStringContainsString('regionally evidenced for US', $errors);
        $this->assertStringContainsString('does not satisfy the "AU" collection', $errors);
    }

    /**
     * A GLOBAL collection is served on every host, so a country-specific row is not
     * enough evidence for it — this is the asymmetry that makes GLOBAL stricter than AU.
     */
    public function test_a_global_subject_refuses_a_payload_evidenced_only_for_one_region(): void
    {
        $edition = $this->draft($this->subject('Automatic Dog Feeders', 2026, 'GLOBAL'), 5, ['AU']);

        $errors = $this->errors($edition);

        $this->assertStringContainsString('regionally evidenced for AU', $errors);
        $this->assertStringContainsString('does not satisfy the "GLOBAL" collection', $errors);
    }

    public function test_it_refuses_when_the_subject_region_is_inactive(): void
    {
        $edition = $this->draft($this->subject('Automatic Dog Feeders', 2026, 'AU'), 5, ['AU']);

        $this->region('AU', false);

        $errors = $this->errors($edition);

        $this->assertStringContainsString('region "AU" is not active', $errors);
        // One clear cause, not five per-review regional failures on top of it.
        $this->assertStringNotContainsString('no explicit region rows', $errors);
    }

    public function test_it_refuses_when_the_subject_has_no_resolvable_region(): void
    {
        $edition = $this->draft();

        // region_id is NOT NULL in schema, so this state is represented in memory:
        // the gate must still refuse it rather than skip the check.
        $subject = $edition->subject;
        $subject->region_id = null;
        $edition->setRelation('subject', $subject);

        $this->assertStringContainsString('has no region', $this->errors($edition));
    }

    // ---- market_id and category_region are deliberately not consulted -------------

    public function test_market_id_does_not_affect_the_outcome(): void
    {
        $subject = $this->subject('Automatic Dog Feeders', 2026, 'AU');
        $edition = $this->draft($subject, 5, ['AU']);

        $this->assertSame('', $this->errors($edition));

        // Absent, matching, and contradictory market values must all behave identically,
        // because market_id is external free text with no reference table.
        foreach ([null, 'AU', 'US', 'ZZ-NOT-A-MARKET'] as $index => $market) {
            PublishedReviewPayload::query()
                ->where('published_review_id', $this->reviewIds[$index])
                ->update(['market_id' => $market]);
        }

        $this->assertSame('', $this->errors($edition->refresh()));
    }

    public function test_a_missing_category_region_row_does_not_block_publication(): void
    {
        $subject = $this->subject('Automatic Dog Feeders', 2026, 'AU');
        $edition = $this->draft($subject, 5, ['AU']);

        // Nothing in this test ever writes category_region, and the edition still
        // publishes: an unverified pivot with no foreign keys is not a gate condition.
        $this->assertSame(0, DB::table('category_region')->where('category_id', $subject->category_id)->count());
        $this->assertSame('', $this->errors($edition));
    }
}
