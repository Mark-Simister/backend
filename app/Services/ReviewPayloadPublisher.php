<?php

namespace App\Services;

use App\Exceptions\ReviewPublishGateException;
use App\Models\PublishedReviewPayload;
use App\Models\Video;
use App\Support\Reviews\ReviewSchemaBuilder;
use App\Support\Reviews\ReviewSlugGenerator;
use App\Support\Reviews\VideoReviewMapper;
use Illuminate\Support\Str;
use Throwable;

/**
 * Publishes an admin video to the public SSR read model (published_review_payloads).
 *
 * - publish()  : the EXPLICIT human sign-off gate. Creates/updates the payload with
 *                publish_status='published' (admin rows are never 'ready_for_review',
 *                which stays a pipeline-only value).
 * - refresh()  : re-snapshots an ALREADY SEO-published video. content_tier is
 *                RE-EVALUATED every time (thin→rich when review content is added).
 * - withdraw() : marks the payload unavailable (404) — used when the video is
 *                unpublished/drafted or all regions are removed.
 *
 * FAILURE POLICY (applies to publish AND refresh). Two kinds, deliberately separated:
 *
 *  - EXPECTED (ReviewPublishGateException): the video fails the publish gate. Recorded
 *    as seo_publish_status='error' on the VIDEO, swallowed, and any existing live payload
 *    is left completely untouched. Retrying cannot help, so the job must NOT fail.
 *
 *  - UNEXPECTED (anything else): a bug or an infrastructure fault. Reported, recorded on
 *    the video best-effort, then RETHROWN so the queue retries it and, once $tries is
 *    exhausted, records it in failed_jobs. Swallowing these would turn a broken refresh
 *    into a successful-looking job and let the public page rot silently.
 *
 * Deployment monitoring therefore watches videos.seo_publish_status='error' for gate
 * failures, and failed_jobs only for genuinely exhausted unexpected exceptions.
 */
class ReviewPayloadPublisher
{
    public function __construct(
        private VideoReviewMapper $mapper,
        private ReviewSchemaBuilder $schema,
    ) {}

    /** Explicit "Publish to SEO" action. Returns null on failure (error recorded on the video). */
    public function publish(Video $video): ?PublishedReviewPayload
    {
        return $this->snapshot($video);
    }

    /** Re-snapshot after an edit. No-op unless the video was already SEO-published. */
    public function refresh(Video $video): ?PublishedReviewPayload
    {
        if (blank($video->seo_published_at)) {
            return null;   // never SEO-published: the explicit action is required first
        }

        // TAKEDOWN signals — the admin has intentionally made this not-a-public-review.
        // Withdraw the live page. (Contrast with a DATA GAP, e.g. product_name cleared:
        // snapshot() records an error and deliberately leaves the page up.)
        //   - all regions removed: an admin payload must never have an empty pivot,
        //     since "empty pivot" means ALL regions for pipeline back-compat.
        if ($video->status !== 'published'
            || $video->review_type !== 'review'
            || $video->regions()->count() === 0) {
            $this->withdraw($video);
            return null;
        }

        return $this->snapshot($video);
    }

    public function withdraw(Video $video): void
    {
        $payload = PublishedReviewPayload::where('video_id', $video->id)->first();
        if ($payload) {
            $payload->forceFill(['publish_status' => 'withdrawn', 'updated_at' => now()])->save();
        }
        $this->saveVideo($video, ['seo_publish_status' => 'withdrawn']);
    }

    /**
     * Persist the publisher's own bookkeeping WITHOUT firing model events —
     * otherwise VideoObserver would re-dispatch a refresh for the write we just made.
     */
    private function saveVideo(Video $video, array $attributes): void
    {
        Video::withoutEvents(fn () => $video->forceFill($attributes)->save());
    }

