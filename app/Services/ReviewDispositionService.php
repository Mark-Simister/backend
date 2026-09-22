<?php

namespace App\Services;

use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use App\Models\ReviewPublicationDisposition;
use App\Support\BestFor\WithdrawalStateMap;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runtime read/write for review publication dispositions, and the one place that turns a
 * disposition into a Best For edition's PERSISTED operational state.
 *
 * ### The stored columns are the truth
 *
 * `recommendation_state` and `indexing_state` on `best_for_editions` are the authoritative
 * effective state. A renderer, a sitemap query, an admin screen or a raw SQL filter may
 * read them directly and be correct. Nothing is left to a resolver a future consumer might
 * forget to call — an indexing rule that fails open is the failure this design exists to
 * prevent.
 *
 * ### Two inputs, only one of which is stored
 *
 *   DERIVED  the disposition contribution: does any selected review currently require
 *            material treatment? A pure function of `review_publication_dispositions`,
 *            recomputable at any time, so it is never stored separately.
 *
 *   STORED   the operator's own hold: `manual_recommendation_suspended_at` and
 *            `manual_noindex_at`. Cannot be derived from anything, so without these a
 *            recomputation would silently clear a human decision.
 *
 * ### The effective-state formula
 *
 *   recommendation_state = suspended  if manual_recommendation_suspended_at IS NOT NULL
 *                                     OR any selected review requires material treatment
 *                                     OR publication_state = removed
 *                          else active
 *
 *   indexing_state       = noindex    if manual_noindex_at IS NOT NULL
 *                                     OR any selected review requires material treatment
 *                                     OR publication_state = removed
 *                          else indexable
 *
 * The `removed` term is not decoration. Because `indexing_state` is what a sitemap filters
 * on, a severely unpublished edition must never store `indexable`, or the column would be
 * authoritative and wrong at the same time. `publication_state` carries its own
 * unambiguous provenance — nothing but explicit severe unpublication ever sets `removed`,
 * and only an explicit restore clears it — so recomputation reads it and never writes it.
 *
 * ### What this service will not do
 *
 * It never writes an editorial column. It never clears a manual marker as a side effect of
 * a disposition change. It never reverses a severe removal. It never writes per-selection
 * state, because there is none: a withdrawn entry's historical treatment is derived at
 * read time from its disposition row, which is why `best_for_edition_selections` carries
 * no operational columns at all.
 *
 * ### Recording is not confirming
 *
 * recordWithdrawal() may carry a proposed class but leaves it UNCONFIRMED, so
 * effectiveWithdrawalClass() reports `material` until a human confirms otherwise. An
 * unclassified withdrawal is treated as material pending disposition, so nothing keeps
 * recommending a review whose status nobody has decided.
 *
 * ### Concurrency
 *
 * Recomputation locks the edition row with lockForUpdate() and reads the manual markers
 * from the locked row, so a hold applied concurrently cannot be lost. That is a real row
 * lock on InnoDB and a no-op on SQLite, so the SQLite suite cannot prove it — it remains
 * subject to the later MySQL runtime qualification boundary.
 */
final class ReviewDispositionService
{
    // ---- Disposition rows -------------------------------------------------

    /** The stored row, or an unsaved instance for a review that has never had one. */
    public function for(string $publishedReviewId): ReviewPublicationDisposition
    {
        return ReviewPublicationDisposition::query()->find($publishedReviewId)
            ?? new ReviewPublicationDisposition(['published_review_id' => $publishedReviewId]);
    }

    /**
     * Record that a withdrawal happened. An optional proposed class is stored but NOT
     * confirmed, so treatment stays material until a human confirms it.
     */
    public function recordWithdrawal(
        string $publishedReviewId,
        ?string $proposedClass = null,
        ?string $reason = null,
        ?int $by = null,
        bool $severe = false
    ): ReviewPublicationDisposition {
        return DB::transaction(function () use ($publishedReviewId, $proposedClass, $reason, $by, $severe) {
            $disposition = $this->for($publishedReviewId);

            $disposition->withdrawn_at = $disposition->withdrawn_at ?? now();
            $disposition->withdrawal_class = $proposedClass;
            $disposition->classification_confirmed = false;
            $disposition->withdrawal_reason = $reason ?? $disposition->withdrawal_reason;
            $disposition->disposition_at = now();
            $disposition->disposed_by = $by;
            $disposition->save();

            $this->settleEditionsForReview($publishedReviewId, $severe, $reason, $by);

            return $disposition->refresh();
        });
    }

    /** A human confirms the class. From here the stored class governs treatment. */
    public function confirmClassification(
        string $publishedReviewId,
        string $class,
        ?string $reason = null,
        ?int $by = null,
        bool $severe = false
    ): ReviewPublicationDisposition {
        return DB::transaction(function () use ($publishedReviewId, $class, $reason, $by, $severe) {
            $disposition = $this->for($publishedReviewId);

            // The model refuses a class without a withdrawal event; make the event
            // explicit here rather than letting that guard fire.
            $disposition->withdrawn_at = $disposition->withdrawn_at ?? now();
            $disposition->withdrawal_class = $class;
            $disposition->classification_confirmed = true;
            $disposition->withdrawal_reason = $reason ?? $disposition->withdrawal_reason;
            $disposition->disposition_at = now();
            $disposition->disposed_by = $by;
            $disposition->save();

            $this->settleEditionsForReview($publishedReviewId, $severe, $reason, $by);

            return $disposition->refresh();
        });
    }

