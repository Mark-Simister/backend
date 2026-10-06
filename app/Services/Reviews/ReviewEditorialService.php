<?php

namespace App\Services\Reviews;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\ReviewEditorialRecord;
use App\Models\ReviewPublicationDisposition;
use App\Services\Audit\AdminAuditLog;
use App\Services\Media\MediaRegistry;
use App\Services\ReviewDispositionService;
use App\Support\Reviews\ReviewFingerprints;
use App\Support\Reviews\ReviewMediaSet;
use App\Support\Reviews\VideoAuthority;
use Illuminate\Support\Facades\DB;

/**
 * The editorial lifecycle - the Admin key of two-key publication (design record s4).
 *
 *   T1 qaPass             pending/returned/stale -> qa_passed   (review.qa)
 *   T2 returnForCorrection pending/qa_passed -> returned         (review.qa)
 *   T3 release            qa_passed -> released                  (review.release)
 *   T4 unrelease          released -> qa_passed                  (review.release)
 *   T5 demote             released -> qa_passed  (system: D3 hold, withdrawal, material invalidity)
 *   T6 invalidateForIngestion (system, inside the supported-ingestion transaction)
 *
 * QA and Release bind to the exact render projection (content fingerprint under the
 * active scheme) and the exact proposed media set (M1). Every transition is audited.
 * Admin never writes n8n-owned payload content or publish_status - the only payload
 * relation it writes is the region pivot for pipeline rows.
 */
class ReviewEditorialService
{
    public function __construct(
        private AdminAuditLog $audit,
        private MediaRegistry $media,
    ) {
    }

    public function recordFor(string $publishedReviewId): ReviewEditorialRecord
    {
        return ReviewEditorialRecord::query()->find($publishedReviewId)
            ?? new ReviewEditorialRecord(['published_review_id' => $publishedReviewId]);
    }

    /**
     * What the queue/workspace and the QA & Release forms need: the current content
     * fingerprint (active scheme) and the proposed media fingerprint.
     *
     * @return array{scheme: ?string, content: ?string, media: ?string}
     */
    public function currentFingerprints(PublishedReviewPayload $payload): array
    {
        $scheme = VideoAuthority::resolve()->contentScheme();

        return [
            'scheme' => $scheme,
            'content' => ReviewFingerprints::content($payload, $scheme),
            'media' => ReviewFingerprints::proposedMedia($payload->published_review_id),
        ];
    }

    /** Is the stored binding stale against the current content or proposed media? */
    public function isStale(ReviewEditorialRecord $record, PublishedReviewPayload $payload): bool
    {
        if (! $record->hasBinding()) {
            return false;
        }
        $now = $this->currentFingerprints($payload);

        return $now['scheme'] !== $record->bound_fingerprint_scheme
            || $now['content'] === null || ! hash_equals($record->bound_content_fingerprint, $now['content'])
            || $now['media'] === null || ! hash_equals($record->bound_media_fingerprint, $now['media']);
    }

    /** D3: is the review held by the governance divergence hold? (the scope's own predicate) */
    public function isDivergenceHeld(PublishedReviewPayload $payload): bool
    {
        return ! PublishedReviewPayload::query()->whereKey($payload->getKey())->passesDivergenceHold()->exists();
    }

    /** D1: is the review's public safety state contradictory? (the scope's own predicate) */
    public function isSafetyContradictory(PublishedReviewPayload $payload): bool
    {
        return ! PublishedReviewPayload::query()->whereKey($payload->getKey())->passesSafetyContradictionGate()->exists();
    }

