<?php

namespace App\Observers;

use App\Jobs\RefreshSeoPayloadJob;
use App\Models\PublishedReviewPayload;
use App\Models\Video;

/**
 * Keeps an already-SEO-published video's public payload in sync with admin edits.
 *
 * IMPORTANT: this NEVER creates the first payload — publishing is gated on the
 * explicit "Publish to SEO" action. It only fires once seo_published_at is set.
 *
 * No feedback loop: ReviewPayloadPublisher writes its own seo_* bookkeeping back
 * to the video inside Video::withoutEvents(), so the observer isn't re-triggered
 * by the publish it just performed.
 */
class VideoObserver
{
    /** Changing any of these on a published review invalidates the public snapshot. */
    public const SEO_RELEVANT = [
        'review',            // the review content itself (drives content_tier thin↔rich)
        'status',            // unpublish/draft → withdraw
        'review_type',       // no longer a review → withdraw
        'product_name',
        'product_asin_sku',
        'final_beastie_score',
        'public_rating',
        'character_score',
        'editorial_score',
        'affiliate_link',
        'cta_text',
        'seo_title',
        'seo_description',
        'og_image_url',
        'thumbnail_image',
    ];

    public function saved(Video $video): void
    {
        if (blank($video->seo_published_at)) {
            return;   // never SEO-published: the explicit action must run first
        }

        if (! $video->wasChanged(self::SEO_RELEVANT)) {
            return;   // nothing that affects the public page changed
        }

        RefreshSeoPayloadJob::dispatch($video->id);
    }

    /**
     * A deleted video must not leave a live public page behind. Withdraw the
     * payload directly — a queued refresh couldn't (the video no longer exists).
     */
    public function deleted(Video $video): void
    {
        PublishedReviewPayload::where('video_id', $video->id)
            ->update(['publish_status' => 'withdrawn', 'updated_at' => now()]);
    }
}
