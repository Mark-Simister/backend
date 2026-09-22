<?php

namespace App\Support\BestFor;

use App\Models\BestForEdition;
use App\Models\PublishedReviewPayload;
use App\Models\ReviewPublicationDisposition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Decides whether a Best For edition may be published. Pure validation: it collects
 * errors and writes nothing.
 *
 * Every check either passes deterministically from current backend data or demands an
 * explicit human confirmation. Nothing is inferred from absent evidence — a condition
 * that cannot be established is a refusal, not a pass.
 *
 * ### Deliberately NOT part of this gate
 *
 * Regional / market suitability. PublishedReviewPayload::scopePublicForRegion() exists,
 * but it answers a different question — "is this page visible on THIS host for THIS
 * request's region right now" — and Best For editions carry no market identity at all.
 * No approved contract authorises an all-region or GLOBAL restriction, so none is
 * imposed here. Its status clause is reused; the region clause is not. Regional
 * suitability remains a known later renderer/publication-workflow dependency and must
 * be resolved before any real production publication workflow is approved.
 */
final class BestForPublicationGate
{
    public const REQUIRED_SELECTION_COUNT = 5;

    private SelectionSnapshotBuilder $snapshots;

    public function __construct(?SelectionSnapshotBuilder $snapshots = null)
    {
        $this->snapshots = $snapshots ?? new SelectionSnapshotBuilder();
    }

    /**
     * @return string[] empty means publishable
     */
    public function errorsFor(BestForEdition $edition, PublicationConfirmations $confirmations): array
    {
        $errors = [];

        if ($edition->wasPublishedBeforeThisSave()) {
            return ['This edition is already published. Corrections are made by publishing a new edition.'];
        }

        $errors = array_merge($errors, $this->editionErrors($edition, $confirmations));

        $selections = $edition->selections()->get();
        $errors = array_merge($errors, $this->shapeErrors($selections));

        // Snapshot resolution doubles as reference validation: it proves each referenced
        // publication exists, carries the v6 contract and yields a truthful public_signal.
        $identifiers = [];
        foreach ($selections as $selection) {
            $reviewId = (string) $selection->published_review_id;
            $snapshot = $this->snapshots->forSelection($reviewId);
            $errors = array_merge($errors, $snapshot['errors']);

            if ($snapshot['errors'] === []) {
                $identifiers[$reviewId] = $snapshot['values']['source_product_identifier'] ?? null;
            }

            $errors = array_merge($errors, $this->referenceErrors($edition, $reviewId, $confirmations));
        }

        $errors = array_merge($errors, $this->distinctnessErrors($identifiers, $selections->count(), $confirmations));

        return array_values(array_unique($errors));
    }

    /** @return string[] */
    private function editionErrors(BestForEdition $edition, PublicationConfirmations $confirmations): array
    {
        $errors = [];

        // Allocated at creation by BestForEdition; publication validates it rather than
        // reassigning it. The key is an OPAQUE permanent identifier and must be a ULID —
        // a hand-set slug or category/year string would become permanent and public at
        // this moment, and there is no second chance to reject it.
        if (blank($edition->public_edition_key)) {
            $errors[] = 'The permanent edition key is missing.';
        } elseif (! Str::isUlid((string) $edition->public_edition_key)) {
            $errors[] = sprintf(
                'The permanent edition key "%s" is not a valid ULID.',
                (string) $edition->public_edition_key
            );
        }

        if (blank($edition->methodology_text)) {
            $errors[] = 'The mandatory selection-method statement (methodology_text) is missing.';
        }

        if (blank($edition->video_asset_id)) {
            $errors[] = 'No approved curated video asset is set.';
        }

        // Video-to-collection consistency is semantic and cannot be determined from any
        // data this backend holds. It is the one confirmation with a durable home.
        if (! $confirmations->videoConsistencyConfirmed) {
            $errors[] = 'Video-to-collection consistency has not been confirmed by a human.';
        }

        $subject = $edition->subject;

        if (! $subject) {
            $errors[] = 'The edition has no resolvable subject.';

            return $errors;
        }

        return array_merge($errors, $this->snapshots->forEdition($subject)['errors']);
    }