    /**
     * Confirm the review's associations. Channel and character resolve by EXACT
     * pipeline_code match against the payload's own identity claim - they can never
     * contradict it. The category is a human confirmation.
     */
    public function confirmAssociations(PublishedReviewPayload $payload, int $categoryId, ?int $by): ReviewEditorialRecord
    {
        $this->assertPipeline($payload);

        $channel = $this->resolveChannel($payload);
        $character = $this->resolveCharacter($payload);
        $errors = [];
        if (! $channel) {
            $errors[] = 'No channel has pipeline_code exactly "' . $payload->channel . '".';
        }
        if (! $character) {
            $errors[] = 'No character has pipeline_code exactly "' . $payload->character_id . '".';
        }
        if (! Category::query()->whereKey($categoryId)->exists()) {
            $errors[] = 'The selected category does not exist.';
        }
        if ($errors !== []) {
            throw new EditorialTransitionException($errors);
        }

        return DB::transaction(function () use ($payload, $channel, $character, $categoryId, $by) {
            $record = $this->lockedRecord($payload->published_review_id);
            $before = $record->only(['channel_id', 'character_id', 'category_id']);

            $record->forceFill([
                'channel_id' => $channel->id,
                'character_id' => $character->id,
                'category_id' => $categoryId,
                'associations_confirmed_at' => now(),
                'associations_confirmed_by' => $by,
            ])->save();

            $this->audit->record('review.associations_confirmed', 'review', $payload->published_review_id, $by,
                $record->editorial_state, $record->editorial_state, null, null, [
                    'before' => $before,
                    'after' => $record->only(['channel_id', 'character_id', 'category_id']),
                ]);

            return $record;
        });
    }

    /**
     * Exact, case-sensitive match. The column is binary-collated on MySQL; the PHP
     * comparison keeps the match exact on any collation.
     */
    public function resolveChannel(PublishedReviewPayload $payload): ?Channel
    {
        $code = $payload->channel;

        return filled($code)
            ? Channel::query()->where('pipeline_code', $code)->get()->first(fn (Channel $c) => $c->pipeline_code === $code)
            : null;
    }

    public function resolveCharacter(PublishedReviewPayload $payload): ?Character
    {
        $code = $payload->character_id;

        return filled($code)
            ? Character::query()->where('pipeline_code', $code)->get()->first(fn (Character $c) => $c->pipeline_code === $code)
            : null;
    }

    /**
     * Set the region eligibility rows for a pipeline review (Admin-written pivot). This
     * changes public visibility, so it is gated by review.release at the route.
     *
     * @param  int[]  $regionIds
     */
    public function setRegions(PublishedReviewPayload $payload, array $regionIds, string $reason, ?int $by): void
    {
        $this->assertPipeline($payload);
        $this->assertReason($reason);

        $regionIds = array_values(array_unique(array_map('intval', $regionIds)));
        if (count($regionIds) !== Region::query()->whereKey($regionIds)->count()) {
            throw new EditorialTransitionException(['One or more selected regions do not exist.']);
        }

        DB::transaction(function () use ($payload, $regionIds, $reason, $by) {
            $before = $payload->regions()->pluck('region_code')->sort()->values()->all();
            $payload->regions()->sync($regionIds);
            $after = Region::query()->whereKey($regionIds)->pluck('region_code')->sort()->values()->all();

            $this->audit->record('review.regions_set', 'review', $payload->published_review_id, $by, null, null, $reason, null, [
                'before' => $before, 'after' => $after,
            ]);
        });
    }

    public function updateNotes(PublishedReviewPayload $payload, ?string $notes, ?int $by): ReviewEditorialRecord
    {
        $this->assertPipeline($payload);

        return DB::transaction(function () use ($payload, $notes, $by) {
            $record = $this->lockedRecord($payload->published_review_id);
            $record->internal_notes = filled($notes) ? $notes : null;
            $record->save();

            $this->audit->record('review.notes_updated', 'review', $payload->published_review_id, $by);

            return $record;
        });
    }

    /** T1 - record QA pass, binding the exact content and proposed media the reviewer saw. */
    public function qaPass(PublishedReviewPayload $payload, string $submittedContent, string $submittedMedia, ?int $by, ?string $reason = null): ReviewEditorialRecord
    {
        $this->assertPipeline($payload);

        return DB::transaction(function () use ($payload, $submittedContent, $submittedMedia, $by, $reason) {
            $record = $this->lockedRecord($payload->published_review_id);
            $now = $this->currentFingerprints($payload);
            $errors = [];

            $from = $record->editorial_state;
            $allowed = in_array($from, [ReviewEditorialRecord::STATE_PENDING_REVIEW, ReviewEditorialRecord::STATE_RETURNED_FOR_CORRECTION], true)
                || (in_array($from, [ReviewEditorialRecord::STATE_QA_PASSED, ReviewEditorialRecord::STATE_RELEASED], true) && $this->isStale($record, $payload));
            if (! $allowed) {
                $errors[] = "A QA pass is not available from [{$from}] while the bound content is current.";
            }
            if (! $record->associationsConfirmed()) {
                $errors[] = 'Associations (channel, character, category) must be confirmed first.';
            }
            $errors = array_merge($errors, $this->fingerprintErrors($now, $submittedContent, $submittedMedia));
            $errors = array_merge($errors, $this->mediaPublishabilityErrors(ReviewMediaSet::proposed($payload->published_review_id)));
            if ($errors !== []) {
                throw new EditorialTransitionException($errors);
            }

            $this->transition($record, ReviewEditorialRecord::STATE_QA_PASSED, $reason, $by, [
                'bound_content_fingerprint' => $now['content'],
                'bound_media_fingerprint' => $now['media'],
                'bound_fingerprint_scheme' => $now['scheme'],
            ]);

            $this->audit->record('review.qa_passed', 'review', $payload->published_review_id, $by, $from,
                ReviewEditorialRecord::STATE_QA_PASSED, $reason, $now['content'], [
                    'scheme' => $now['scheme'], 'media_fingerprint' => $now['media'],
                    'd1_contradictory' => $this->isSafetyContradictory($payload),
                    'd3_held' => $this->isDivergenceHeld($payload),
                ]);

            return $record;
        });
    }

