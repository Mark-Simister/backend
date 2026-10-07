<?php

namespace App\Support\Reviews;

use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The two review-media sets the editorial lifecycle compares (design record 4.3, 7).
 *
 *   active    what a public surface would show: the review's active attachments.
 *   proposed  what QA reviews and Release would capture: per review role, the staged
 *             (pending) attachment when one exists, otherwise the active one.
 *
 * Every review role is single-occupancy, so each set holds at most one attachment per
 * role.
 */
final class ReviewMediaSet
{
    /** @return Collection<int, MediaAttachment> */
    public static function active(string $publishedReviewId): Collection
    {
        return self::activeQuery()->where('owner_key', $publishedReviewId)->get();
    }

    /**
     * active() for many reviews in one read - the same query, keyed by review. Every
     * requested review is present (an empty set when it has no active attachments).
     *
     * @param  string[]  $publishedReviewIds
     * @return Collection<string, Collection<int, MediaAttachment>>
     */
    public static function activeFor(array $publishedReviewIds): Collection
    {
        $active = $publishedReviewIds === []
            ? collect()
            : self::activeQuery()->whereIn('owner_key', $publishedReviewIds)->get()->groupBy('owner_key');

        return collect($publishedReviewIds)->mapWithKeys(fn ($id) => [$id => $active->get($id, collect())->values()]);
    }

    private static function activeQuery(): Builder
    {
        return MediaAttachment::query()
            ->with('asset')
            ->where('owner_type', MediaAttachment::OWNER_REVIEW)
            ->where('state', MediaAttachment::STATE_ACTIVE)
            ->whereIn('role', self::roles())
            ->orderBy('id');
    }

    /** @return Collection<int, MediaAttachment> */
    public static function proposed(string $publishedReviewId): Collection
    {
        $current = MediaAttachment::query()
            ->with('asset')
            ->where('owner_type', MediaAttachment::OWNER_REVIEW)
            ->where('owner_key', $publishedReviewId)
            ->whereIn('state', [MediaAttachment::STATE_PENDING, MediaAttachment::STATE_ACTIVE])
            ->whereIn('role', self::roles())
            ->orderBy('id')
            ->get();

        $proposed = collect();
        foreach (self::roles() as $role) {
            $forRole = $current->where('role', $role);
            $pick = $forRole->where('state', MediaAttachment::STATE_PENDING)->sortByDesc('id')->first()
                ?? $forRole->where('state', MediaAttachment::STATE_ACTIVE)->first();
            if ($pick) {
                $proposed->push($pick);
            }
        }

        return $proposed->values();
    }

    /** @return string[] */
    public static function roles(): array
    {
        return array_keys((array) config('media.roles.' . MediaAttachment::OWNER_REVIEW, []));
    }

    /**
     * Mode A poster rule (RP-ADMIN-CONTENT-MEDIA-DESIGN-CORR-01): a review set that
     * carries a public `video` must also carry a `poster` in the same set. The poster is
     * the only source of the visible video poster and of VideoObject.thumbnailUrl; there
     * is no provider-thumbnail fallback and provider metadata is never a render source.
     *
     * @param  string[]  $roles  the roles present in one attachment set (active or proposed)
     */
    public static function modeAPosterSatisfied(array $roles): bool
    {
        return ! in_array('video', $roles, true) || in_array('poster', $roles, true);
    }
}
