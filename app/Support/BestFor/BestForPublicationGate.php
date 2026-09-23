<?php

namespace App\Support\BestFor;

use App\Models\BestForEdition;
use App\Models\BestForSubject;
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
 * ### Regional eligibility — stricter than the review page, on purpose
 *
 * A collection is published for exactly one region, carried by its subject. Every
 * selection must show EXPLICIT regional evidence for that region: an explicit row for
 * the subject's region, or an explicit GLOBAL row. A GLOBAL collection accepts only an
 * explicit GLOBAL row, because a collection served on every host cannot rest on a
 * review that is evidenced for one country.
 *
 * This deliberately does NOT reuse PublishedReviewPayload::scopePublicForRegion(). That
 * scope answers a different question — "is this page visible on THIS host right now" —
 * and treats an empty region set as "all regions" for backwards-compatibility with
 * pipeline-seeded payloads. Best For refuses that reading: an empty region set is
 * ABSENT EVIDENCE, and this gate never reads absent evidence as satisfaction. A review
 * page merely being visible somewhere is a weaker claim than recommending a product to
 * an audience. The scope's status clause is still reused; its region clause is not.
 *
 * `market_id` is not consulted anywhere in this class. It is nullable free text mirrored
 * from an external sheet, has no reference table and no consumer, and can change under an
 * existing review on re-import — so it cannot carry eligibility. `category_region` is not
 * consulted either: it has no foreign keys and its operational reliability is unproved,
 * and inventing a gate condition from an unverified pivot would refuse valid publications.
 *
 * ### Deliberately NOT part of this gate
 *
 * What happens AFTER publication when a payload's regions change. That affects live link
 * and action availability only; it never rewrites the immutable snapshot and never moves
 * recommendation_state or indexing_state on its own. It is later renderer work.
 */
final class BestForPublicationGate
{
    public const REQUIRED_SELECTION_COUNT = 5;

    /**
     * The region code denoting "every host". A global collection is an ordinary subject
     * pointing at this region row, not a null. Its existence as reference data is an
     * invariant the operational boundary must establish and protect before a real GLOBAL
     * publication — nothing here creates it, and a test fixture creating one proves only
     * that the gate reads it correctly.
     */
    public const GLOBAL_REGION_CODE = 'GLOBAL';

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

        // Resolved once, not per selection. Null means the subject's region is missing,
        // unresolvable or inactive — editionErrors() has already said so, and repeating
        // it five more times as a per-review regional failure would bury the real cause.
        $subjectRegionCode = $this->publishableSubjectRegionCode($edition);

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

            $errors = array_merge($errors, $this->referenceErrors($edition, $reviewId, $confirmations, $subjectRegionCode));
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

        $errors = array_merge($errors, $this->subjectRegionErrors($subject));

        return array_merge($errors, $this->snapshots->forEdition($subject)['errors']);
    }

    /**
     * The collection's own region must be established before any selection can be judged
     * against it. An inactive region is a refusal rather than a pass: a region switched
     * off is a deliberate operational act, and publishing a new collection into it would
     * contradict that act at the moment it is least visible.
     *
     * @return string[]
     */
    private function subjectRegionErrors(BestForSubject $subject): array
    {
        if ($subject->region_id === null) {
            return ['The collection subject has no region, so regional eligibility cannot be established.'];
        }

        $region = $subject->region;

        if (! $region) {
            return ['The collection subject region cannot be resolved.'];
        }

        if (! (bool) $region->is_active) {
            return [sprintf(
                'The collection subject region "%s" is not active.',
                (string) $region->region_code
            )];
        }

        return [];
    }

    /**
     * The subject's region code, or null when it is missing, unresolvable or inactive —
     * in which case the per-selection regional check is skipped and the single subject-level
     * error stands on its own.
     */
    private function publishableSubjectRegionCode(BestForEdition $edition): ?string
    {
        $subject = $edition->subject;

        if (! $subject || $subject->region_id === null) {
            return null;
        }

        $region = $subject->region;

        if (! $region || ! (bool) $region->is_active) {
            return null;
        }

        return (string) $region->region_code;
    }

    /**
     * Explicit regional evidence for one selection.
     *
     *   subject GLOBAL      → the payload must carry an explicit GLOBAL row
     *   subject e.g. AU     → an explicit AU row, or an explicit GLOBAL row
     *   no rows at all      → FAIL. Absence is not evidence of availability
     *   only another region → FAIL
     *
     * The two failures are reported differently because they need different fixes: an
     * empty set means the review has never been regionally classified, while a wrong set
     * means it has been classified and this collection is not in it.
     *
     * @return string[]
     */
    private function regionErrors(PublishedReviewPayload $payload, string $reviewId, string $subjectRegionCode): array
    {
        $acceptable = $subjectRegionCode === self::GLOBAL_REGION_CODE
            ? [self::GLOBAL_REGION_CODE]
            : [$subjectRegionCode, self::GLOBAL_REGION_CODE];

        $payloadCodes = $payload->regions()
            ->pluck('region_code')
            ->map(fn ($code) => (string) $code)
            ->all();

        if (array_intersect($payloadCodes, $acceptable) !== []) {
            return [];
        }

        if ($payloadCodes === []) {
            return [sprintf(
                'Review %s: no explicit region rows, so eligibility for the "%s" collection cannot be established. '
                . 'An empty region set is not read here as "all regions".',
                $reviewId,
                $subjectRegionCode
            )];
        }

        return [sprintf(
            'Review %s is regionally evidenced for %s, which does not satisfy the "%s" collection (needs %s).',
            $reviewId,
            implode(', ', $payloadCodes),
            $subjectRegionCode,
            implode(' or ', $acceptable)
        )];
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
    private function referenceErrors(
        BestForEdition $edition,
        string $reviewId,
        PublicationConfirmations $confirmations,
        ?string $subjectRegionCode
    ): array {
        $errors = [];
        $payload = $this->snapshots->resolvePayload($reviewId);

        if (! $payload) {
            return $errors;   // already reported by the snapshot builder
        }

        if ($subjectRegionCode !== null) {
            $errors = array_merge($errors, $this->regionErrors($payload, $reviewId, $subjectRegionCode));
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
