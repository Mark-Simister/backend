<?php

namespace App\Http\Controllers;

use App\Models\PublishedReviewPayload;
use App\Support\RegionResolver;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\VerifiedReview;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * SEO endpoints served by Laravel: /sitemap.xml and /robots.txt.
 * Both are per-region host-aware (computed from the request host) and gated by
 * config/reviews.php (public statuses + indexability).
 */
class SitemapController extends Controller
{
    /** XML sitemap of public review pages. <loc> uses the request's regional host. */
    public function index(Request $request, PublicReleaseVerifier $verifier)
    {
        // Exactly the rule /review/{slug} applies: the shared verifier's full pipeline for
        // THIS host (SQL gates, structural evaluability, D1, E8) on every row - a review
        // that fails any of them, e.g. whose recomputed content or media no longer matches
        // its release binding, is left out, so the sitemap never knowingly advertises a URL
        // that would 404 on this regional host. Not cached. The SQL gates read no stored
        // JSON and run once per bounded chunk, so a row with unevaluable JSON is simply left
        // out instead of failing or truncating the sitemap.
        // PRODUCTION NOTE: set PUBLIC_REVIEW_STATUSES=published in production.
        $region = RegionResolver::fromHost($request->getHost());
        $origin = rtrim($request->getSchemeAndHttpHost(), '/');

        $urls = '';
        $verifier->eachVerified($region, fn ($q) => $q->orderBy('published_review_payloads.updated_at', 'desc'),
            function (VerifiedReview $review, PublishedReviewPayload $r) use ($origin, &$urls) {
                // The slug comes from the verified projection; lastmod from the row's
                // operational timestamps (outside the projection by design).
                $loc = $origin . '/review/' . $review->projection['flat']['review_slug'];   // per-region, request host
                $lastmod = $r->updated_at ?: $r->created_at;
                $urls .= "  <url>\n    <loc>" . e($loc) . "</loc>\n";
                if ($lastmod) {
                    try {
                        $urls .= "    <lastmod>" . Carbon::parse($lastmod)->toAtomString() . "</lastmod>\n";
                    } catch (\Throwable $e) {
                        // skip an unparseable date rather than break the sitemap
                    }
                }
                $urls .= "  </url>\n";
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $urls
            . '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * robots.txt. On indexable (production) hosts: allow all + advertise the
     * per-region sitemap. On staging/local (not indexable): disallow everything
     * so nothing gets crawled, and omit the sitemap line.
     */
    public function robots(Request $request)
    {
        // Both conditions. A host that renders nothing has no sitemap to advertise, so
        // disabling rendering can never leave a `Sitemap:` line pointing at a 404 — and
        // the admin backend stays non-indexable regardless of PUBLIC_REVIEW_INDEXABLE.
        $indexable = (bool) config('reviews.indexable', false)
            && (bool) config('reviews.rendering_enabled', false);

        $origin = rtrim($request->getSchemeAndHttpHost(), '/');

        if ($indexable) {
            $body = "User-agent: *\nAllow: /\n\nSitemap: {$origin}/sitemap.xml\n";
        } else {
            $body = "User-agent: *\nDisallow: /\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
