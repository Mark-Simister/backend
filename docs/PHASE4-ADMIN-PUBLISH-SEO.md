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

### FU-5 — 2026-07-11 — Authentication holes on the admin backend (found via a "pre-existing" test failure)

`tests/Feature/Auth/RegistrationTest.php` had failed for months and was repeatedly waved
past as "pre-existing Breeze scaffolding, unrelated to the feature." It was not. The
failing assertion (`assertAuthenticated`) was caused by missing seeded roles, and behind it
sat a live privilege-escalation hole. Three findings, all on directly reachable hosts.

**A1 — public registration granted `super_admin`.** `GET/POST /register` sat behind `guest`
middleware only. `RegisteredUserController::store()` created the account with
`role = 'super_admin'` and called `assignRole('super_admin')` on the web guard. `User` does
not implement `MustVerifyEmail` (the import is commented out at `app/Models/User.php:5`), so
`EnsureEmailIsVerified` — the `verified` middleware on `/dashboard` — passes anything that
is not a `MustVerifyEmail` instance. A registrant was authenticated, super-admin and on the
dashboard in one request, with no email confirmation.

It was exposed on **two** hosts: `stg.beastierated.com` and `review-bstg.beastierated.com`,
which serves the same `routes/auth.php` on its own public hostname.

*Fix:* routes, `RegisteredUserController` and the register view deleted. Admin accounts come
from `RolePermissionSeeder` + `DatabaseSeeder` or the console. `RegistrationTest` is
inverted — both routes must 404, no route may be named `register`, and the controller file
must not exist. `POST /api/register` is unchanged: that is the frontend member signup,
creating an `ApiUser` with role `user` on the `api` guard.

**A2 — OR-permissions and an unguarded block endpoint.** Spatie's `permission:a|b|c` means
*any* of them. `Route::resource('users', ...)->middleware('permission:user.view|user.create|
user.edit|user.delete')` therefore let a view-only sub-admin create, edit and **delete**
users. `users/{user}/toggle-block` carried no permission at all — its enclosing group is
`middleware(['auth'])` — so any authenticated user could block a super_admin. Because
`ApiUser::$table = 'users'`, a public frontend member's credentials authenticate at the
admin `/login`, so "any authenticated user" included the membership.

`'role' => 'required|string|exists:roles,name'` was not a control either: `super_admin`
exists on the `web` guard, so it validated, and `$user->update(['role' => ...])` wrote
`users.role = 'super_admin'` *before* `assignRole()` failed on the guard mismatch.

*Fix:* each verb carries its own permission; `toggle-block` requires `user.edit`; the role
rule is scoped to the `api` guard (these routes manage `ApiUser` records) and
`assertMayAssignRole()` authorizes before anything is written, refusing `super_admin` to
anyone who is not one.

**Still open — the same OR-permission pattern is on twelve other resources:** `channels`,
`categories`, `regions`, `character_tags`, `character_roles`, `characters`, `faqs`,
**`videos`**, `forms`, `highlight_tags`, `subscription_listing`, `reviews`. `videos` matters
most here: after the shared-database cutover, a sub-admin holding only `video.view` can
edit a video and thereby rewrite its public review page.

**A3 — privilege columns were mass-assignable.** `role` and `is_verified` were in
`User::$fillable`, and `ProfileController::update()` does
`$request->user()->fill($request->validated())`. `ProfileUpdateRequest::rules()` returns only
name and email today, so nothing was exploitable — but it was one careless line from
self-promotion.

*Fix:* removed from `$fillable`; `$guarded = ['role', 'is_verified', 'is_blocked']` declared
as well. **`$guarded` alone would have done nothing:** `Model::isFillable()` returns true the
moment a key appears in `$fillable` and never consults `$guarded`. Taking them out of
`$fillable` is the operative control. `ApiUser` (same table) still lists all three; its call
sites pass hardcoded values, but it deserves the same treatment.

