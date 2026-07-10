<?php

namespace App\Support\Reviews;

use App\Models\PublishedReviewPayload;
use App\Models\Video;
use Illuminate\Support\Str;

/**
 * Generates the stable public review slug for a video.
 *
 * IMMUTABLE: once videos.review_slug is set it is returned verbatim forever —
 * the slug is the indexed canonical URL, so an edit to the title/product name
 * must never change it.
 *
 * Shape mirrors the pipeline convention: "{product-name-slug}-{asin}".
 * Collisions fall back to -2, -3, …
 */
class ReviewSlugGenerator
{
    /** Existing slug if already assigned, else a fresh unique candidate (does NOT persist). */
    public static function forVideo(Video $video): string
    {
        if (filled($video->review_slug)) {
            return $video->review_slug;   // immutable
        }

        $base = static::base($video);
        $candidate = $base;
        $n = 1;
        while (static::isTaken($candidate, $video->id)) {
            $candidate = $base . '-' . (++$n);   // -2, -3, …
        }

        return $candidate;
    }

    /** product-name slug, suffixed with the ASIN when one exists. */
    protected static function base(Video $video): string
    {
        $name = Str::slug((string) ($video->product_name ?: $video->title));
        $asin = Str::slug((string) $video->product_asin_sku);

        $base = $asin !== '' ? "{$name}-{$asin}" : $name;

        return $base !== '' ? $base : 'review-' . $video->id;
    }

    /** A slug is taken if any other video, or any payload, already uses it. */
    protected static function isTaken(string $slug, ?int $exceptVideoId = null): bool
    {
        $videoTaken = Video::where('review_slug', $slug)
            ->when($exceptVideoId, fn ($q) => $q->where('id', '!=', $exceptVideoId))
            ->exists();

        return $videoTaken || PublishedReviewPayload::where('review_slug', $slug)->exists();
    }
}
