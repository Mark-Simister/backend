<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\ReviewPayloadPublisher;
use Throwable;

/**
 * The explicit human sign-off that turns an admin review into a public SSR page.
 *
 * This is the ONLY thing that creates the first published_review_payload — a video
 * merely reaching status=published never auto-publishes to SEO. Afterwards,
 * VideoObserver + RefreshSeoPayloadJob keep the snapshot in sync automatically.
 *
 * Runs synchronously so the admin gets immediate success/failure feedback.
 * (The admin UI that surfaces seo_publish_status / seo_publish_error is step 4.)
 */
class ReviewSeoPublishController extends Controller
{
    public function __construct(private ReviewPayloadPublisher $publisher) {}

    public function publish(Video $video)
    {
        try {
            $payload = $this->publisher->publish($video);
        } catch (Throwable) {
            // Unexpected failures are rethrown by the publisher so the QUEUE can retry
            // them. There is no queue here — a human is waiting — so surface the recorded
            // error instead of a 500. The publisher already reported it.
            return back()->withErrors([
                'seo' => $video->fresh()->seo_publish_error ?: 'Publish to SEO failed unexpectedly.',
            ]);
        }

        if ($payload) {
            return back()->with('status', "Published to SEO: /review/{$payload->review_slug}");
        }

        return back()->withErrors([
            'seo' => $video->fresh()->seo_publish_error ?: 'Publish to SEO failed.',
        ]);
    }

    public function withdraw(Video $video)
    {
        $this->publisher->withdraw($video);

        return back()->with('status', 'Withdrawn from SEO — the public page now returns 404.');
    }
}