**Decision — the renderer gets no auth surface.** `review-renderer` will drop
`routes/auth.php` entirely: no login, no registration, no password reset, no dashboard on a
public SEO renderer. Relying on `review_reader`'s lack of `INSERT` is not a control; it is a
500.

**Process note.** Three separate reviews concluded these tests were "pre-existing and
unrelated." A red test is not a triage category. Read what it is failing on.

### FU-6 — 2026-07-11 — `auth` treated as sufficient for /admin (whole-surface authorization failure)

Found while fixing the "pre-existing" RegistrationTest (FU-5): the admin surface had no
coherent authorization floor. This is the finding that generalises — the specifics below
are symptoms of one root cause.

**Root cause.** routes/web.php declares SEVEN separate `prefix('admin')` groups. Each was
expected to gate its own routes, and the pattern used was `auth` at the group level plus,
sometimes, a per-route/per-resource `permission:`. That is not an authorization model; it
is an authorization convention, and conventions rot. The result:

- **31 loose privileged routes** (declared with `Route::post/put/delete` rather than
  `Route::resource`) inherited only `auth`. Any web-authenticated user could invoke them.
  Because `ApiUser::$table = 'users'`, a PUBLIC frontend member authenticates at the admin
  `/login` and was therefore "any authenticated user" — a member could delete comments,
  reassign a video's Vimeo media, edit affiliate links, or block a super_admin.
- **One entire group had no middleware at all** — not even `auth`. `admin/top-categories`
  store/update/destroy were reachable **completely unauthenticated**.

**Note the audit trap, because it recurs.** The unauthenticated group was invisible to the
first audit, which searched for *authenticated* writes lacking a permission. A route with
*nothing* on it does not match a search for routes with the *wrong* thing. When hunting
missing controls, enumerate the whole surface; do not filter by the presence of the control
you expect to find broken.

**The 13 OR-permission resources** were a subtler variant. `permission:x.view|x.create|
x.edit|x.delete` reads like "these permissions guard this resource"; Spatie's pipe means
ANY of them, applied to every verb — so a view-only sub_admin could create, edit and
delete. (`users` fixed in 70423d7; the other 12 in the gate-A resources commit.)

**Fix, in two gates.**

- **Gate B (baseline).** `EnsureAdminAccess`, keyed on the request PATH and appended to the
  `web` group, requires every `admin/*` request to be an authenticated user with at least
  one web-guard permission. Path-keyed, not group-keyed, so it cannot be missed by a group
  declared elsewhere or added later; `AdminSurfaceCoverageTest` proves the coverage by
  construction. This alone closes the 31 loose routes and the unauthenticated group to
  everyone but genuine admins.
- **Gate A (specific).** Each verb of the 13 resources now requires its own permission
  (four `Route::resource()->only()` registrations, preserving names/params). Each of the 31
  loose routes now carries the permission matching what it changes: payload-feeding →
  `video.edit`, bloopers → `character.edit`, admin chrome → `settings.manage`. B does NOT
  make A redundant: a sub_admin with `faq.view` passes B and would still reach everything
  without A.

**`settings.manage` is a deliberate bundle.** Six admin-chrome routes (themes, global
colours, top-categories, site images, tag creation, product-message deletion) had no
natural permission family. They were super-admin-only ONLY by accident — see the landmine
below. `settings.manage` makes that a decision: seeded, granted to super_admin only, and
commented in the seeder as bundling unrelated concerns that must be split before it is ever
granted to a sub_admin.

**LANDMINE — nine permission families are referenced by live middleware and seeded
nowhere:** `faq.*`, `region.*`, `character_tag.*`, `character_role.*`, `highlight_tag.*`,
`subscription_list.*`, `rating_review.*`, `form.*`, `subscription.*`. They exist in no
seeder. The routes that reference them are reachable today ONLY because of the super-admin
Gate::before bypass (below), which short-circuits the check before the missing permission is
consulted. **If anyone ever narrows or removes Gate::before, those nine resources become
unreachable by everyone, super-admins included, with no obvious cause** — the middleware
will deny a permission that cannot be granted because it does not exist. Seed them (or
delete the references) before touching Gate::before.

