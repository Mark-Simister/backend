<?php

namespace App\Support\BestFor;

use App\Models\BestForEdition;
use App\Models\BestForEditionSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * D2 read path, and only that: given one `published_review_id` and one host
 * region, which CURRENT ACTIVE Best For memberships may a public review page
 * show?
 *
 * This is a read-only adapter. It performs no publication, withdrawal or
 * snapshot logic, and it is deliberately independent of the write-side
 * services — a review page must never be able to change editorial state by
 * being rendered.
 *
 * WHY A RESOLVER AT ALL. The membership fact is owned by the Best For
 * entities and reached through `published_review_id`, which is a traceability
 * key rather than a foreign key: the publication row it names is rewritten
 * wholesale on every import. Nothing about membership is stored on
 * published_review_payloads, so the page cannot read it from the payload.
 *
 * THE FIVE CONDITIONS an edition must satisfy, all of them, before any
 * selection under it may appear:
 *   - the edition is published (`published_at` is not null);
 *   - `publication_state` is live;
 *   - `recommendation_state` is active;
 *   - it is the CURRENT edition for its own subject, which is expressed by the
 *     single `current_for_subject_id` mechanism pointing at that subject;
 *   - its subject resolves for this host under the precedence rule below.
 *
 * `indexing_state` is deliberately ABSENT from that list. It governs whether
 * the edition's own page may be indexed; it does not decide whether the
 * membership fact may appear on an inbound review page. A noindex edition that
 * is otherwise live, active and current still shows here, and that is correct.
 *
 * REGION PRECEDENCE, PER CATEGORY/YEAR GROUP, NEVER UNIONED. For each distinct
 * (category, year) subject group, the exact host-region subject wins if one
 * exists; otherwise the GLOBAL subject is the fallback; otherwise that group
 * contributes nothing. Exact and GLOBAL are never both returned for the SAME
 * category/year — that would show a reader two competing answers to one
 * question. Different category/year groups resolve independently, so one page
 * may legitimately show an exact-region membership for one group and a GLOBAL
 * membership for another.
 *
 * A null host region (a non-regional host: origin, local, CLI) cannot match any
 * exact-region subject, so it falls through to GLOBAL. That mirrors the
 * fail-safe already used by RegionResolver and the payload region gate.
 *
 * NO NUMERIC RANK LEAVES THIS CLASS. `best_for_edition_selections.position`
 * carries the approved internal ordering of the five entries, and it is
 * deliberately not part of the returned shape, so a renderer cannot present a
 * membership as "#2" even by accident.
 */
class CurrentMembershipResolver
{
    /** The GLOBAL collection is an ordinary regions row, matched by code. */
    public const GLOBAL_REGION_CODE = 'GLOBAL';

    /**
     * @param  string|null  $publishedReviewId  the review's traceability key
     * @param  string|null  $hostRegionCode     e.g. 'AU', or null on a non-regional host
     * @return Collection<int, array<string, mixed>>
     */
    public function forReview(?string $publishedReviewId, ?string $hostRegionCode): Collection
    {
        if (blank($publishedReviewId)) {
            return collect();
        }

        return BestForEditionSelection::query()
            ->where('published_review_id', $publishedReviewId)
            ->whereHas('edition', fn (Builder $edition) => $this->constrainToCurrentActivePublished($edition))
            ->with(['edition.subject.region'])
            ->get()
            // A selection whose subject or region row cannot be resolved is not
            // renderable as a membership. Dropping it is the fail-closed choice.
            ->filter(fn (BestForEditionSelection $s) => $s->edition?->subject?->region !== null)
            ->groupBy(fn (BestForEditionSelection $s) => $s->edition->subject->category_id . '|' . $s->edition->subject->year)
            ->map(fn (Collection $group) => $this->resolveOneGroup($group, $hostRegionCode))
            ->filter()
            ->map(fn (BestForEditionSelection $s) => $this->present($s))
            ->sortBy([['category_name', 'asc'], ['year', 'desc']])
            ->values();
    }

    /**
     * The edition-state gate. `whereColumn` is what makes "is the current
     * edition for its OWN subject" checkable in one predicate: the schema's
     * UNIQUE on current_for_subject_id already guarantees at most one current
     * edition per subject, and the model refuses a pointer at a foreign
     * subject, so equality here is both necessary and sufficient.
     */
    private function constrainToCurrentActivePublished(Builder $edition): void
    {
        $edition
            ->whereNotNull('published_at')
            ->where('publication_state', BestForEdition::PUBLICATION_LIVE)
            ->where('recommendation_state', BestForEdition::RECOMMENDATION_ACTIVE)
            ->whereColumn('current_for_subject_id', 'subject_id');
    }

    /**
     * Exact host region first, else GLOBAL, else nothing — for ONE category/year
     * group. Returning a single selection rather than a filtered list is what
     * makes the never-union rule structural instead of a convention.
     */
    private function resolveOneGroup(Collection $group, ?string $hostRegionCode): ?BestForEditionSelection
    {
        if (filled($hostRegionCode)) {
            $exact = $group->first(fn (BestForEditionSelection $s) => $this->regionCodeOf($s) === $hostRegionCode);

            if ($exact !== null) {
                return $exact;
            }
        }

        return $group->first(fn (BestForEditionSelection $s) => $this->regionCodeOf($s) === self::GLOBAL_REGION_CODE);
    }

    private function regionCodeOf(BestForEditionSelection $selection): ?string
    {
        return $selection->edition->subject->region->region_code ?? null;
    }

    /**
     * The public shape. Category name and year are read from the edition's
     * as-published columns rather than the live category row, because a
     * membership is a snapshot of what was published, not of what the taxonomy
     * says today. `position` is intentionally not included.
     *
     * @return array<string, mixed>
     */
    private function present(BestForEditionSelection $selection): array
    {
        $subject = $selection->edition->subject;
        $regionCode = (string) $subject->region->region_code;

        return [
            'category_id' => (int) $subject->category_id,
            'year' => (int) $subject->year,
            'category_name' => (string) $selection->edition->category_name_as_published,
            'superlative' => (string) $selection->superlative,
            'region_code' => $regionCode,
            'public_edition_key' => (string) $selection->edition->public_edition_key,
            'is_global_fallback' => $regionCode === self::GLOBAL_REGION_CODE,
        ];
    }
}