    /** T2 - return for correction (returning to n8n is a manual operational step). */
    public function returnForCorrection(PublishedReviewPayload $payload, string $reason, ?int $by): ReviewEditorialRecord
    {
        $this->assertPipeline($payload);
        $this->assertReason($reason);

        return DB::transaction(function () use ($payload, $reason, $by) {
            $record = $this->lockedRecord($payload->published_review_id);
            $from = $record->editorial_state;
            if (! in_array($from, [ReviewEditorialRecord::STATE_PENDING_REVIEW, ReviewEditorialRecord::STATE_QA_PASSED], true)) {
                throw new EditorialTransitionException(["A review cannot be returned for correction from [{$from}]."]);
            }

            $this->transition($record, ReviewEditorialRecord::STATE_RETURNED_FOR_CORRECTION, $reason, $by);
            $this->audit->record('review.returned_for_correction', 'review', $payload->published_review_id, $by, $from,
                ReviewEditorialRecord::STATE_RETURNED_FOR_CORRECTION, $reason);

            return $record;
        });
    }

    /**
     * Every T3 precondition, as a list of blockers (empty = releasable). The workspace
     * shows this live; release() enforces it. D1 and D3 use the scope's own predicates.
     */
    public function releaseBlockers(PublishedReviewPayload $payload, ReviewEditorialRecord $record, ?string $submittedContent = null, ?string $submittedMedia = null): array
    {
        $errors = [];

        if ($payload->source !== 'pipeline') {
            $errors[] = 'Only pipeline reviews can be released; legacy admin-source rows are not publicly eligible.';
        }
        if ($record->editorial_state !== ReviewEditorialRecord::STATE_QA_PASSED) {
            $errors[] = "Release requires the qa_passed state (current: {$record->editorial_state}).";
        }
        if (! $record->associationsConfirmed()) {
            $errors[] = 'Associations (channel, character, category) are not confirmed.';
        }

        $now = $this->currentFingerprints($payload);
        if ($now['scheme'] === null) {
            $errors[] = 'The video-authority configuration is invalid; nothing can be released.';
        } elseif ($record->bound_fingerprint_scheme !== $now['scheme']) {
            $errors[] = 'The QA binding was made under a different scheme; record a fresh QA pass.';
        }
        if (! $record->hasBinding()
            || $now['content'] === null || ! hash_equals($record->bound_content_fingerprint, $now['content'])
            || $now['media'] === null || ! hash_equals($record->bound_media_fingerprint, $now['media'])) {
            $errors[] = 'The content or media changed since the QA pass; record a fresh QA pass.';
        }
        if ($submittedContent !== null || $submittedMedia !== null) {
            $errors = array_merge($errors, $this->fingerprintErrors($now, (string) $submittedContent, (string) $submittedMedia));
        }

        if (! in_array($payload->publish_status, (array) config('reviews.public_statuses', ['published']), true)) {
            $errors[] = "Producer clearance is missing: publish_status is [{$payload->publish_status}].";
        }
        if ($this->isDivergenceHeld($payload)) {
            $errors[] = 'D3: the review is under the divergence governance hold and cannot be released.';
        }
        if ($this->isSafetyContradictory($payload)) {
            $errors[] = 'D1: the review has a contradictory public safety state and cannot be released.';
        }
        if ($record->isHeld()) {
            $errors[] = 'An editorial hold is in place.';
        }

        $disposition = ReviewPublicationDisposition::query()->find($payload->published_review_id);
        if ($disposition?->isWithdrawn()) {
            $errors[] = 'The review is withdrawn.';
        }
        if ($disposition?->isMateriallyInvalid()) {
            $errors[] = 'The review is materially invalid.';
        }

        return array_values(array_unique(array_merge(
            $errors,
            $this->mediaPublishabilityErrors(ReviewMediaSet::proposed($payload->published_review_id))
        )));
    }