**Gate::before is load-bearing and undecided.** `AppServiceProvider` contains:

    Gate::before(fn ($user, $ability) => $user->hasRole('super_admin') ? true : null);

Every per-verb split and every loose-route permission added here is, BY DESIGN, entirely
untested against a super_admin — because a super_admin never reaches the permission check
at all. The test suite asserts these controls only for sub_admins/members and says so in
each test. Anyone reading the splits later must understand: their correctness for a
super_admin depends wholly on this one line, and this one line is the reason the nine
unseeded families appear to work. It has never been deliberately reviewed as a security
control. It should be.

**ApiUser.** Shares the `users` table; its three privilege columns (`role`, `is_verified`,
`is_blocked`) were mass-assignable exactly as User's were (FU-5 A3). Hardened the same way.

**Also fixed.** `admin/vimeo/assign` used `can:video.update` — an UNSEEDED ability, so it
was super-admin-only by the same accident; normalised to `permission:video.edit`.

### FU-6 addendum — 2026-07-11 — "fails closed" was wrong about the renderer's admin routes

We said twice that after Phase B the renderer's admin routes would "fail closed" because
it runs as `review_reader` with no INSERT. That is wrong, and it is the same mistake as
`$guarded` reading like protection while doing nothing: a control that is really the
absence of one, disguised by something adjacent.

A `SELECT`-only grant does not make an admin write route safe. It makes it **500** — the
write is attempted and the database rejects it. A 500 is not an authorization boundary; it
is an error page, and it still ran the controller, the model binding, and any read the
handler did first. And per the column-scoped grant note above, a future renderer feature
may legitimately require widening the grant — at which point "it can't write" quietly stops
being true, with nothing to signal that a route which was "safe because it 500s" is now a
route that succeeds.

Separately, "just delete `routes/auth.php`" does not disarm the admin routes either: with
no `login` route, the `auth` middleware redirects to `route('login')` and throws
`RouteNotFoundException`. Broken is not absent.

**Resolution: the review-renderer branch declares no admin surface at all.** Its
`routes/web.php` contains only `/review/{id}`, `/review/{slug}`, `/sitemap.xml`,
`/robots.txt`; `routes/api.php` is empty; `routes/auth.php` is deleted; `bootstrap/app.php`
carries no admin gate because there is nothing to gate. The routes are gone, not unreachable
— the only state in which "the renderer has no admin surface" is actually true rather than
true-until-the-grant-changes. Built from 09b63d3 as commit c71692e; held locally, lands
with runbook v3.

### FU-3 caveat — 2026-07-11 — the extractor selects its workbook by modification time

`extract_published_payloads.py` reads columns BY HEADER NAME (row 1), not by fixed cell —
so `publish_status` is whatever column is headed `publish_status`, and promoting a row means
editing that column's cell for that row. In the current newest workbook that is cell **Y2**
for the Vecomfy row (`publish_status` = column Y, review_slug = column K, Vecomfy = row 2).
Verify the header, not the letter, since the mapping is by name.

The trap: the script picks the workbook with the NEWEST modification time —
`sorted(glob(...), key=os.path.getmtime, reverse=True)[0]`. There are currently TWO
`BeastieRated_Production_OS*.xlsx` files in public/product_review/. Opening and saving the
WRONG one silently makes it newest, so the extractor switches source with no error and no
diff — the promotion appears not to have taken. Always confirm the `sheet file: …` line the
script prints names the workbook you actually edited, and consider removing stale workbooks
so only one exists.

### review-renderer surface — 2026-07-11 — it is SEVEN routes, not four

