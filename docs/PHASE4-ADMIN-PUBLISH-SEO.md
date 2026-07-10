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

## Tracked follow-ups (dated; not resolved by this feature)

These were discovered while auditing the shared-DB rollout. None is caused by the SEO
work; all three affect it. Recorded here so they are not visible only in a chat log.

### FU-1 — 2026-07-10 — `php artisan migrate` is broken on the admin backend, destructively
`themes` and `user_themes` **exist** in `bstd_staging` while their migrations
(`2026_02_04_055440`, `2026_02_04_072317`) are still `Pending`. Laravel runs pending
migrations in filename order, so a bare `migrate`:

1. applies `2025_09_26_130905_add_highlight_tags_last_checked_at_to_videos_table`
   (ALTERs `videos`, records the row), then
2. dies on `create_themes_table` with `SQLSTATE[42S01]: table already exists`.

MySQL has no transactional DDL, so step 1 cannot be undone. The result is a half-applied
batch, every time, forever, until this is reconciled.

**This is armed.** `.github/workflows/stg.yml` runs `php artisan migrate --force` on every
push to `staging`. The `--path`-scoped migration rule protects the SEO rollout only; it
does not protect the next person who pushes.

**Recommendation.** Add `Schema::hasTable()` early-return guards to both theme migrations —
the same idempotency pattern the review migrations already use — after confirming with
`SHOW CREATE TABLE themes` that the live tables match the stub definition (`id` +
`timestamps`). If they do not match, the migration is lying about the schema: record both
as already-run in the `migrations` table instead and leave the real tables alone. Either
way, run them as their own deliberate batch **before** any scoped batch, so the last batch
remains cleanly rollback-able. Also fix `stg.yml`, which additionally runs `git stash` and
silently discards whatever is dirty on the server.

### FU-2 — 2026-07-10 — `APP_ENV=local` disarms Laravel's destructive-command guard
`Illuminate\Console\ConfirmableTrait` prompts only when `app()->environment() === 'production'`.
On the admin backend (`APP_ENV=local`), **`php artisan migrate:fresh` and `db:wipe` drop every
table in the shared staging database with no confirmation.**

Changing `APP_ENV` to `staging` buys nothing — only the literal string `production` arms the
prompt. `APP_ENV=production` does arm it, and its only effect on our code is
`config/reviews.php:29`, where `indexable` defaults to `APP_ENV === 'production'` — which
would flip the admin host's `robots.txt` to allow-all.

**Recommendation.** Set `PUBLIC_REVIEW_INDEXABLE=false` explicitly on the admin backend
(the SEO rollout does this at A5), and only then set `APP_ENV=production`. Verify
`/robots.txt` still returns `Disallow: /` afterwards. Note the guard remains partial:
`--force` bypasses the prompt, and CI already passes `--force` to `migrate`.

This is why the rollout sets `PUBLIC_REVIEW_INDEXABLE` and `TRUSTED_PROXIES` as explicit
environment variables rather than deriving them from the environment name. Two safety
properties keyed off one string will eventually pull in opposite directions.

### FU-3 — 2026-07-10 — `PUBLIC_REVIEW_STATUSES=published` is permanent policy, not a one-off
Only `published` is publicly renderable. `ready_for_review` is a workflow state.
`draft → ready_for_review → published → public website`.

The chain that decides a pipeline row's visibility is
**`Published_Review_Payloads` sheet → `extract_published_payloads.py` → `published_review_payloads.json`
→ `PublishedReviewPayloadSeeder` → DB**, and the seeder's `updateOrCreate()` overwrites
`publish_status` from the JSON. So a DB-only `UPDATE` is reverted by the next import, and a
JSON-only edit is reverted by the next extraction. **Promotion must originate in the sheet.**

**Consequence for the unbuilt WF4 ingestion endpoint:** it must carry its own explicit
mechanism for setting `publish_status='published'` when its editorial process completes.
Without one, every pipeline-generated review will land as `ready_for_review`, be invisible
to the public site, and stall until a human edits the spreadsheet. The seeder must also
refuse to move a row **out of** `withdrawn` without `--force`: a takedown is a public-safety
action and a routine import must not resurrect the page.

### FU-4 — 2026-07-10 — The trusted proxy address must be VERIFIED, never assumed

**VALUE: NOT YET CAPTURED.** `TRUSTED_PROXIES` is unset, and
`config('reviews.trusted_proxies')` therefore defaults to `[]` — no proxy is trusted, in
any environment. This is deliberate: `bootstrap/app.php` previously trusted `at: '*'`, and
the admin backend is directly reachable, so any client could forge `X-Forwarded-Host` and
pull an AU-only review page onto any host.

Do **not** assume the Apache→Laravel connection arrives from `127.0.0.1`. The regional
vhosts `ProxyPass` to the review-bstg vhost by hostname, which may resolve to the
instance's private address (`172.31.x.x`) rather than loopback.

**How to derive it, without creating a public diagnostic endpoint:**

1. Confirm `mod_remoteip` is NOT enabled — if it is, the logged address has already been
   rewritten and will not match `REMOTE_ADDR`:
   `apachectl -M | grep -i remoteip`
2. Find the review-bstg vhost's access log:
   `sudo grep -riE "ServerName|CustomLog" /etc/apache2/sites-enabled/ | grep -i review`
3. Request a regional URL from outside the box, then read the newest line. Its first field
   is the address Laravel will see as `REMOTE_ADDR`:
   `curl -s -o /dev/null https://au.fstg.beastierated.com/robots.txt`
   `sudo tail -1 /var/log/apache2/<review-bstg>-access.log | awk '{print $1}'`

Only if the log is ambiguous, fall back to a **temporary** log line inside an EXISTING
controller (never a new route): `Log::info('proxy-src', ['ip' => $request->server('REMOTE_ADDR')])`
in `SitemapController::robots()`. Capture once, then remove it and *verify* the removal —
`git diff --exit-code app/Http/Controllers/SitemapController.php` and
`grep -c proxy-src app/Http/Controllers/SitemapController.php` returning `0` — before
redeploying. Do not assume the cleanup happened.

Set `TRUSTED_PROXIES` to the exact verified address(es). Never `*`. Then confirm
functionally: all four regional hosts must emit a canonical on their own host. A wrong
address fails closed — the canonical falls back to `review-bstg.beastierated.com` and the
region gate stops resolving — so the functional check is itself the confirmation, and no
diagnostic endpoint needs to survive.

Record the captured address here when it is known, so the next session does not rediscover
it, and does not quietly re-assume `127.0.0.1`.
