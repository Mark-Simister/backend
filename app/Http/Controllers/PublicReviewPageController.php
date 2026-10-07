<?php

namespace App\Http\Controllers;

use App\Support\BestFor\CurrentMembershipResolver;
use App\Support\RegionResolver;
use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\VerifiedReview;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Public, SEO-friendly review page served at /review/{slug}, resolved against
 * published_review_payloads.review_slug. Returns complete crawlable HTML on the
 * first request (before any JS runs).
 *
 * PUBLIC RELEASE VERIFICATION (Admin Content & Media design record, section 5).
 * No review-derived content is emitted unless the actual current render projection
 * still matches the human-approved release binding. The review is located among the
 * shared verifier's candidates (E1-E7), verified (E8), and rendered ONLY from the
 * immutable VerifiedReview - never from the payload model. Content outside the render
 * projection is invisible to the renderer by construction.
 *
 * This is the PUBLIC route. The React /allreviews/{id} route (videos table) is
 * the internal/admin/preview surface. The legacy numeric /review/{id} route is
 * a best-effort 301 redirect into the slug system, to a verified review only
 * (see legacyRedirect()).
 *
 * Production behaviour is driven by config/reviews.php (PUBLIC_REVIEW_STATUSES,
 * PUBLIC_REVIEW_INDEXABLE, PUBLIC_REVIEW_CACHE_TTL) — nothing is hard-coded.
 */
class PublicReviewPageController extends Controller
{
    public function __construct(private PublicReleaseVerifier $verifier)
    {
    }

    public function show(Request $request, string $slug)
    {
        // Region-gate by the request's host — the SAME $request->getHost() used
        // for the canonical (originFor), so region + canonical never diverge.
        $region = RegionResolver::fromHost($request->getHost());

        // Verification runs BEFORE every cache lookup: a review that no longer verifies
        // is a 404, and nothing previously cached for it can be served. The verifier's SQL
        // gates read no stored JSON; a row whose JSON is not structurally evaluable is
        // ineligible on its own and can never fail the request.
        $verified = $this->verifier->verifiedRows($region, fn ($q) => $q
            ->where('published_review_payloads.review_slug', $slug))
            ->first();

        abort_unless($verified, 404);

        $indexable = (bool) config('reviews.indexable', false);
        $ttl = (int) config('reviews.cache_ttl', 0);

        // Per-region canonical: the host is computed from THIS request (the
        // regional host it was served on, e.g. au.fstg.beastierated.com), not
        // from the payload's stored canonical_url — the pipeline's stored host
        // reflects whatever region it assumed at generation time. The slug stays
        // authoritative. App\Providers\ProxyTrustServiceProvider makes this correct
        // behind the reverse proxy — and only for a verified proxy address. See originFor().
        $origin = $this->originFor($request, $verified->projection['flat']['canonical_url'] ?? null);

        // Only a successfully verified review can populate the cache. The key carries the
        // release binding (scheme, bound content and media fingerprints) plus indexability
        // and the origin, so a re-release, a media change, an indexability change or a
        // different regional host never shares an entry. 404s are never cached. TTL 0 = off.
        // $region is carried into the render so D2 Best For membership resolves
        // against the SAME host region as the payload gate and the canonical.
        $render = fn () => $this->renderHtml($verified, $indexable, $origin, $region);
        $html = $ttl > 0
            ? Cache::remember(self::cacheKey($verified, $indexable, $origin), $ttl, $render)
            : $render();

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', $indexable ? 'index, follow' : 'noindex, nofollow');
    }

    /** The public HTML cache identity of one verified review. */
    public static function cacheKey(VerifiedReview $verified, bool $indexable, string $origin): string
    {
        return 'review_html:' . implode(':', [
            $verified->projection['flat']['review_slug'] ?? '',
            $verified->scheme,
            $verified->contentFingerprint,
            $verified->mediaFingerprint,
            $indexable ? '1' : '0',
            $origin,
        ]);
    }