The renderer serves four *intended* routes — /review/{id}, /review/{slug}, /sitemap.xml,
/robots.txt — plus /up (health) and two framework/package auto-routes: sanctum/csrf-cookie
(Laravel Sanctum) and storage/{path} (storage:link). `php artisan route:list` on
review-renderer @ c71692e shows SEVEN GET routes, zero writes, zero admin/auth. The two
package routes are inert read-only GETs and are left in place deliberately: removing Sanctum
from the branch about to serve four regional hosts trades a real boot-time dependency risk
for a cosmetic count. Recorded so nobody re-derives the four-vs-seven discrepancy later and
mistakes it for scope creep.

### FU-4 correction — 2026-07-11 — the captured proxy address MUST be private, or STOP

The capture procedure curls a REGIONAL host (au.fstg.beastierated.com) but reads the
REVIEW-BSTG ORIGIN vhost's access log. That is correct only because the regional host
proxies /robots.txt to review-bstg — the proxied request lands in the review-bstg log with
the PROXY as REMOTE_ADDR, which is what we want. But it is a trap: if you tail the
regional host's OWN access log instead, the first field is the external client (your
laptop's public IP), and setting `TRUSTED_PROXIES` to that would trust X-Forwarded-Host
from that address — trusting the open internet in the one field whose entire purpose is to
prevent exactly that.

Two hard rules, added to the procedure:

1. Before reading a first field, confirm WHICH vhost the log belongs to. It must be the
   review-bstg / origin vhost (the one Laravel runs behind), NOT the regional edge vhost.
   `grep ServerName` the config that owns the log file and verify it is
   review-bstg.beastierated.com.
2. The captured address MUST be private — loopback (127.0.0.1, ::1) or RFC1918
   (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16). Anything else — any public/routable IP —
   means the wrong log was read (or the topology is not what we assume). HARD STOP. Do not
   set TRUSTED_PROXIES to a public address under any circumstances.

### Process note — 2026-07-11 — the empirical check keeps beating the careful read

Three or four times now, the thing that settled a question was exercising the running
system, not reading the source more carefully:

  - the `robots.txt` regression was a real request from a separate network, not a local
    file that looked right;
  - the `Host` header vs getHost() behaviour was a full absolute-URL request, not the
    route definition;
  - `getAllPermissions()` guard-filtering for a member was a rendered check, not Spatie's
    docs;
  - the profile form's "editable email input" — reported as a live UX defect from a grep
    that matched the <input> lines but could not see the {{-- --}} wrapping them — was
    settled by rendering /profile as an authenticated user and asserting on the HTML. The
    field was already commented out; there was never a live defect. The fix (read-only
    display + a mutation-tested no-name="email" assertion) stands anyway, on a different
    footing than first stated: not "fix a shipped bug" but "make an incidentally-correct
    Blade load-bearing", the same category as $guarded and SESSION_DRIVER=array reading
    like controls while enforcing nothing.

When a claim is about what the system DOES, assert against the running system. A grep and a
careful read both answer the question asked; only the running system answers the question
meant.

### FU-4 correction #2 — 2026-07-11 — the captured line must be provably MINE

A private address is necessary, not sufficient. `tail`-ing the log and reading "the newest
line" can capture a stale line from someone else's earlier request, or a line from a
different private-facing log — yielding a plausible, private, WRONG address. TRUSTED_PROXIES
then points at something real and incorrect, and the failure is silent: regions stop
resolving, canonical falls back to review-bstg, and it reads as a rollback fault, not a
config error.