    /** @return string[] */
    private function shapeErrors($selections): array
    {
        $errors = [];
        $count = $selections->count();

        if ($count !== self::REQUIRED_SELECTION_COUNT) {
            $errors[] = sprintf(
                'A Best For edition publishes exactly %d selections; this edition has %d.',
                self::REQUIRED_SELECTION_COUNT,
                $count
            );
        }

        $positions = $selections->pluck('position')->map(fn ($p) => (int) $p)->all();
        sort($positions);
        if ($count === self::REQUIRED_SELECTION_COUNT && $positions !== range(1, self::REQUIRED_SELECTION_COUNT)) {
            $errors[] = 'Selection positions must be exactly 1 to ' . self::REQUIRED_SELECTION_COUNT . '.';
        }

        $reviewIds = $selections->pluck('published_review_id')->all();
        if (count($reviewIds) !== count(array_unique($reviewIds))) {
            $errors[] = 'The same review appears in more than one slot.';
        }

        $superlatives = $selections
            ->pluck('superlative')
            ->map(fn ($s) => mb_strtolower(trim((string) $s)))
            ->all();
        if (count($superlatives) !== count(array_unique($superlatives))) {
            $errors[] = 'Two selections share the same superlative.';
        }

        foreach ($selections as $selection) {
            $reviewId = (string) $selection->published_review_id;

            // Authored, not derived: validated here, never replaced from a payload.
            if (blank($selection->superlative)) {
                $errors[] = "Review {$reviewId}: superlative is missing.";
            }
            if (blank($selection->selection_reason)) {
                $errors[] = "Review {$reviewId}: selection reason is missing.";
            }
        }

        return $errors;
    }

    /** @return string[] */
    private function referenceErrors(BestForEdition $edition, string $reviewId, PublicationConfirmations $confirmations): array
    {
        $errors = [];
        $payload = $this->snapshots->resolvePayload($reviewId);

        if (! $payload) {
            return $errors;   // already reported by the snapshot builder
        }

        $publicStatuses = (array) config('reviews.public_statuses', ['published']);
        if (! in_array($payload->publish_status, $publicStatuses, true)) {
            $errors[] = sprintf(
                'Review %s: publish_status "%s" is not publicly visible.',
                $reviewId,
                (string) $payload->publish_status
            );
        }

        $disposition = ReviewPublicationDisposition::query()->find($reviewId);
        if ($disposition) {
            if ($disposition->isWithdrawn()) {
                $errors[] = sprintf(
                    'Review %s is withdrawn (effective class: %s).',
                    $reviewId,
                    (string) $disposition->effectiveWithdrawalClass()
                );
            }
            if ($disposition->isMateriallyInvalid()) {
                $errors[] = "Review {$reviewId} is materially invalid.";
            }
        }

        // No divergence data exists anywhere in this backend. Missing evidence must never
        // read as "not divergence-held", so every selection needs an explicit confirmation.
        if (! $confirmations->confirmsNotDivergenceHeld($reviewId)) {
            $errors[] = "Review {$reviewId}: not confirmed free of a divergence hold.";
        }

        $errors = array_merge($errors, $this->categoryErrors($edition, $payload, $reviewId, $confirmations));

        return $errors;
    }

    /**
     * Category membership is machine-checkable only where the payload links back to a
     * video: `video_id` is populated for admin-sourced payloads, and pipeline rows — the
     * normal production path — carry no category at all. Deterministic where possible,
     * explicit human confirmation where not.
     *
     * @return string[]
     */
    private function categoryErrors(BestForEdition $edition, PublishedReviewPayload $payload, string $reviewId, PublicationConfirmations $confirmations): array
    {
        $subjectCategoryId = $edition->subject?->category_id;

        if ($subjectCategoryId === null) {
            return [];   // already reported as an unresolvable subject
        }

        $categoryId = $payload->video_id === null
            ? null
            : DB::table('videos')->where('id', $payload->video_id)->value('category_id');

        if ($categoryId !== null) {
            return (int) $categoryId === (int) $subjectCategoryId
                ? []
                : ["Review {$reviewId} belongs to a different category than this collection."];
        }

        return $confirmations->confirmsCategory($reviewId)
            ? []
            : ["Review {$reviewId}: category membership cannot be determined from the payload and is not confirmed."];
    }

    /**
     * Distinctness beyond "no duplicate review". Deterministic only where every selection
     * carries a source product identifier; otherwise a human must confirm it. No canonical
     * product catalogue exists, and claiming machine certainty here would be false.
     *
     * @param  array<string, string|null>  $identifiers
     * @return string[]
     */
    private function distinctnessErrors(array $identifiers, int $selectionCount, PublicationConfirmations $confirmations): array
    {
        if ($identifiers === [] || count($identifiers) !== $selectionCount) {
            return [];   // reference errors already reported; distinctness is undecidable
        }

        $normalised = [];
        foreach ($identifiers as $identifier) {
            if (blank($identifier)) {
                return $confirmations->productDistinctnessConfirmed
                    ? []
                    : ['Product distinctness cannot be determined (a selection has no source product identifier) and is not confirmed.'];
            }

            $normalised[] = mb_strtoupper(trim((string) $identifier));
        }

        return count($normalised) === count(array_unique($normalised))
            ? []
            : ['The same product appears in more than one slot.'];
    }
}
