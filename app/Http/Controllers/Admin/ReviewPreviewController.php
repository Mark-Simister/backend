<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublishedReviewPayload;
use App\Support\BestFor\CurrentMembershipResolver;
use App\Support\Reviews\ReviewFingerprints;
use App\Support\Reviews\VideoAuthority;
use Illuminate\Http\Request;

/**
 * Authenticated Admin review preview (design record section 11, Phase-4 FU-9 capture).
 *
 *  - show():  the Admin wrapper - the rendered page in a frame, pending media in a side
 *             panel, and the fingerprints submitted with T1 (QA pass) and T3 (Release).
 *  - frame(): the stored payload rendered through reviews/public-show.blade.php, reused
 *             UNCHANGED, with indexable=false. Given ?fp=, it refuses to render content
 *             whose current fingerprint differs, so what was seen is what gets bound.
 *
 * Read-only: never creates a slug or payload row, never touches the public cache, never
 * applies public eligibility. Every response is noindex + no-store (AdminPrivateResponse).
 */
class ReviewPreviewController extends Controller
{
    public function show(string $review, ReviewEditorialController $workspace)
    {
        $payload = $this->payload($review);

        return view('admin.review-editorial.preview', $workspace->workspace($payload));
    }

    public function frame(Request $request, string $review)
    {
        $payload = $this->payload($review);

        $expected = $request->query('fp');
        if (is_string($expected) && $expected !== '') {
            $current = ReviewFingerprints::content($payload, VideoAuthority::resolve()->contentScheme());
            if ($current === null || ! hash_equals($current, $expected)) {
                return response('This review changed since the preview was opened. Reload the preview before recording a QA pass or Release.', 409)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
        }

        $origin = rtrim($request->getSchemeAndHttpHost(), '/');
        $rp = is_array($payload->review_page_json) ? $payload->review_page_json : [];
        $schema = $payload->schema_json_ld ?: ($rp['schema'] ?? null);
        $schemaJson = null;
        if ($schema) {
            $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $schemaJson = preg_replace('#https?://(?:[a-z]{2}\.)?(?:fstg\.|bstg\.)?beastierated\.com#i', $origin, $json);
        }

        $html = view('reviews.public-show', [
            'payload' => $payload,
            'rp' => $rp,
            'schemaJson' => $schemaJson,
            'canonical' => $origin . '/review/' . $payload->review_slug,
            'indexable' => false,
            'origin' => $origin,
            'bestFor' => (new CurrentMembershipResolver())->forReview($payload->published_review_id, null),
        ])->render();

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function payload(string $review): PublishedReviewPayload
    {
        return PublishedReviewPayload::query()->where('published_review_id', $review)->firstOrFail();
    }
}