    /**
     * The scheme+host to build canonical / OG / JSON-LD URLs from. Primary =
     * the actual request host (per-region, computed at request time). The
     * stored canonical_url host is used ONLY as a fallback when the request
     * arrives on a non-regional host (CLI, local, apex) — the slug is always
     * authoritative, only the host varies.
     */
    private function originFor(Request $request, ?string $canonicalUrl): string
    {
        $host = $request->getHost();
        $isRegional = (bool) preg_match('/\.beastierated\.com$/i', $host);

        if ($isRegional) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        // Fallback: derive origin from the stored canonical_url if present,
        // otherwise use the current (non-regional) request host as-is.
        if ($canonicalUrl && ($p = parse_url($canonicalUrl)) && !empty($p['host'])) {
            return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    /**
     * Legacy numeric /review/{id} (old videos-backed URL) → best-effort 301 to
     * the canonical /review/{slug}, matched by product ASIN. Only a review that passes
     * public-release verification for this host is a valid target; anything else is a
     * 404, so the slug of an unreleased or ineligible review is never revealed. No
     * numeric review URL is ever served or canonical.
     */
    public function legacyRedirect(Request $request, int $id)
    {
        // Read ONE column, not the whole row. `review_reader` holds a column-scoped
        // grant — GRANT SELECT (id, product_asin_sku) ON videos — so Video::find()'s
        // `SELECT *` would fail with ERROR 1143. This also keeps every other column of
        // `videos` (internal notes, affiliate links, seo_publish_error, unpublished
        // drafts) out of the public renderer's read surface.
        $asin = DB::table('videos')->where('id', $id)->value('product_asin_sku');

        if (filled($asin)) {
            $region = RegionResolver::fromHost($request->getHost());
            $verified = $this->verifier->verifiedRows($region, fn ($q) => $q
                ->where('published_review_payloads.product_uid', 'like', '%ASIN_' . strtoupper($asin) . '%')
                ->orderBy('published_review_payloads.id'))
                ->first();
            if ($verified) {
                return redirect()->route('public.reviews.show', $verified->projection['flat']['review_slug'], 301);
            }
        }
        abort(404);
    }

    /**
     * Renders ONLY from the verified projection and the release-bound media. The view
     * receives the projection's flat columns (as $payload) and its review_page_json (as
     * $rp); the payload model itself never reaches the view.
     */
    private function renderHtml(VerifiedReview $verified, bool $indexable, string $origin, ?string $region = null): string
    {
        $projection = $verified->projection;
        $payload = (object) $projection['flat'];
        $rp = is_array($projection['review_page_json']) ? $projection['review_page_json'] : [];
        $modeA = $verified->scheme === FingerprintScheme::A1;

        // D2: CURRENT ACTIVE Best For memberships only. Read through the Best For
        // entities via published_review_id — never from published_review_payloads,
        // which carries no membership data. The resolver applies the exact-region
        // then GLOBAL precedence and exposes no numeric rank.
        $bestFor = (new CurrentMembershipResolver())->forReview($verified->publishedReviewId, $region);
        $schema = $projection['schema_json_ld'] ?: ($rp['schema'] ?? null);

        // Mode A (admin video authority): the visible video and the single VideoObject
        // come only from the release-bound Admin media. Payload VideoObject nodes are
        // never output, whichever stored schema source was used.
        $reviewVideo = null;
        if ($modeA) {
            $reviewVideo = $this->boundVideo($verified->media);
            $schema = $this->withAdminVideoObject($this->withoutVideoObjects($schema), $reviewVideo);
        }

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

        $videoAuthority = $modeA ? 'admin' : 'payload';

        return view('reviews.public-show', compact('payload', 'rp', 'schemaJson', 'canonical', 'indexable', 'origin', 'bestFor', 'videoAuthority', 'reviewVideo'))->render();
    }

    /**
     * The release-bound Admin video for Mode A, built only from the verified M1 rows:
     * the `video` attachment (embed URL from the allow-listed provider template) and the
     * `poster` attachment captured in the same release (its public URL and alt text).
     * Verification has already refused a bound video without a bound poster
     * (RP-ADMIN-CONTENT-MEDIA-DESIGN-CORR-01). Nothing is ever taken from the payload,
     * provider metadata, a provider API or a derived provider URL. When the poster URL
     * cannot be produced (no MEDIA_URL configured) the video is not shown at all.
     *
     * @param  array<int, array<string, mixed>>  $media
     * @return array<string, mixed>|null
     */
    private function boundVideo(array $media): ?array
    {
        $byRole = [];
        foreach ($media as $row) {
            $byRole[$row['role']] = $row['asset'];
        }

        $video = $byRole['video'] ?? null;
        $poster = $byRole['poster'] ?? null;
        if (! $video || ! $poster || ($video['kind'] ?? null) !== 'video' || ($poster['kind'] ?? null) !== 'image') {
            return null;
        }

        $embedUrl = $this->embedUrl((string) ($video['external_ref'] ?? ''));
        $posterUrl = $this->managedFileUrl($poster['disk'] ?? null, $poster['path'] ?? null);
        if ($embedUrl === null || $posterUrl === null) {
            return null;
        }

        return [
            'embed_url' => $embedUrl,
            'poster_url' => $posterUrl,
            'poster_alt' => (string) ($poster['alt_text'] ?? ''),
            'name' => $video['title'] ?? null,
            'description' => $video['description'] ?? null,
            'upload_date' => $video['provider_published_at'] ?? null,
            'duration_seconds' => $video['duration_seconds'] ?? null,
            'chapters' => is_array($video['chapters'] ?? null) ? $video['chapters'] : [],
        ];
    }

    /** Embed URL from the allow-listed provider template; null for anything else. */
    private function embedUrl(string $externalRef): ?string
    {
        [$provider, $id] = array_pad(explode(':', $externalRef, 2), 2, null);
        $spec = $provider === null ? null : config("media.providers.{$provider}");
        if (! is_array($spec) || $id === null || ! preg_match($spec['id_pattern'], $id)) {
            return null;
        }

        return str_replace('{id}', rawurlencode($id), $spec['embed']);
    }

    /**
     * Public URL of a managed file - only with an explicit disk URL (MEDIA_URL). Built
     * exactly as Laravel's local disk builds a URL for a configured `url`, without
     * instantiating the disk (which would create its root directory on a page render).
     */
    private function managedFileUrl(?string $disk, ?string $path): ?string
    {
        $base = $disk ? config("filesystems.disks.{$disk}.url") : null;
        if (! $path || ! filled($base)) {
            return null;
        }

        return rtrim((string) $base, '/') . '/' . ltrim($path, '/');
    }

    /** Removes every node whose @type is or contains VideoObject. */
    private function withoutVideoObjects(mixed $schema): mixed
    {
        if (! is_array($schema)) {
            return $schema;
        }
        if ($this->isVideoObject($schema)) {
            return null;
        }
        if (is_array($schema['@graph'] ?? null)) {
            $graph = array_filter($schema['@graph'], fn ($node) => ! $this->isVideoObject($node));
            $schema['@graph'] = array_is_list($schema['@graph']) ? array_values($graph) : $graph;
        }

        return $schema;
    }

    private function isVideoObject(mixed $node): bool
    {
        if (! is_array($node) || ! array_key_exists('@type', $node)) {
            return false;
        }
        $type = $node['@type'];

        return $type === 'VideoObject' || (is_array($type) && in_array('VideoObject', $type, true));
    }

    /** Adds exactly one VideoObject, built only from the bound Admin video. */
    private function withAdminVideoObject(mixed $schema, ?array $video): mixed
    {
        if ($video === null) {
            return $schema;
        }

        $node = array_filter([
            '@type' => 'VideoObject',
            'name' => $video['name'],
            'description' => $video['description'],
            'thumbnailUrl' => $video['poster_url'],
            'uploadDate' => $video['upload_date'] ? Carbon::parse($video['upload_date'], 'UTC')->toAtomString() : null,
            'duration' => $video['duration_seconds'] !== null ? 'PT' . (int) $video['duration_seconds'] . 'S' : null,
            'embedUrl' => $video['embed_url'],
            'hasPart' => $this->clips($video['chapters'], $video['duration_seconds']),
        ], fn ($v) => $v !== null && $v !== []);

        if (! is_array($schema)) {
            return ['@context' => 'https://schema.org', '@graph' => [$node]];
        }
        if (is_array($schema['@graph'] ?? null) && array_is_list($schema['@graph'])) {
            $schema['@graph'][] = $node;

            return $schema;
        }

        $context = $schema['@context'] ?? 'https://schema.org';
        unset($schema['@context']);

        return ['@context' => $context, '@graph' => [$schema, $node]];
    }

    /** Chapters as schema.org Clip nodes (name, start and end offsets in seconds). */
    private function clips(array $chapters, ?int $duration): array
    {
        $clips = [];
        $count = count($chapters);
        foreach (array_values($chapters) as $i => $chapter) {
            $end = $i + 1 < $count ? (int) $chapters[$i + 1]['t'] : $duration;
            $clips[] = array_filter([
                '@type' => 'Clip',
                'name' => (string) $chapter['label'],
                'startOffset' => (int) $chapter['t'],
                'endOffset' => $end,
            ], fn ($v) => $v !== null);
        }

        return $clips;
    }
}