Fix: make the request uniquely identifiable and grep for that marker, rather than tailing.

  - Step 3 (from your laptop): request a unique path AND a unique User-Agent —
      MARK=fu4-<pick-something-unique>
      curl -s -o /dev/null -A "beastie-fu4/$MARK" "https://au.fstg.beastierated.com/robots.txt?probe=$MARK"
    Two markers on purpose. A unique query string usually forces an edge cache MISS (query
    is part of the default cache key), so the request actually reaches the origin; the
    unique User-Agent is a fallback that the combined log format records (%{User-Agent}i)
    if the query is stripped somewhere.

  - Step 4 (on the box): grep the ORIGIN log for the marker, do NOT tail:
      sudo grep "$MARK" /var/log/apache2/<review-bstg-ORIGIN-access-log> | tail -1
    The first field of THAT line is the address. **If grep returns nothing, that is the
    answer, not a retry prompt:** the request never reached this origin log — wrong log, or
    it was served from an edge cache — so STOP and re-confirm which log belongs to the
    review-bstg ServerName.

Then apply BOTH gates: the address must be private (loopback/RFC1918) AND it must be the
grep-matched line from your own marked request. Public address, or empty grep, = hard stop.

Application-side safety confirmed from the code (server-side proxy/edge behaviour is not
verifiable from the repo, hence the dual marker):
  - SitemapController::robots() depends only on config + getSchemeAndHttpHost(), never on
    the query string, so ?probe cannot change the robots body;
  - robots is not wrapped in Cache (no application cache to disturb);
  - /robots.txt is not behind EnsurePublicReviewRendering, so the probe reaches the
    controller regardless of the rendering flag.

### FU-4 correction #3 — 2026-07-11 — read the whole matched line, not just the first field

The first field (%h) and the marker's location (%r request path, %{User-Agent}i) are logged
independently. A private-looking %h with the marker appearing somewhere unexpected means
something between edge and origin rewrote the request — at which point %h no longer
describes the hop the marked request actually came from, and is not trustworthy even though
it is private. So the check is: the marker MUST appear in the request path
(/robots.txt?probe=…) or the User-Agent field of the matched line, AND the first field must
be private. Read the whole line, confirm the marker is where it belongs, THEN take the
address. Marker anywhere unexpected = hard stop, same as public or empty-grep.

### FU-1 addendum — 2026-07-11 — the theme migrations do NOT reproduce the live tables

Verified against bstd_staging (SHOW CREATE TABLE). Both `themes` and `user_themes` exist,
are populated (AUTO_INCREMENT 8 and 21), and neither migration is in the `migrations` ledger.
P-7's Schema::hasTable() guard therefore correctly no-ops them, and A3.5 records them as run
without altering the live tables — the rollout is NOT blocked by this.

But "recorded as run" must be read narrowly: it means "this migration will never execute
against this DB", NOT "this migration produced this schema". It did not. The committed
migration diverges from the live tables in three ways:

  - colour columns: migration `->string()` = varchar(255); live themes = varchar(50),
    live user_themes = varchar(20). Matches neither.
  - user_themes.user_id / theme_id: migration nullable()->index(); live NOT NULL with no
    index. So recording-as-run over-claims two indexes and the wrong nullability. (Neither
    the migration nor the live table has an FK or a unique(user_id, theme_id).)
  - collation: migration inherits one DB default so both tables are consistent; live is
    inconsistent — themes utf8mb4_0900_ai_ci, user_themes utf8mb4_general_ci with per-column
    general_ci on the colour columns. Evidence these tables were hand-built at different
    times, not migration-produced.

Consequence: a fresh `migrate` (tests, a new environment) yields varchar(255) / nullable+
indexed user_id — a different schema from staging, permanently and invisibly, until someone
diffs. Not a live fault: the only cross-table links are integer id joins
(UserTheme.theme_id -> themes.id, UserTheme.user_id -> users.id), where collation is
irrelevant, and no code compares the colour columns across the two tables. So the collation
mismatch breaks nothing today.

Follow-up (not this rollout): decide which schema is canonical — align the migration to the
live tables (varchar 50/20, NOT NULL, no index, matching collations) or migrate the live
tables to the migration — so fresh installs and staging converge. Until then, treat the
theme tables as hand-managed, not migration-managed.
