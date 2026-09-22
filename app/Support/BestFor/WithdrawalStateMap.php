<?php

namespace App\Support\BestFor;

use App\Models\BestForEdition;
use App\Models\ReviewPublicationDisposition;

/**
 * Maps a review's EFFECTIVE withdrawal class onto a Best For edition's three orthogonal
 * operational columns. Pure and stateless: no database access, no model writes.
 *
 * Returns only the columns that must CHANGE. An empty array means "leave this edition
 * exactly as it is", which is a different and more honest statement than re-asserting
 * the current values — the caller never writes what it was not asked to write.
 *
 * ### Where each mapping comes from
 *
 * Tracker row 102 is authoritative for material, temporary-technical and unclassified:
 *   material              "active recommendation suspended … prominent page warning …
 *                          default noindex pending disposition … unpublication where
 *                          severity requires"
 *   temporary technical   "recommendation stands … no indexing change"
 *   unclassified          "defaults to the material class"
 *
 * ORDINARY EDITORIAL was NOT settled by that source — it describes the withdrawn ENTRY,
 * not the edition. Mark decided it in BR-IMPL-02 as Option A: the edition is untouched
 * (publication_state stays `live`, recommendation_state stays `active`, indexing_state
 * stays `indexable`). Ordinary editorial withdrawal is by definition not a truth problem,
 * so it must not suppress an otherwise healthy collection.
 *
 * The affected selection's historical treatment is DERIVED at read time from its linked
 * disposition row. There is deliberately no mutable per-selection operational state, which
 * is why `best_for_edition_selections` carries no operational columns at all.
 *
 * Severe unpublication is never inferred. It is a human severity judgement passed in
 * explicitly, and it only applies on top of a material class.
 */
final class WithdrawalStateMap
{
    /**
     * @param  string|null  $effectiveClass  from ReviewPublicationDisposition::effectiveWithdrawalClass();
     *                                       null means no withdrawal event exists
     * @param  bool  $severe  explicit human judgement that severity requires unpublication
     * @return array<string, string>  operational columns to change; empty = change nothing
     */
    public static function forEffectiveClass(?string $effectiveClass, bool $severe = false): array
    {
        // No withdrawal event. Nothing to do — and note that a temporarily unavailable
        // product reaches here as null, because availability is not withdrawal.
        if ($effectiveClass === null) {
            return [];
        }

        if ($effectiveClass === ReviewPublicationDisposition::CLASS_MATERIAL) {
            $state = [
                'recommendation_state' => BestForEdition::RECOMMENDATION_SUSPENDED,
                'indexing_state' => BestForEdition::INDEXING_NOINDEX,
            ];

            if ($severe) {
                $state['publication_state'] = BestForEdition::PUBLICATION_REMOVED;
            }

            return $state;
        }

        // Ordinary editorial (Option A) and temporary technical both leave the edition
        // untouched. They differ in how the ENTRY renders, which is derived elsewhere.
        return [];
    }

    /** Convenience: the mapping for a disposition, including the unclassified → material rule. */
    public static function forDisposition(ReviewPublicationDisposition $disposition, bool $severe = false): array
    {
        return self::forEffectiveClass($disposition->effectiveWithdrawalClass(), $severe);
    }

    /**
     * Does this single review currently demand material treatment?
     *
     * True for a confirmed material withdrawal, for an unclassified or unconfirmed
     * withdrawal (which reads as material until a human decides), and for a materially
     * invalid publication even when no withdrawal event exists.
     */
    public static function requiresMaterialTreatment(?ReviewPublicationDisposition $disposition): bool
    {
        if ($disposition === null) {
            return false;
        }

        return $disposition->effectiveWithdrawalClass() === ReviewPublicationDisposition::CLASS_MATERIAL
            || $disposition->isMateriallyInvalid();
    }

    /**
     * The disposition-driven restriction for an EDITION, derived from all of its selected
     * reviews at once.
     *
     * This is a whole-edition question, not a per-review one: a collection stays
     * restricted while ANY of its five selections still demands material treatment, and
     * becomes unrestricted the moment none of them does.
     *
     * This is an INPUT to recomputation, not the answer a consumer reads. Because it is
     * recomputed from the current disposition rows rather than remembered, confirming a
     * provisional material withdrawal as ordinary editorial or temporary technical simply
     * stops contributing a restriction — there is no stored disposition flag to unwind.
     * ReviewDispositionService combines this with the edition's manual holds and its
     * publication state, and persists the result into `recommendation_state` and
     * `indexing_state`, which are what a renderer, sitemap or SQL filter must read.
     *
     * @param  iterable<ReviewPublicationDisposition|null>  $dispositions
     * @return array<string, string>  empty = no disposition-driven restriction
     */
    public static function forSelections(iterable $dispositions): array
    {
        foreach ($dispositions as $disposition) {
            if (self::requiresMaterialTreatment($disposition)) {
                return self::forEffectiveClass(ReviewPublicationDisposition::CLASS_MATERIAL);
            }
        }

        return [];
    }
}
