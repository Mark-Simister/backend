<?php

namespace App\Http\Controllers;

use App\Models\PublishedReviewPayload;
use App\Support\RegionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Public, SEO-friendly review page served at /review/{slug}, resolved against
 * published_review_payloads.review_slug. Returns complete crawlable HTML on the
 * first request (before any JS runs). The pipeline pre-builds review_page_json
 * + schema_json_ld, so this controller mostly decodes and hands them to the
 * Blade rather than recomputing display data or schema.
 *
 * This is the PUBLIC route. The React /allreviews/{id} route (videos table) is
 * the internal/admin/preview surface. The legacy numeric /review/{id} route is
 * a best-effort 301 redirect into the slug system (see legacyRedirect()).
 *
 * Production behaviour is driven by config/reviews.php (PUBLIC_REVIEW_STATUSES,
 * PUBLIC_REVIEW_INDEXABLE, PUBLIC_REVIEW_CACHE_TTL) — nothing is hard-coded.
 */
class PublicReviewPageController extends Controller
{
    /** publish_status values considered publicly visible (config-driven). */
    private function publicStatuses(): array
    {
        return (array) config('reviews.public_statuses', ['published']);
    }

    public function show(Request $request, string $slug)
    {
        // Region-gate by the request's host — the SAME $request->getHost() used
        // for the canonical (originFor), so region + canonical never diverge.
        // The publish_status gate + region eligibility live in one place:
        // PublishedReviewPayload::publicForRegion().
        $region = RegionResolver::fromHost($request->getHost());
        $payload = PublishedReviewPayload::where('review_slug', $slug)
            ->publicForRegion($region)
            ->first();

        abort_unless($payload, 404);

        $indexable = (bool) config('reviews.indexable', false);
        $ttl = (int) config('reviews.cache_ttl', 0);

        // Per-region canonical: the host is computed from THIS request (the
        // regional host it was served on, e.g. au.fstg.beastierated.com), not
        // from the payload's stored canonical_url — the pipeline's stored host
        // reflects whatever region it assumed at generation time. The slug stays
        // authoritative. App\Providers\ProxyTrustServiceProvider makes this correct
        // behind the reverse proxy — and only for a verified proxy address. See originFor().
        $origin = $this->originFor($request, $payload);

        // Cache key includes the origin (canonical/OG/JSON-LD are host-specific)
        // + indexability, so au/us/uk/ca and staging→prod never serve each
        // other's cached HTML. Only public 200s cached; 404s never. TTL 0 = off.
        $render = fn () => $this->renderHtml($payload, $indexable, $origin);
        $html = $ttl > 0
            ? Cache::remember(
                "review_html:{$payload->review_slug}:{$payload->updated_at}:" . ($indexable ? '1' : '0') . ":{$origin}",
                $ttl,
                $render
            )
            : $render();

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', $indexable ? 'index, follow' : 'noindex, nofollow');
    }

    /**
     * The scheme+host to build canonical / OG / JSON-LD URLs from. Primary =
     * the actual request host (per-region, computed at request time). The
     * stored canonical_url host is used ONLY as a fallback when the request
     * arrives on a non-regional host (CLI, local, apex) — the slug is always
     * authoritative, only the host varies.
     */
    private function originFor(Request $request, PublishedReviewPayload $payload): string
    {
        $host = $request->getHost();
        $isRegional = (bool) preg_match('/\.beastierated\.com$/i', $host);

        if ($isRegional) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        // Fallback: derive origin from the stored canonical_url if present,
        // otherwise use the current (non-regional) request host as-is.
        if ($payload->canonical_url && ($p = parse_url($payload->canonical_url)) && !empty($p['host'])) {
            return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    /**
     * Legacy numeric /review/{id} (old videos-backed URL) → best-effort 301 to
     * the canonical /review/{slug}, matched by product ASIN. If no published
     * payload maps to it, 404. No numeric review URL is ever served or canonical.
     */
    public function legacyRedirect(int $id)
    {
        // Read ONE column, not the whole row. `review_reader` holds a column-scoped
        // grant — GRANT SELECT (id, product_asin_sku) ON videos — so Video::find()'s
        // `SELECT *` would fail with ERROR 1143. This also keeps every other column of
        // `videos` (internal notes, affiliate links, seo_publish_error, unpublished
        // drafts) out of the public renderer's read surface.
        $asin = DB::table('videos')->where('id', $id)->value('product_asin_sku');

        if (filled($asin)) {
            $payload = PublishedReviewPayload::whereIn('publish_status', $this->publicStatuses())
                ->where('product_uid', 'like', '%ASIN_' . strtoupper($asin) . '%')
                ->first();
            if ($payload) {
                return redirect()->route('public.reviews.show', $payload->review_slug, 301);
            }
        }
        abort(404);
    }

    private function renderHtml(PublishedReviewPayload $payload, bool $indexable, string $origin): string
    {
        // Pre-built payloads (already decoded to arrays by the model casts).
        $rp = is_array($payload->review_page_json) ? $payload->review_page_json : [];
        $schema = $payload->schema_json_ld ?: ($rp['schema'] ?? null);

        // Canonical (+ OG url) are ALWAYS the slug URL on the request's host.
        $canonical = $origin . '/review/' . $payload->review_slug;

        // The pipeline pre-builds JSON-LD with a stored host (e.g. us.fstg…);
        // rewrite every internal beastierated host in it to the serving host so
        // @id/url/item fields match the per-region canonical. External URLs
        // (Amazon, Good Housekeeping, …) are untouched.
        $schemaJson = null;
        if ($schema) {
            $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $schemaJson = preg_replace(
                '#https?://(?:[a-z]{2}\.)?(?:fstg\.|bstg\.)?beastierated\.com#i',
                $origin,
                $json
            );
        }

        return view('reviews.public-show', compact('payload', 'rp', 'schemaJson', 'canonical', 'indexable', 'origin'))->render();
    }
}