    /**
     * Clear the withdrawal event entirely, then recompute. This releases only the
     * DISPOSITION contribution — a manual hold and a severe removal both survive.
     */
    public function reinstate(string $publishedReviewId, ?string $reason = null, ?int $by = null): ReviewPublicationDisposition
    {
        return DB::transaction(function () use ($publishedReviewId, $reason, $by) {
            $disposition = $this->for($publishedReviewId);

            // Cleared together, so the row never passes through a class-without-event state.
            $disposition->withdrawn_at = null;
            $disposition->withdrawal_class = null;
            $disposition->classification_confirmed = false;
            $disposition->withdrawal_reason = $reason ?? $disposition->withdrawal_reason;
            $disposition->disposition_at = now();
            $disposition->disposed_by = $by;
            $disposition->save();

            $this->recomputeForReview($publishedReviewId, $reason, $by);

            return $disposition->refresh();
        });
    }

    /** Material validity is orthogonal to withdrawal and to availability. */
    public function setValidity(string $publishedReviewId, string $validityState, ?string $reason = null, ?int $by = null): ReviewPublicationDisposition
    {
        return DB::transaction(function () use ($publishedReviewId, $validityState, $reason, $by) {
            $disposition = $this->for($publishedReviewId);
            $disposition->validity_state = $validityState;
            $disposition->withdrawal_reason = $reason ?? $disposition->withdrawal_reason;
            $disposition->disposition_at = now();
            $disposition->disposed_by = $by;
            $disposition->save();

            $this->recomputeForReview($publishedReviewId, $reason, $by);

            return $disposition->refresh();
        });
    }

    /**
     * Availability moves on its own. It never sets withdrawn_at, never touches the class,
     * and contributes nothing to the restriction — a product being temporarily out of
     * stock is not a withdrawal and must not read as one.
     */
    public function setAvailability(string $publishedReviewId, string $availabilityState): ReviewPublicationDisposition
    {
        $disposition = $this->for($publishedReviewId);
        $disposition->availability_state = $availabilityState;
        $disposition->save();

        return $disposition->refresh();
    }

    // ---- Derivation inputs ------------------------------------------------

    /** Published and draft editions that reference this review. */
    public function affectedEditions(string $publishedReviewId): Collection
    {
        $editionIds = BestForEditionSelection::query()
            ->where('published_review_id', $publishedReviewId)
            ->pluck('edition_id')
            ->unique()
            ->all();

        return $editionIds === []
            ? collect()
            : BestForEdition::query()->whereIn('id', $editionIds)->get();
    }

    /** The current dispositions of every review selected by this edition, in position order. */
    public function dispositionsFor(BestForEdition $edition): Collection
    {
        $reviewIds = $edition->selections()->pluck('published_review_id')->all();

        if ($reviewIds === []) {
            return collect();
        }

        $rows = ReviewPublicationDisposition::query()->whereIn('published_review_id', $reviewIds)->get()
            ->keyBy('published_review_id');

        return collect($reviewIds)->map(fn ($id) => $rows->get($id));
    }

    /**
     * The disposition contribution for a whole edition, derived from all of its selected
     * reviews at once.
     *
     * This is a whole-edition question, not a per-review one: a collection stays restricted
     * while ANY selection still demands material treatment, and is released only when none
     * does. Naming the blockers is what makes "why is this still suspended?" answerable.
     *
     * @return array{restriction: array<string, string>, material_required: bool, blocking_review_ids: string[]}
     */
    public function dispositionRestrictionFor(BestForEdition $edition): array
    {
        $dispositions = $this->dispositionsFor($edition);

        $blocking = $dispositions
            ->filter(fn ($d) => WithdrawalStateMap::requiresMaterialTreatment($d))
            ->map(fn ($d) => (string) $d->published_review_id)
            ->values()
            ->all();

        return [
            'restriction' => WithdrawalStateMap::forSelections($dispositions),
            'material_required' => $blocking !== [],
            'blocking_review_ids' => $blocking,
        ];
    }

    // ---- Persisted recomputation ------------------------------------------

