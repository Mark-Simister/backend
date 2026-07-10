# Phase 4 — Admin → Public SSR Publishing (region-gated)

Goal: when an admin review/video is **explicitly published to SEO** and assigned to
regions, create/refresh a `published_review_payloads` snapshot so `/review/{slug}`
resolves **only on those regions' hosts**. `published_review_payloads` remains the
single denormalized read model; masters stay `videos` (admin) and the spreadsheet
(pipeline).

## Decisions (locked)
- **Explicit gate:** an admin **"Publish to SEO"** action creates the first
  admin-sourced payload. Status→published alone does NOT auto-create.
- After first SEO-publish, observers/jobs **auto-refresh** the payload when the
  video's `review` JSON, `status`, `review_slug`, or region assignments change.
- Unpublished/drafted, or all eligible regions removed → **auto-withdraw** (payload
  marked unavailable, 404).
- **Snapshot** publishing — `/review/{slug}` never live-joins into `videos`.
- **EU out of scope** this phase (AU/US/UK/CA only); region resolver is extendable.
- **review-bstg shares the admin staging DB** (read-only renderer). Admin backend
  owns migrations/writes. **Do NOT drop the `beastie_review` PoC DB** until the
  shared-DB rollout is validated (see Rollout gate).
- Region eligibility: **empty pivot = all regions** (backwards-compat); a **GLOBAL
  region row also = all regions**. Admin payloads always sync explicit region rows
  from the video's regions.
- `videos.review_slug` is **immutable** after first generation.
- Admin SEO state exposed on `videos`: `seo_publish_status`, `seo_published_at`,
  `seo_last_published_at`, `seo_publish_error`.

## Host derivation (confirmed — the make-or-break detail)
Deployed proxy uses **`ProxyPreserveHost Off`** + `RequestHeader set X-Forwarded-Host
"<region>.fstg…"`. `bootstrap/app.php` trusts proxies incl. `HEADER_X_FORWARDED_HOST`.
So `PublicReviewPageController::originFor()` calling **`$request->getHost()`** returns
the regional host (Symfony overrides Host with the trusted X-Forwarded-Host). The
region resolver MUST use the **same `$request->getHost()`** — never raw
`X-Forwarded-Host`, never the origin Host header. Because canonical and region derive
from one shared call, they cannot diverge: a wrong host would show as a wrong
canonical (visible/validated), so silent region-gating failure is impossible.
**Fail-safe:** unrecognised host → region `null` → a region-restricted payload 404s
(never leaks to all regions).

## Data model
- **M1 `videos`:** `review_slug` (string, nullable, unique, immutable),
  `seo_publish_status` (default `unpublished`), `seo_published_at`,
  `seo_last_published_at` (timestamps, nullable), `seo_publish_error` (text, nullable).
- **M2 `published_review_payloads`:** `video_id` (nullable, indexed — no FK, matches
  existing pattern + dodges MySQL FK/charset traps), `source` (default `pipeline`),
  `published_at` (nullable).
- **M3 pivot `published_review_payload_region`:** `published_review_payload_id`,
  `region_id`, unique composite — **named short** (`prpr_pk_unique`) to stay under
  MySQL's 64-char index-name limit; `region_id` indexed (`prpr_region_idx`). No FKs.
- Admin rows: `published_review_id = "admin__{video_id}"`, `source='admin'`,
  `video_id` set; keyed on `video_id` for `updateOrCreate`. Pipeline rows unchanged.

## Region predicate — centralised
`RegionResolver::fromHost($host): ?string` (host label → region code, from
`config('reviews.region_hosts')`, extendable). Single model scope
**`PublishedReviewPayload::publicForRegion(?string $region)`** = publish_status gate
+ (empty pivot OR GLOBAL row OR the request region). Used by BOTH the controller and
sitemap — no duplication.
```php
// controller
$region = RegionResolver::fromHost($request->getHost());
$payload = PublishedReviewPayload::where('review_slug',$slug)->publicForRegion($region)->first();
// sitemap
PublishedReviewPayload::publicForRegion($region)->get([...]);
```

## Build phases
1. **Foundation (this pass):** M1–M3, model relations/casts, `RegionResolver`,
   `publicForRegion` scope, controller+sitemap wiring, BC + GLOBAL handling, tests.
   **No admin UI yet.**
2. Publishing engine: `ReviewSlugGenerator`, `VideoReviewMapper` (video.review →
   review_page_json shape + commerce/sources/safety/disclosure JSON),
   `ReviewSchemaBuilder` (schema_json_ld), `ReviewPayloadPublisher`
   (`publish`/`refresh`/`withdraw`), `RefreshSeoPayloadJob`.
3. Triggers: explicit "Publish to SEO" admin action; `VideoObserver` + region-update
   hook to auto-refresh/withdraw (only after first publish).
4. Admin UI: publish/withdraw buttons + SEO state display.

## Shared-DB rollout gate (do NOT retire beastie_review until all pass)
1. Shared staging DB has M1–M3.
2. Existing **vecomfy pipeline payload** seeded/imported into the shared DB.
3. review-bstg runs the compatible renderer code and its `.env` `DB_*` points at the
   shared DB.
4. **AU/US/UK/CA validations pass again** — incl. a real admin-published payload
   resolving region-gated AND the vecomfy pipeline row still resolving everywhere.
Only then retire the `beastie_review` PoC DB.

## Test plan
Region gating (AU/US/UK/CA + unknown host), empty-pivot BC, GLOBAL row, sitemap
per-region filtering, canonical host, no Set-Cookie, existing pipeline payload
compatibility; later: explicit-gate, auto-refresh, auto-withdraw, slug immutability,
mapper/schema/slug units, SPA regression.
