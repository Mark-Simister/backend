<?php

namespace App\Support\Reviews;

use App\Models\MediaAttachment;
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
        return MediaAttachment::query()
            ->with('asset')
            ->where('owner_type', MediaAttachment::OWNER_REVIEW)
            ->where('owner_key', $publishedReviewId)
            ->where('state', MediaAttachment::STATE_ACTIVE)
            ->whereIn('role', self::roles())
            ->orderBy('id')
            ->get();
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
}