    /**
     * Recompute and persist the effective operational state of one edition.
     *
     * Idempotent: with unchanged inputs it writes nothing at all, so a repeated call
     * leaves `updated_at` alone. Never writes `publication_state`, never clears a manual
     * marker, never touches editorial data.
     *
     * Returns the locked, freshly-saved instance. A caller holding its own instance of the
     * same row should re-read it.
     */
    public function recomputeOperationalState(BestForEdition $edition, ?string $reason = null, ?int $by = null): BestForEdition
    {
        return DB::transaction(function () use ($edition, $reason, $by) {
            // Lock first, then read the manual markers FROM the locked row, so a hold
            // applied between our read and our write cannot be lost.
            $locked = BestForEdition::query()->whereKey($edition->getKey())->lockForUpdate()->firstOrFail();

            $materialRequired = $this->dispositionRestrictionFor($locked)['material_required'];
            $removed = $locked->publication_state === BestForEdition::PUBLICATION_REMOVED;

            $locked->recommendation_state =
                ($locked->manual_recommendation_suspended_at !== null || $materialRequired || $removed)
                    ? BestForEdition::RECOMMENDATION_SUSPENDED
                    : BestForEdition::RECOMMENDATION_ACTIVE;

            $locked->indexing_state =
                ($locked->manual_noindex_at !== null || $materialRequired || $removed)
                    ? BestForEdition::INDEXING_NOINDEX
                    : BestForEdition::INDEXING_INDEXABLE;

            if ($locked->isDirty(['recommendation_state', 'indexing_state'])) {
                $locked->operational_reason = $reason ?? $locked->operational_reason;
                $locked->operational_at = now();
                $locked->operational_by = $by;
                $locked->save();
            }

            return $locked;
        });
    }

    /**
     * Recompute every edition that references this review.
     *
     * @return int editions recomputed
     */
    public function recomputeForReview(string $publishedReviewId, ?string $reason = null, ?int $by = null): int
    {
        $count = 0;

        foreach ($this->affectedEditions($publishedReviewId) as $edition) {
            $this->recomputeOperationalState($edition, $reason, $by);
            $count++;
        }

        return $count;
    }

    /**
     * Explicit severe unpublication driven from a disposition decision. The only path here
     * that writes `publication_state = removed`, and never inferred — severity is a human
     * judgement passed in deliberately.
     *
     * @return int editions removed
     */
    public function applySevereUnpublication(string $publishedReviewId, ?string $reason = null, ?int $by = null): int
    {
        return DB::transaction(function () use ($publishedReviewId, $reason, $by) {
            $count = 0;

            foreach ($this->affectedEditions($publishedReviewId) as $edition) {
                $locked = BestForEdition::query()->whereKey($edition->getKey())->lockForUpdate()->firstOrFail();
                $locked->publication_state = BestForEdition::PUBLICATION_REMOVED;
                // A removed edition stops being the subject's current recommendation, and
                // nothing is promoted in its place.
                $locked->current_for_subject_id = null;
                $locked->operational_reason = $reason ?? $locked->operational_reason;
                $locked->operational_at = now();
                $locked->operational_by = $by;
                $locked->save();

                $this->recomputeOperationalState($locked, $reason, $by);
                $count++;
            }

            return $count;
        });
    }

    // ---- Manual operational planes ----------------------------------------

    /**
     * Apply an operator's own recommendation hold. Independent of indexing: suspending a
     * recommendation does not noindex the page.
     */
    public function applyManualRecommendationSuspension(BestForEdition $edition, string $reason, ?int $by = null): BestForEdition
    {
        return $this->setManualMarker($edition, 'manual_recommendation_suspended_at', now(), $reason, $by);
    }

    /**
     * Release an operator's recommendation hold, then RECOMPUTE. Clearing the marker does
     * not assume the edition becomes active: if a selected review still requires material
     * treatment, or the edition is severely removed, it stays suspended.
     */
    public function releaseManualRecommendationSuspension(BestForEdition $edition, string $reason, ?int $by = null): BestForEdition
    {
        return $this->setManualMarker($edition, 'manual_recommendation_suspended_at', null, $reason, $by);
    }

    /** Apply an operator's own noindex hold. Independent of the recommendation plane. */
    public function applyManualNoindex(BestForEdition $edition, string $reason, ?int $by = null): BestForEdition
    {
        return $this->setManualMarker($edition, 'manual_noindex_at', now(), $reason, $by);
    }

    /**
     * Release an operator's noindex hold, then RECOMPUTE. If a selected review still
     * requires material treatment, indexing_state stays `noindex`.
     */
    public function releaseManualNoindex(BestForEdition $edition, string $reason, ?int $by = null): BestForEdition
    {
        return $this->setManualMarker($edition, 'manual_noindex_at', null, $reason, $by);
    }

    private function setManualMarker(BestForEdition $edition, string $column, $value, string $reason, ?int $by): BestForEdition
    {
        return DB::transaction(function () use ($edition, $column, $value, $reason, $by) {
            $locked = BestForEdition::query()->whereKey($edition->getKey())->lockForUpdate()->firstOrFail();
            $locked->{$column} = $value;
            $locked->operational_reason = $reason;
            $locked->operational_at = now();
            $locked->operational_by = $by;
            $locked->save();

            return $this->recomputeOperationalState($locked, $reason, $by);
        });
    }

    // ---- Internal ---------------------------------------------------------

    /** Severe takes the removal path (which itself recomputes); otherwise recompute. */
    private function settleEditionsForReview(string $publishedReviewId, bool $severe, ?string $reason, ?int $by): int
    {
        return $severe
            ? $this->applySevereUnpublication($publishedReviewId, $reason, $by)
            : $this->recomputeForReview($publishedReviewId, $reason, $by);
    }
}
