<?php

namespace Tests\Feature\AdminContent;

use App\Models\AdminAuditEvent;
use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\ReviewEditorialRecord;
use App\Models\ReviewPublicationDisposition;
use App\Models\User;
use App\Services\Reviews\EditorialTransitionException;
use App\Services\Reviews\ReviewPayloadIngestor;
use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\VideoAuthorityConfigurationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\TestCase;

/**
 * The editorial lifecycle T1-T6, holds, dispositions and supported ingestion (design
 * record section 4).
 */
class EditorialLifecycleTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->associations();
        $this->reviewer = User::factory()->create();
    }

    private function state(PublishedReviewPayload $payload): ?string
    {
        return $this->record($payload)?->editorial_state;
    }

    private function assertRefused(callable $action, string $messageFragment): void
    {
        try {
            $action();
        } catch (EditorialTransitionException $e) {
            $this->assertStringContainsString($messageFragment, implode(' | ', $e->errors));

            return;
        }
        $this->fail("Expected the transition to be refused with: {$messageFragment}");
    }

    private function actions(PublishedReviewPayload $payload): array
    {
        return AdminAuditEvent::query()->where('subject_type', 'review')->where('subject_key', $payload->published_review_id)
            ->orderBy('id')->pluck('action')->all();
    }

    public function test_a_review_with_no_record_is_pending_and_not_public(): void
    {
        $payload = $this->ingest($this->reviewRow());

        $this->assertNull($this->record($payload));
        $this->assertSame(ReviewEditorialRecord::STATE_PENDING_REVIEW, $this->editorial()->recordFor($payload->published_review_id)->editorial_state);
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload, null));
    }

    public function test_associations_resolve_by_exact_pipeline_code_and_are_audited(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $record = $this->confirm($payload, $this->reviewer->id);

        $this->assertTrue($record->associationsConfirmed());
        $this->assertSame('CH_PET', $record->channel->pipeline_code);
        $this->assertSame('CHR_REX', $record->character->pipeline_code);
        $this->assertContains('review.associations_confirmed', $this->actions($payload));

        $mismatch = $this->ingest($this->reviewRow('pr_test_002', ['channel' => 'ch_pet']));
        $this->assertRefused(fn () => $this->confirm($mismatch), 'No channel has pipeline_code exactly "ch_pet"');
    }

    public function test_t1_requires_confirmed_associations(): void
    {
        $payload = $this->ingest($this->reviewRow());

        $this->assertRefused(fn () => $this->qaPass($payload), 'Associations');
    }

    public function test_t1_binds_the_exact_reviewed_content_and_media(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $this->confirm($payload);
        $now = $this->fp($payload);

        $record = $this->qaPass($payload, $this->reviewer->id);

        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $record->editorial_state);
        $this->assertSame($now['content'], $record->bound_content_fingerprint);
        $this->assertSame($now['media'], $record->bound_media_fingerprint);
        $this->assertSame(FingerprintScheme::P1, $record->bound_fingerprint_scheme);
        $this->assertSame($this->reviewer->id, $record->state_changed_by);

        $event = AdminAuditEvent::query()->where('action', 'review.qa_passed')->firstOrFail();
        $this->assertSame($now['content'], $event->content_fingerprint);
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $event->to_state);
    }

    public function test_t1_and_t3_refuse_fingerprints_the_reviewer_did_not_see(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $this->confirm($payload);
        $now = $this->fp($payload);
        $stale = str_repeat('0', 64);

        $this->assertRefused(fn () => $this->editorial()->qaPass($payload, $stale, $now['media'], null), 'content changed since you opened');
        $this->assertRefused(fn () => $this->editorial()->qaPass($payload, $now['content'], $stale, null), 'media changed since you opened');

        $this->qaPass($payload);
        $this->assertRefused(fn () => $this->editorial()->release($payload->fresh(), $stale, $now['media'], 'go', null), 'content changed since you opened');
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->state($payload));
    }

    public function test_t1_is_not_repeatable_while_the_binding_is_current(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $this->confirm($payload);
        $this->qaPass($payload);

        $this->assertRefused(fn () => $this->qaPass($payload), 'not available from [qa_passed]');
    }

    public function test_t2_return_needs_a_reason_and_a_permitted_state(): void
    {
        $payload = $this->ingest($this->reviewRow());

        $this->assertRefused(fn () => $this->editorial()->returnForCorrection($payload, '  ', null), 'A reason is required');

        $this->editorial()->returnForCorrection($payload, 'Wrong product image', $this->reviewer->id);
        $this->assertSame(ReviewEditorialRecord::STATE_RETURNED_FOR_CORRECTION, $this->state($payload));

        $released = $this->releasedReview('pr_test_002');
        $this->assertRefused(fn () => $this->editorial()->returnForCorrection($released, 'late', null), 'cannot be returned for correction from [released]');
    }

    public function test_t3_release_captures_the_bound_media_and_the_review_verifies(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $this->confirm($payload);
        $video = $this->videoAsset();
        $staged = $this->registry()->stage($video, MediaAttachment::OWNER_REVIEW, $payload->published_review_id, 'video', null);
        $this->qaPass($payload);

        $this->assertSame(MediaAttachment::STATE_PENDING, $staged->fresh()->state, 'QA pass alone activates nothing');
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));

        $record = $this->release($payload, $this->reviewer->id);

        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $record->editorial_state);
        $this->assertSame(MediaAttachment::STATE_ACTIVE, $staged->fresh()->state);
        $this->assertSame('review:' . $payload->published_review_id . ':video', $staged->fresh()->active_slot);
        $this->assertNotNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));
        $this->assertSame(
            ['review.associations_confirmed', 'media.staged', 'review.qa_passed', 'media.activated', 'review.released'],
            $this->actions($payload)
        );
    }

    public function test_d1_and_d3_allow_a_qa_pass_but_refuse_release(): void
    {
        $d3 = $this->ingest($this->reviewRow('pr_d3'));
        $this->confirm($d3);
        config(['reviews.divergence_hold_product_uids' => [$d3->product_uid]]);
        $this->qaPass($d3);
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->state($d3), 'T1 is allowed while D3 applies');
        $this->assertTrue(AdminAuditEvent::query()->where('action', 'review.qa_passed')->firstOrFail()->details['d3_held']);
        $this->assertRefused(fn () => $this->release($d3), 'D3');
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->state($d3));

        $d1 = $this->ingest($this->reviewRow('pr_d1', [], ['safety' => ['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'stale']]));
        $this->confirm($d1);
        $this->qaPass($d1);
        $this->assertRefused(fn () => $this->release($d1), 'D1');
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->state($d1));
    }

    public function test_t3_requires_producer_clearance_and_no_hold_or_blocking_disposition(): void
    {
        $payload = $this->ingest($this->reviewRow('pr_status', ['publish_status' => 'ready_for_review']));
        $this->confirm($payload);
        $this->qaPass($payload);
        $this->assertRefused(fn () => $this->release($payload), 'Producer clearance is missing');

        $held = $this->ingest($this->reviewRow('pr_held'));
        $this->confirm($held);
        $this->qaPass($held);
        $this->editorial()->hold($held, 'Checking a claim', null);
        $this->assertRefused(fn () => $this->release($held), 'editorial hold');

        $withdrawn = $this->ingest($this->reviewRow('pr_withdrawn'));
        $this->confirm($withdrawn);
        $this->qaPass($withdrawn);
        $this->editorial()->withdraw($withdrawn, null, 'Manufacturer recall', null);
        $this->assertRefused(fn () => $this->release($withdrawn), 'withdrawn');

        $invalid = $this->ingest($this->reviewRow('pr_invalid'));
        $this->confirm($invalid);
        $this->qaPass($invalid);
        $this->editorial()->setValidity($invalid, ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID, 'Wrong model', null);
        $this->assertRefused(fn () => $this->release($invalid), 'materially invalid');
    }

    public function test_media_staged_after_qa_makes_the_binding_stale(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $this->confirm($payload);
        $this->qaPass($payload);
        $before = $this->fp($payload);

        $this->registry()->stage($this->imageAsset(), MediaAttachment::OWNER_REVIEW, $payload->published_review_id, 'poster', null);

        $this->assertNotSame($before['media'], $this->fp($payload)['media']);
        $this->assertTrue($this->editorial()->isStale($this->record($payload), $payload->fresh()));
        $this->assertRefused(fn () => $this->release($payload), 'changed since the QA pass');

        $this->qaPass($payload);   // a stale qa_passed record may be QA-passed again
        $this->release($payload);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->state($payload));
    }

    public function test_unpublishable_media_blocks_qa_pass(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $this->confirm($payload);
        $this->registry()->stage($this->imageAsset(null), MediaAttachment::OWNER_REVIEW, $payload->published_review_id, 'poster', null);

        $this->assertRefused(fn () => $this->qaPass($payload), 'needs alt text');
    }

    public function test_t4_unrelease_requires_a_release_and_a_reason(): void
    {
        $payload = $this->releasedReview();

        $this->assertRefused(fn () => $this->editorial()->unrelease($payload, '', null), 'A reason is required');
        $this->editorial()->unrelease($payload, 'Pausing for a re-shoot', $this->reviewer->id);
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->state($payload));
        $this->assertTrue($this->record($payload)->hasBinding(), 'unrelease keeps the binding');
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));

        $this->assertRefused(fn () => $this->editorial()->unrelease($payload, 'again', null), 'Only a released review');
    }

    public function test_t5_withdrawal_and_material_invalidity_demote_and_reinstatement_needs_a_fresh_release(): void
    {
        $payload = $this->releasedReview();

        $this->editorial()->withdraw($payload, ReviewPublicationDisposition::CLASS_ORDINARY_EDITORIAL, 'Taken down', $this->reviewer->id);
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->state($payload));
        $demotion = AdminAuditEvent::query()->where('action', 'review.demoted')->firstOrFail();
        $this->assertNull($demotion->actor_id, 'a demotion is a system action');
        $this->assertSame('withdrawn', $demotion->reason);
        $this->assertTrue($demotion->details['system']);

        $this->editorial()->reinstate($payload, 'Back up', $this->reviewer->id);
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->state($payload), 'reinstatement never re-exposes a release');
        $this->release($payload);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->state($payload));

        $this->editorial()->setValidity($payload, ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID, 'Wrong model', null);
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->state($payload));
        $this->assertSame(2, AdminAuditEvent::query()->where('action', 'review.demoted')->count());
    }

    public function test_temporary_unavailability_neither_blocks_release_nor_demotes_but_is_excluded_publicly(): void
    {
        $payload = $this->releasedReview();

        $this->editorial()->setAvailability($payload, ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE, 'Out of stock', null);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->state($payload));
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null), 'E7 excludes it');

        $other = $this->ingest($this->reviewRow('pr_unavailable'));
        $this->confirm($other);
        $this->qaPass($other);
        $this->editorial()->setAvailability($other, ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE, 'Out of stock', null);
        $this->assertSame([], $this->editorial()->releaseBlockers($other->fresh(), $this->record($other)));

        $this->editorial()->setAvailability($payload, ReviewPublicationDisposition::AVAILABILITY_AVAILABLE, 'Restocked', null);
        $this->assertNotNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));
    }

    public function test_t6_a_substantive_supported_refresh_invalidates_a_release_in_the_same_transaction(): void
    {
        $payload = $this->releasedReview();
        $old = $this->record($payload)->bound_content_fingerprint;

        $result = app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_test_001', ['h1' => 'A corrected title']));

        $this->assertSame('content_refreshed', $result['invalidation']);
        $record = $this->record($payload);
        $this->assertSame(ReviewEditorialRecord::STATE_PENDING_REVIEW, $record->editorial_state);
        $this->assertFalse($record->hasBinding());
        $this->assertNull($record->bound_fingerprint_scheme);

        $event = AdminAuditEvent::query()->where('action', 'review.invalidated')->firstOrFail();
        $this->assertSame('content_refreshed', $event->reason);
        $this->assertSame($old, $event->details['old_binding']['bound_content_fingerprint']);
        $this->assertSame($payload->fresh()->content_fingerprint, $event->content_fingerprint);
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));
    }

    public function test_t6_an_insignificant_refresh_leaves_the_release_standing(): void
    {
        $payload = $this->releasedReview();

        $result = app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_test_001', ['updated_at' => '2026-10-06 08:00:00'], [
            'beastiescore' => ['raw' => ['calculated_at' => '2026-10-06T08:00:00Z']],
            'safety' => ['safety_age_days' => 4],
        ]));

        $this->assertNull($result['invalidation']);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->state($payload));
        $this->assertSame('2026-10-06 08:00:00', (string) $payload->fresh()->updated_at);
        $this->assertNotNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));
    }

    public function test_t6_an_old_binding_never_comes_back(): void
    {
        $payload = $this->releasedReview();

        app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_test_001', ['h1' => 'Changed']));
        app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_test_001'));   // byte-equivalent original returns

        $this->assertSame(ReviewEditorialRecord::STATE_PENDING_REVIEW, $this->state($payload));
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));
    }

    public function test_t6_returned_reviews_go_back_to_pending_only_when_content_changes(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $this->editorial()->returnForCorrection($payload, 'Fix the verdict', null);

        $unchanged = app(ReviewPayloadIngestor::class)->upsert($this->reviewRow());
        $this->assertNull($unchanged['invalidation']);
        $this->assertSame(ReviewEditorialRecord::STATE_RETURNED_FOR_CORRECTION, $this->state($payload));

        $changed = app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_test_001', [], ['quick_verdict' => ['summary' => 'Corrected verdict.']]));
        $this->assertSame('content_refreshed_after_return', $changed['invalidation']);
        $this->assertSame(ReviewEditorialRecord::STATE_PENDING_REVIEW, $this->state($payload));
    }

    public function test_t6_an_unavailable_fingerprint_invalidates(): void
    {
        $payload = $this->releasedReview();
        $record = $this->record($payload);

        $reason = $this->editorial()->invalidateForIngestion($record, null, FingerprintScheme::P1, $record->bound_content_fingerprint);

        $this->assertSame('fingerprint_unavailable', $reason);
        $this->assertSame(ReviewEditorialRecord::STATE_PENDING_REVIEW, $record->fresh()->editorial_state);
    }

    public function test_ingestion_refuses_an_invalid_video_authority_and_writes_nothing(): void
    {
        $payload = $this->releasedReview();
        config(['reviews.video_authority' => 'Admin']);

        try {
            app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_test_001', ['h1' => 'Would demote']));
            $this->fail('ingestion must refuse');
        } catch (VideoAuthorityConfigurationException) {
        }

        $this->assertSame('Test Feeder Review', $payload->fresh()->h1);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->state($payload), 'a configuration typo never mass-demotes');
    }

    public function test_ingestion_keeps_the_withdrawn_protection(): void
    {
        $payload = $this->ingest($this->reviewRow('pr_test_001', ['publish_status' => 'withdrawn']));

        $skipped = app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_test_001', ['publish_status' => 'published']));
        $this->assertSame(ReviewPayloadIngestor::SKIPPED_WITHDRAWN, $skipped['status']);
        $this->assertSame('withdrawn', $payload->fresh()->publish_status);

        $forced = app(ReviewPayloadIngestor::class)->upsert($this->reviewRow('pr_test_001', ['publish_status' => 'published']), true);
        $this->assertSame(ReviewPayloadIngestor::IMPORTED, $forced['status']);
        $this->assertSame('published', $payload->fresh()->publish_status);
    }

    public function test_ingestion_never_accepts_derived_columns_from_the_input(): void
    {
        $payload = $this->ingest($this->reviewRow('pr_test_001', [
            'content_fingerprint' => str_repeat('a', 64),
            'content_fingerprint_scheme' => 'forged',
            'id' => 999,
        ]));

        $this->assertNotSame(str_repeat('a', 64), $payload->content_fingerprint);
        $this->assertSame(FingerprintScheme::P1, $payload->content_fingerprint_scheme);
        $this->assertNotSame(999, $payload->id);
    }

    public function test_holds_are_independent_of_state_and_need_reasons(): void
    {
        $payload = $this->releasedReview();

        $this->editorial()->hold($payload, 'Legal check', $this->reviewer->id);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->state($payload));
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null), 'a held review is not eligible');
        $this->assertRefused(fn () => $this->editorial()->hold($payload, 'again', null), 'already in place');

        $this->assertRefused(fn () => $this->editorial()->liftHold($payload, '', null), 'A reason is required');
        $this->editorial()->liftHold($payload, 'Cleared', $this->reviewer->id);
        $this->assertNotNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), null));
        $this->assertContains('review.hold_applied', $this->actions($payload));
        $this->assertContains('review.hold_lifted', $this->actions($payload));
    }

    public function test_legacy_admin_source_rows_are_view_and_disposition_only(): void
    {
        $legacy = $this->ingest($this->reviewRow('pr_legacy', ['source' => 'admin']));
        $now = $this->fp($legacy);

        $this->assertRefused(fn () => $this->confirm($legacy), 'Legacy admin-source');
        $this->assertRefused(fn () => $this->editorial()->qaPass($legacy, (string) $now['content'], (string) $now['media'], null), 'Legacy admin-source');
        $this->assertRefused(fn () => $this->editorial()->hold($legacy, 'x', null), 'Legacy admin-source');
        $this->assertRefused(fn () => $this->editorial()->updateNotes($legacy, 'x', null), 'Legacy admin-source');
        $this->assertRefused(fn () => $this->editorial()->setRegions($legacy, [], 'x', null), 'Legacy admin-source');

        $this->editorial()->withdraw($legacy, null, 'Retire the legacy row', null);
        $this->assertTrue(ReviewPublicationDisposition::query()->findOrFail('pr_legacy')->isWithdrawn());

        // Even a record forced into "released" never makes a legacy row eligible (E1).
        $record = new ReviewEditorialRecord(['published_review_id' => 'pr_legacy']);
        $record->forceFill([
            'editorial_state' => ReviewEditorialRecord::STATE_RELEASED,
            'bound_content_fingerprint' => $now['content'],
            'bound_media_fingerprint' => $now['media'],
            'bound_fingerprint_scheme' => FingerprintScheme::P1,
        ])->save();
        $this->assertStringContainsString('Only pipeline reviews can be released', implode(' | ', $this->editorial()->releaseBlockers($legacy, $record)));
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($legacy->fresh(), null));
    }

    public function test_region_changes_are_audited_and_need_a_reason(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $au = new Region();
        $au->forceFill(['region_name' => 'Australia', 'region_code' => 'AU', 'is_active' => 1, 'currency' => 'AUD', 'currency_symbol' => '$'])->save();

        $this->assertRefused(fn () => $this->editorial()->setRegions($payload, [$au->id], ' ', null), 'A reason is required');
        $this->assertRefused(fn () => $this->editorial()->setRegions($payload, [$au->id, 9999], 'x', null), 'do not exist');

        $this->editorial()->setRegions($payload, [$au->id], 'AU launch', $this->reviewer->id);
        $this->assertSame(['AU'], $payload->regions()->pluck('region_code')->all());

        $event = AdminAuditEvent::query()->where('action', 'review.regions_set')->firstOrFail();
        // Compared per key: MySQL's JSON type normalises object member order.
        $this->assertSame([], $event->details['before']);
        $this->assertSame(['AU'], $event->details['after']);
        $this->assertSame($this->reviewer->id, $event->actor_id);
    }

    public function test_admin_never_writes_n8n_owned_payload_content(): void
    {
        $payload = $this->ingest($this->reviewRow());
        $before = PublishedReviewPayload::query()->findOrFail($payload->id)->getAttributes();

        $this->confirm($payload);
        $this->editorial()->updateNotes($payload, 'Internal only', null);
        $this->qaPass($payload);
        $this->editorial()->hold($payload, 'x', null);
        $this->editorial()->liftHold($payload, 'y', null);
        $this->release($payload);
        $this->editorial()->unrelease($payload, 'z', null);
        $this->editorial()->withdraw($payload, null, 'w', null);

        $this->assertSame($before, PublishedReviewPayload::query()->findOrFail($payload->id)->getAttributes());
    }

    public function test_the_audit_trail_is_append_only(): void
    {
        $payload = $this->releasedReview();
        $event = AdminAuditEvent::query()->where('subject_key', $payload->published_review_id)->firstOrFail();
        $count = AdminAuditEvent::query()->count();

        try {
            $event->forceFill(['reason' => 'rewritten'])->save();
            $this->fail('audit events must not be updatable');
        } catch (LogicException) {
        }

        try {
            $event->delete();
            $this->fail('audit events must not be deletable');
        } catch (LogicException) {
        }

        $this->assertSame($count, AdminAuditEvent::query()->count());
        $this->assertNull($event->fresh()->reason, 'the stored event is unchanged');
    }
}