    /**
     * Fields that must be present before a video may become a public review page.
     * `product_name` is the discriminator: character showcase videos have none, and
     * publishing them would create product-less pages with Product schema.
     * `regions` is required so an admin payload can never have an empty pivot.
     *
     * @return string[] human-readable reasons the video cannot be published
     */
    public function gateErrors(Video $video): array
    {
        $errors = [];

        if ($video->review_type !== 'review') {
            $errors[] = 'review_type must be "review"';
        }
        if ($video->status !== 'published') {
            $errors[] = 'video status must be "published"';
        }
        if (blank($video->product_name)) {
            $errors[] = 'product_name is required (no product to review)';
        }
        if ($video->final_beastie_score === null) {
            $errors[] = 'final_beastie_score is required';
        }
        if ($video->regions()->count() === 0) {
            $errors[] = 'at least one region must be assigned';
        }

        return $errors;
    }

    private function snapshot(Video $video): ?PublishedReviewPayload
    {
        try {
            if ($errors = $this->gateErrors($video)) {
                throw ReviewPublishGateException::fromGateErrors($errors);
            }

            $slug = ReviewSlugGenerator::forVideo($video);          // immutable once set
            $tier = $this->mapper->contentTier($video);              // re-evaluated every run
            $reviewPage = $this->mapper->map($video, $slug, $tier);
            $schema = $this->schema->build($video, $slug, $tier);

            $payload = PublishedReviewPayload::firstOrNew(['video_id' => $video->id]);
            $r = is_array($video->review) ? $video->review : [];

            $payload->forceFill([
                'published_review_id' => 'admin__' . $video->id,
                'video_id' => $video->id,
                'source' => 'admin',
                'review_slug' => $slug,
                'publish_status' => 'published',   // human sign-off; never 'ready_for_review'
                'status' => 'admin_published',
                'content_tier' => $tier,
                'h1' => $reviewPage['page_identity']['h1'] ?? null,
                'seo_title' => $reviewPage['seo']['seo_title'] ?? null,
                'seo_description' => $reviewPage['seo']['seo_description'] ?? null,
                'og_image_url' => $reviewPage['seo']['og_image_url'] ?? null,
                'hero_image_url' => $reviewPage['product']['hero_image_url'] ?? null,
                'final_beastie_score' => $video->final_beastie_score,
                'public_score' => $video->public_rating,
                'public_rating_count' => $r['reviews_analysed'] ?? null,
                'confidence_tier' => $r['confidence'] ?? null,
                'channel' => $video->channel?->name,
                'character_name' => $video->character?->name,
                'character_id' => $video->character_id ? (string) $video->character_id : null,
                'review_page_json' => $reviewPage,
                'schema_json_ld' => $schema,
                'commerce_json' => $reviewPage['commerce'] ?? null,
                'sources_json' => $reviewPage['sources'] ?? null,
                'safety_json' => $reviewPage['safety'] ?? null,
                'disclosure_json' => $reviewPage['disclosure'] ?? null,
                'published_at' => $payload->published_at ?? now(),
                'created_at' => $payload->created_at ?? now(),
                'updated_at' => now(),
            ])->save();

            $payload->regions()->sync($video->regions()->pluck('regions.id')->all());

            $this->saveVideo($video, [
                'review_slug' => $slug,
                'seo_publish_status' => 'published',
                'seo_published_at' => $video->seo_published_at ?? now(),
                'seo_last_published_at' => now(),
                'seo_publish_error' => null,
            ]);

            return $payload;
        } catch (ReviewPublishGateException $e) {
            // EXPECTED. Record it, leave the live payload serving, and do not fail the job.
            $this->recordFailure($video, $e);

            return null;
        } catch (Throwable $e) {
            // UNEXPECTED. Report, record what we can, then rethrow so the worker retries
            // and eventually records a real failure instead of a phantom success.
            report($e);
            $this->recordFailure($video, $e);

            throw $e;
        }
    }

    /**
     * Best-effort failure bookkeeping on the video. If this write itself fails the
     * database is the broken thing — swallow it rather than masking the original cause.
     */
    private function recordFailure(Video $video, Throwable $e): void
    {
        try {
            $this->saveVideo($video, [
                'seo_publish_status' => 'error',
                'seo_publish_error' => Str::limit($e->getMessage(), 1000),
            ]);
        } catch (Throwable) {
            //
        }
    }
}