    /** T3 - release, capturing the bound review media set. */
    public function release(PublishedReviewPayload $payload, string $submittedContent, string $submittedMedia, string $reason, ?int $by): ReviewEditorialRecord
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($payload, $submittedContent, $submittedMedia, $reason, $by) {
            $record = $this->lockedRecord($payload->published_review_id);
            $errors = $this->releaseBlockers($payload, $record, $submittedContent, $submittedMedia);
            if ($errors !== []) {
                throw new EditorialTransitionException($errors);
            }

            $this->media->captureReviewSet($payload->published_review_id, $by);
            $this->transition($record, ReviewEditorialRecord::STATE_RELEASED, $reason, $by);

            $this->audit->record('review.released', 'review', $payload->published_review_id, $by,
                ReviewEditorialRecord::STATE_QA_PASSED, ReviewEditorialRecord::STATE_RELEASED, $reason,
                $record->bound_content_fingerprint, [
                    'scheme' => $record->bound_fingerprint_scheme,
                    'media_fingerprint' => $record->bound_media_fingerprint,
                ]);

            return $record;
        });
    }

    /** T4 - unrelease (human). Bindings are kept; the page is no longer eligible. */
    public function unrelease(PublishedReviewPayload $payload, string $reason, ?int $by): ReviewEditorialRecord
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($payload, $reason, $by) {
            $record = $this->lockedRecord($payload->published_review_id);
            if (! $record->isReleased()) {
                throw new EditorialTransitionException(['Only a released review can be unreleased.']);
            }

            $this->transition($record, ReviewEditorialRecord::STATE_QA_PASSED, $reason, $by);
            $this->audit->record('review.unreleased', 'review', $payload->published_review_id, $by,
                ReviewEditorialRecord::STATE_RELEASED, ReviewEditorialRecord::STATE_QA_PASSED, $reason);

            return $record;
        });
    }

    /**
     * T5 - system demotion of a released review (D3 hold now applies, withdrawal,
     * material invalidity). No-op unless released. Runs inside the caller's transaction
     * when there is one.
     */
    public function demote(string $publishedReviewId, string $reasonCode, ?int $by = null): bool
    {
        return DB::transaction(function () use ($publishedReviewId, $reasonCode, $by) {
            $record = ReviewEditorialRecord::query()->whereKey($publishedReviewId)->lockForUpdate()->first();
            if (! $record || ! $record->isReleased()) {
                return false;
            }

            $this->transition($record, ReviewEditorialRecord::STATE_QA_PASSED, $reasonCode, null);
            $this->audit->record('review.demoted', 'review', $publishedReviewId, null,
                ReviewEditorialRecord::STATE_RELEASED, ReviewEditorialRecord::STATE_QA_PASSED, $reasonCode, null, [
                    'system' => true, 'triggered_by' => $by,
                ]);

            return true;
        });
    }

    /**
     * T6 - supported-ingestion invalidation. MUST be called inside the ingestion
     * transaction with the editorial record locked. Returns the reason code applied, or
     * null when nothing changed.
     */
    public function invalidateForIngestion(ReviewEditorialRecord $record, ?string $newFingerprint, string $scheme, ?string $previousStoredFingerprint, string $source = 'supported_ingestion'): ?string
    {
        $from = $record->editorial_state;
        $reason = null;

        if (in_array($from, [ReviewEditorialRecord::STATE_QA_PASSED, ReviewEditorialRecord::STATE_RELEASED], true)) {
            if ($newFingerprint === null) {
                $reason = 'fingerprint_unavailable';
            } elseif ($record->bound_fingerprint_scheme !== $scheme
                || $record->bound_content_fingerprint === null
                || ! hash_equals($record->bound_content_fingerprint, $newFingerprint)) {
                $reason = 'content_refreshed';
            }
        } elseif ($from === ReviewEditorialRecord::STATE_RETURNED_FOR_CORRECTION
            && ($newFingerprint === null || $previousStoredFingerprint === null || ! hash_equals($previousStoredFingerprint, $newFingerprint))) {
            $reason = 'content_refreshed_after_return';
        }

        if ($reason === null) {
            return null;
        }

        $this->invalidateBinding($record, $reason, $newFingerprint, [
            'source' => $source, 'scheme' => $scheme, 'new_fingerprint' => $newFingerprint,
        ]);

        return $reason;
    }

    /**
     * T6 target: back to pending_review with the binding cleared, so a fresh QA pass and
     * Release are required. A cleared binding can never reactivate on its own, even if
     * byte-equivalent content later returns. System action, audited with the old binding.
     */
    public function invalidateBinding(ReviewEditorialRecord $record, string $reasonCode, ?string $newFingerprint = null, array $details = []): void
    {
        $from = $record->editorial_state;
        $old = [
            'bound_content_fingerprint' => $record->bound_content_fingerprint,
            'bound_media_fingerprint' => $record->bound_media_fingerprint,
            'bound_fingerprint_scheme' => $record->bound_fingerprint_scheme,
        ];

        $this->transition($record, ReviewEditorialRecord::STATE_PENDING_REVIEW, $reasonCode, null, [
            'bound_content_fingerprint' => null,
            'bound_media_fingerprint' => null,
            'bound_fingerprint_scheme' => null,
        ]);

        $this->audit->record('review.invalidated', 'review', $record->published_review_id, null, $from,
            ReviewEditorialRecord::STATE_PENDING_REVIEW, $reasonCode, $newFingerprint,
            array_merge(['system' => true, 'old_binding' => $old], $details));
    }

    public function hold(PublishedReviewPayload $payload, string $reason, ?int $by): ReviewEditorialRecord
    {
        $this->assertPipeline($payload);
        $this->assertReason($reason);

        return DB::transaction(function () use ($payload, $reason, $by) {
            $record = $this->lockedRecord($payload->published_review_id);
            if ($record->isHeld()) {
                throw new EditorialTransitionException(['An editorial hold is already in place.']);
            }

            $record->forceFill(['hold_at' => now(), 'hold_reason' => $reason, 'hold_by' => $by])->save();
            $this->audit->record('review.hold_applied', 'review', $payload->published_review_id, $by,
                $record->editorial_state, $record->editorial_state, $reason);

            return $record;
        });
    }

    public function liftHold(PublishedReviewPayload $payload, string $reason, ?int $by): ReviewEditorialRecord
    {
        $this->assertReason($reason);

        return DB::transaction(function () use ($payload, $reason, $by) {
            $record = $this->lockedRecord($payload->published_review_id);
            if (! $record->isHeld()) {
                throw new EditorialTransitionException(['There is no editorial hold to lift.']);
            }

            $record->forceFill(['hold_at' => null, 'hold_reason' => null, 'hold_by' => null])->save();
            $this->audit->record('review.hold_lifted', 'review', $payload->published_review_id, $by,
                $record->editorial_state, $record->editorial_state, $reason);

            return $record;
        });
    }

    /**
     * Disposition actions, delegating to the existing ReviewDispositionService unchanged.
     * A withdrawal or material invalidity demotes a release (T5) in the same transaction.
     */
    public function withdraw(PublishedReviewPayload $payload, ?string $proposedClass, string $reason, ?int $by): void
    {
        $this->assertReason($reason);

        DB::transaction(function () use ($payload, $proposedClass, $reason, $by) {
            app(ReviewDispositionService::class)->recordWithdrawal($payload->published_review_id, $proposedClass, $reason, $by);
            $this->audit->record('review.withdrawn', 'review', $payload->published_review_id, $by, null, null, $reason, null, [
                'proposed_class' => $proposedClass,
            ]);
            $this->demote($payload->published_review_id, 'withdrawn', $by);
        });
    }

    public function confirmWithdrawalClass(PublishedReviewPayload $payload, string $class, string $reason, ?int $by): void
    {
        $this->assertReason($reason);

        DB::transaction(function () use ($payload, $class, $reason, $by) {
            app(ReviewDispositionService::class)->confirmClassification($payload->published_review_id, $class, $reason, $by);
            $this->audit->record('review.withdrawal_class_confirmed', 'review', $payload->published_review_id, $by, null, null, $reason, null, [
                'class' => $class,
            ]);
        });
    }

    public function setValidity(PublishedReviewPayload $payload, string $state, string $reason, ?int $by): void
    {
        $this->assertReason($reason);

        DB::transaction(function () use ($payload, $state, $reason, $by) {
            app(ReviewDispositionService::class)->setValidity($payload->published_review_id, $state, $reason, $by);
            $this->audit->record('review.validity_set', 'review', $payload->published_review_id, $by, null, null, $reason, null, [
                'validity_state' => $state,
            ]);
            if ($state === ReviewPublicationDisposition::VALIDITY_MATERIALLY_INVALID) {
                $this->demote($payload->published_review_id, 'materially_invalid', $by);
            }
        });
    }

    /** Temporary unavailability is transient: excluded publicly, never demotes. */
    public function setAvailability(PublishedReviewPayload $payload, string $state, string $reason, ?int $by): void
    {
        $this->assertReason($reason);

        DB::transaction(function () use ($payload, $state, $reason, $by) {
            app(ReviewDispositionService::class)->setAvailability($payload->published_review_id, $state);
            $this->audit->record('review.availability_set', 'review', $payload->published_review_id, $by, null, null, $reason, null, [
                'availability_state' => $state,
            ]);
        });
    }

    /** Reinstatement clears the withdrawal; because T5 already demoted, a fresh Release is needed. */
    public function reinstate(PublishedReviewPayload $payload, string $reason, ?int $by): void
    {
        $this->assertReason($reason);

        DB::transaction(function () use ($payload, $reason, $by) {
            app(ReviewDispositionService::class)->reinstate($payload->published_review_id, $reason, $by);
            $this->audit->record('review.reinstated', 'review', $payload->published_review_id, $by, null, null, $reason);
        });
    }

    private function transition(ReviewEditorialRecord $record, string $to, ?string $reason, ?int $by, array $extra = []): void
    {
        $record->forceFill(array_merge([
            'editorial_state' => $to,
            'state_reason' => $reason,
            'state_changed_at' => now(),
            'state_changed_by' => $by,
        ], $extra))->save();
    }

    private function lockedRecord(string $publishedReviewId): ReviewEditorialRecord
    {
        $record = ReviewEditorialRecord::query()->whereKey($publishedReviewId)->lockForUpdate()->first();
        if ($record) {
            return $record;
        }

        $record = new ReviewEditorialRecord(['published_review_id' => $publishedReviewId]);
        $record->save();

        return ReviewEditorialRecord::query()->whereKey($publishedReviewId)->lockForUpdate()->firstOrFail();
    }

    /** @param array{scheme: ?string, content: ?string, media: ?string} $now */
    private function fingerprintErrors(array $now, string $submittedContent, string $submittedMedia): array
    {
        $errors = [];
        if ($now['scheme'] === null) {
            $errors[] = 'The video-authority configuration is invalid; no fingerprint can be bound.';
        }
        if ($now['content'] === null) {
            $errors[] = 'The review content cannot be fingerprinted (invalid or incomplete stored payload).';
        }
        if ($now['media'] === null) {
            $errors[] = 'The review media set cannot be fingerprinted.';
        }
        if ($now['content'] !== null && ! hash_equals($now['content'], $submittedContent)) {
            $errors[] = 'The content changed since you opened this review; reload and review it again.';
        }
        if ($now['media'] !== null && ! hash_equals($now['media'], $submittedMedia)) {
            $errors[] = 'The media changed since you opened this review; reload and review it again.';
        }

        return $errors;
    }

    private function mediaPublishabilityErrors(iterable $attachments): array
    {
        $errors = [];
        foreach ($attachments as $attachment) {
            /** @var MediaAttachment $attachment */
            $errors = array_merge($errors, $this->media->publishabilityErrors($attachment));
        }

        return $errors;
    }

    private function assertPipeline(PublishedReviewPayload $payload): void
    {
        if ($payload->source !== 'pipeline') {
            throw new EditorialTransitionException([
                'Legacy admin-source rows are not publicly eligible; only viewing and dispositions are available.',
            ]);
        }
    }

    private function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new EditorialTransitionException(['A reason is required.']);
        }
    }
}
