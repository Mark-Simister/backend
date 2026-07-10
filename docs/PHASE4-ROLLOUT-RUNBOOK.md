# Phase 4 SEO Rollout — Final Runbook

Move the admin backend and the public review renderer onto the shared staging MySQL
(`bstd_staging`), the renderer reading it through a `SELECT`-only account. Detail and
reasoning for every numbered finding (FU-1…FU-7) live in `PHASE4-ADMIN-PUBLISH-SEO.md`.

**Identities.** `beastierated` = app deploy (git, composer, artisan, `.env`). `ubuntu` =
server admin (`sudo mysql`, systemd, backups, `/etc/hosts`). `beastierated` never gains
privilege. Steps tagged **[deploy]** / **[admin]**.

**Hosts (one EC2 instance, Elastic IP `13.238.27.223`):** admin
`/home/beastierated/backend` @ `staging`, `stg.beastierated.com`, DB `bstd_staging`;
renderer `/home/beastierated/backend-review` @ `carousel-data`→`review-renderer`,
`review-bstg.beastierated.com`, DB `beastie_review`→`bstd_staging`. Web tier is **mod_php**
(no FPM). PHP **8.3.6** CLI + web.

## Pinned refs

```
RENDERER_TARGET = fd44a8a     # review-renderer: 09b63d3 + curated renderer + lock fix. FROZEN.
                              # LOCAL ONLY until the Gate-0.2 preflight pushes it.
ADMIN_TARGET    = <carousel-data tip at freeze>   # MUST contain 37bc517 (the lock fix).
                                                  # Record the exact hash into SNAP-0 at freeze.
ADMIN_BASE      = 5694a72     # staging today
RENDERER_BASE   = 09b63d3     # review-bstg today
LOCKFIX         = 37bc517     # composer.lock clock 3.5.0 / jwt 4.3.0 (must be an ancestor of both targets)
```

**Ancestry assertions (do not trust branch names):**
- A2: `git merge-base --is-ancestor 37bc517 $ADMIN_TARGET` → must succeed.
- B4: `git merge-base --is-ancestor fd44a8a HEAD` after checkout → must succeed; and `HEAD == fd44a8a`.

## Gate 0 — before Phase A

1. **Gate 0.1 (yours):** promote the Vecomfy row to `published` in cell **`Y2`** of the
   workbook the extractor selects by newest mtime (FU-3 — confirm the `sheet file:` line and
   the `publish_status` header, and beware the two-workbook trap); re-run
   `extract_published_payloads.py`; commit the regenerated JSON.
2. **Gate 0.2 (Phase-B preflight, gates B4):** when Phase B is authorised, before B1:
   `git push origin review-renderer` then `git ls-remote --heads origin review-renderer | grep -q fd44a8a` — no-go if absent.
3. `APP_DEBUG=false` on both hosts (already set) — confirm it held.

## SNAP-0 — baseline snapshot (read-only, [admin]) — records the freeze

Run `snapshot.sh pre-a1`. Records hostname/user/UTC, both repos' branch+commit+`git status
--short`, `.env` (no secrets), compiled caches, `migrate:status`, jobs/failed_jobs,
`after_commit`, worker state, PHP versions, LogFormat. **Additionally at freeze:**
- record the exact frozen `ADMIN_TARGET` hash;
- **EIP CHECK-A (FU-4) — exact match, hard-stop on empty. Runs at SNAP-0 AND SNAP-1.**
  Pre-cutover the renderer `.env` has no `TRUSTED_PROXIES` yet, so this asserts the instance
  public IP is still the decided EIP (catches an EIP change before cutover). NOT a substring
  grep — exact equality, empty `META_IP` is a STOP.
  ```bash
  TOKEN=$(curl -s -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 60")
  META_IP=$(curl -s -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/public-ipv4)
  [ -n "$META_IP" ] || { echo "STOP: IMDS returned no public-ipv4"; exit 1; }
  [ "$META_IP" = "13.238.27.223" ] || { echo "STOP: public-ipv4 [$META_IP] != decided EIP 13.238.27.223"; exit 1; }
  echo "OK: instance public-ipv4 == 13.238.27.223"
  ```

**Clean-tree baseline (FU CI/mode finding):** both checkouts must be byte-clean.
- admin: the 11 `storage/*` + `bootstrap/cache/*` `.gitignore` files show **pure mode churn**
  (`100644`→`100755` from CI's old `chmod -R 775`, zero content lines). **Remedy by
  NORMALISING, not by disabling mode tracking** — `core.fileMode=false` would hide every
  future legitimate exec-bit change on that checkout, permanently, to paper over churn the
  corrected `stg.yml` no longer produces (the same suppress-the-signal trade refused
  elsewhere here). Sequence:
    1. `chmod 644` the eleven `.gitignore` files
    2. `git status --porcelain` → expect **clean**
    3. run the corrected `stg.yml` step: `find storage bootstrap/cache -type f -exec chmod 664 {} +`
    4. `git status --porcelain` → **if still clean, `core.fileMode` stays at its default (true)** and this item is done.
  `664` sets group-write, not exec, so git mode stays `100644` — git's `fileMode` compares
  only the exec bit (demonstrated: `chmod 664`/`644` leave the tree clean; only `775`/`755`
  set the exec bit that dirties it). **Fall back to `core.fileMode=false` ONLY if step 4
  dirties the tree — and if it does, that is itself a finding to explain before disabling.**
  Delete the four Aug-2025 zero-byte debris files (`foun`, `found`, `php`, `satisfiable`)
  before WT preservation.
- renderer: `composer.lock` is now committed (`fd44a8a`); working tree must be clean
  (`out.html` removed).
Any other dirty path is a **no-go**.

## A1 — pre-rollout backups (mandatory, blocking) [admin]

`sudo mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4`
for **both** `bstd_staging` and `beastie_review`. Each: exit 0, `chmod 600`, size floor,
`Dump completed` trailer, `CREATE TABLE`/`INSERT INTO videos` (or payload) counts, and a
**restore test** into a temp DB comparing table + payload counts and the Vecomfy row's
slug/status/source. `.env` backups under `umask 077`.

## Working-tree preservation (before A2 and before B4) [deploy]

Preserve tracked (`git diff --binary HEAD`, staged+unstaged) and untracked
(`git ls-files --others --exclude-standard` → tar), record `git stash` **commit SHA** (not
ref), `git stash apply` (never `pop`), verify `git status --porcelain` byte-identical to the
saved baseline. Retain the stash until the rollback window closes. `git reset --hard` is
forbidden until preservation prints OK. (The renderer's ~140 untracked media inflate the tar
— bloat, not risk; `reset --hard` never touches untracked files.)

## Phase A½ — narrow visibility to `published` (live, `beastie_review`, reversible) [deploy]

Promote the Vecomfy row in `beastie_review` (bump `updated_at`), set
`PUBLIC_REVIEW_STATUSES=published`, `config:clear`, verify 200×4. Promote **then** narrow.

## Phase A — admin backend

| | Who | Task | Rollback |
|---|---|---|---|
| WT | [deploy] | preserve admin tree | — |
| A2 | [deploy] | `git fetch`; `git merge-base --is-ancestor 37bc517 $ADMIN_TARGET`; `git merge --ff-only $ADMIN_TARGET`; assert `HEAD==$ADMIN_TARGET` | `reset --hard 5694a72` + WT restore |
| A3-PRE | [deploy] | **hard gate (FU-7):** `php -m` shows `sodium hash json mbstring openssl` all loaded; `composer install --dry-run` succeeds on 8.3.6 | — |
| A3 | [deploy] | **MUST RUN / MUST SUCCEED:** `composer install` — installs clock 3.5.0 / jwt 4.3.0, repairs the stale pre-8.3 vendor | reinstall prior vendor from backup |
| A3.5 | [deploy] | reconcile the 3 pending migrations, own batch (P-7 guards no-op the theme pair). **Recording the theme migrations as run does NOT mean they built the live tables — they diverge (FU-1 addendum): collation split (`utf8mb4_0900_ai_ci` vs `utf8mb4_general_ci`); `user_themes` has NO FK and NO unique on `(user_id, theme_id)`; colour columns are `varchar(20)`/`varchar(50)` vs the migration's `varchar(255)`. Treat the theme tables as hand-managed, not migration-managed.** | `migrate:rollback --step=1` |
| A4 | [deploy] | the six `--path` migrations, own batch, last | `migrate:rollback --step=1` *(pre-A8 only)* |
| A4.5 | [admin] | post-migration backup + restore test (before A8) | — |
| A5 | [deploy] | admin `.env`: `TRUSTED_PROXIES=` (empty), `PUBLIC_REVIEW_RENDERING_ENABLED=false`, `PUBLIC_REVIEW_INDEXABLE=false` | restore `.env` |
| A6 | [deploy] | `config:clear && route:clear` | — |
| A7 | [admin] | install + enable `beastie-queue.service` (one process; `command -v php` as beastierated; `After=` the real mysql unit) | `systemctl disable --now` |
| A8 | [deploy] | first admin publish + deterministic worker proof | ⚠ boundary crossed |

**A3 is now load-bearing (FU-7):** `composer.lock` differs from `5694a72`, so `composer
install` runs and both installs clock 3.5.0/jwt 4.3.0 and repairs the admin host's stale
pre-8.3 vendor. Its A3-PRE gate is hard: a missing `ext-sodium` fails the install mid-Phase-A.

**A4** uses the exact six `--path` migrations; never bare `migrate` (FU-1: `themes`/
`user_themes` exist but are Pending; bare `migrate` half-applies). Pre-flight:
`published_review_payloads`, `site_images`, `videos.review_slug` all absent.

**A8 (deterministic):** stop worker → 0 processes → dispatch → `jobs==1` → start worker →
drains → `payload.updated_at` advanced → **`videos.seo_publish_status='published'`** (the
health signal, not `failed_jobs`). **A8b** observer loop: one job, one payload update, no
cascade. A8 assigns the first immutable slug — **after it, `migrate:rollback` is prohibited;
recovery is A4.5, else A1.**

## SNAP-1 — go-time snapshot [admin]

`snapshot.sh go-time`. Assert: admin `after_commit=true`, exactly one `queue:work` process,
`PUBLIC_REVIEW_RENDERING_ENABLED=false` + `TRUSTED_PROXIES` empty on admin, A8 disposable
video `seo_publish_status=published`, renderer still `09b63d3`/`beastie_review`.
**Run EIP CHECK-A again here** (the exact-match/empty-guard block from SNAP-0) — SNAP-1 is the
go-time snapshot and is the one that catches an EIP change between baseline and cutover.

## Phase B — cutover

| | Who | Task | Rollback |
|---|---|---|---|
| B0 | [deploy] | Gate-0.2 preflight: `git push origin review-renderer`; `git ls-remote … grep fd44a8a` | — |
| B1 | [admin] | create `review_reader`, table/column-scoped grants via `umask 077` + `mktemp /dev/shm` + `trap` | `DROP USER` |
| B2 | [admin] | negative test suite: `SELECT *` on videos → 1143; `SELECT * users` → 1142; write/create → 1142 | — |
| B3 | [deploy] | seed the Vecomfy payload into `bstd_staging` (on admin host) | `DELETE … WHERE source='pipeline'` |
| WT | [deploy] | preserve renderer tree | — |
| B4 | [deploy] | `git fetch`; `git checkout fd44a8a`; `git merge-base --is-ancestor fd44a8a HEAD`; assert `HEAD==fd44a8a`; **`route:list` = 7 GET routes, zero admin/auth/write** | `git checkout 09b63d3` + WT restore |
| B4-PRE | [deploy] | A3-PRE extension gate + `composer install` on the renderer (PHP ≥ 8.3 floor, FU-7) | — |
| B5 | [deploy] | renderer `.env`, **one edit**: `DB_DATABASE=bstd_staging`, `DB_USERNAME=review_reader`, `DB_PASSWORD=…`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=null`, **`TRUSTED_PROXIES=13.238.27.223`**, `PUBLIC_REVIEW_RENDERING_ENABLED=true`, `PUBLIC_REVIEW_INDEXABLE=false`, `PUBLIC_REVIEW_STATUSES=published` | restore `.env` |
| B6 | [deploy] | `config:clear && route:clear` | — |
| B6.5 | [admin] | **EIP CHECK-B (FU-4):** now that B5 has set `TRUSTED_PROXIES`, assert the configured value EXACTLY equals the live peer — exact match, empty `META_IP` STOPs (see FU-4 for the block). STOP on any mismatch or trailing extra proxy. | fix `.env`, re-run |
| B7 | [deploy] | admin region-gating end-to-end (10 checks) + **the EIP guard**: all four regions 200 (a stale/forged `TRUSTED_PROXIES` 404s them) | — |

**B5 `TRUSTED_PROXIES=13.238.27.223`** — the verified Elastic-IP peer (FU-4). Not loopback,
not `*`. Credentials + drivers change in one edit (a `SELECT`-only account with
`SESSION_DRIVER=database` 500s every request). `QUEUE_CONNECTION=null` (not `sync`, which
executes the job body and writes). `chmod 600 .env`.

**B7 is the load-bearing EIP guard:** if `TRUSTED_PROXIES` doesn't match the real peer, all
four regional `/review/…` 404 and the retirement gate fails — fail-closed, loud.

**Retirement gate for `beastie_review` (both required):** Vecomfy pipeline row 200 on
AU/US/UK/CA, and the disposable admin row correctly region-restricted (B7). Only then retire
the PoC DB.

## Go / No-Go

- [ ] Gate 0.1 — Vecomfy promoted (Y2, correct workbook, `sheet file:` confirmed); JSON re-extracted + committed
- [ ] Gate 0.2 preflight — `origin` advertises `fd44a8a`
- [ ] `ADMIN_TARGET` frozen and recorded in SNAP-0; `git merge-base --is-ancestor 37bc517 $ADMIN_TARGET` succeeds
- [ ] Prereq test suites green on the merged tree (Proxy/Rendering/AdminBaseline/AdminSurfaceCoverage/AdminResourceVerb/AdminLooseRoute/UserMassAssignment/ReviewSeoAsync/Profile)
- [ ] SNAP-0 clean-tree: admin modes NORMALISED (chmod 644 the 11 `.gitignore`; corrected `find … -exec chmod 664` keeps it clean; `core.fileMode` stays default — NOT disabled); debris deleted; both checkouts byte-clean
- [ ] A1 both DBs dumped + restore-tested
- [ ] **A3-PRE hard gate:** `sodium hash json mbstring openssl` all loaded on admin CLI; `composer install --dry-run` succeeds on 8.3.6
- [ ] A4.5 taken + restore-tested **before A8**
- [ ] A8 deterministic: `jobs 1→0`, `payload.updated_at` advanced, `seo_publish_status='published'`; A8b no cascade
- [ ] Acknowledged: after A8, `migrate:rollback` prohibited
- [ ] SNAP-1: `after_commit=true`, one worker, admin rendering disabled + no trusted proxies; **EIP CHECK-A passes** (exact match, empty→STOP; not a substring grep)
- [ ] B6.5 EIP CHECK-B: configured `TRUSTED_PROXIES` exactly equals the live peer (STOPs on extra proxy / wrong value / empty META_IP)
- [ ] B4: `HEAD==fd44a8a`, ancestry holds, `route:list` = 7 GET routes zero admin/auth
- [ ] B2: `review_reader` cannot `SELECT *` videos / read users / write / create
- [ ] B3 before B5; seeder re-run does not demote the live page
- [ ] B5: credentials+drivers in one edit; `TRUSTED_PROXIES=13.238.27.223`; `.env` 0600
- [ ] B7: four regions 200; admin row region-restricted; `laravel.log` clean
- [ ] Retirement gate met before retiring `beastie_review`

## Carried-forward (documented, not fixed here)

FU-1 (bare `migrate` broken on admin; theme migrations diverge from live tables — hand-managed,
not migration-managed), FU-2 (`APP_ENV=local` disarms `migrate:fresh`; set `production` only
after A5 sets `PUBLIC_REVIEW_INDEXABLE=false`, then re-verify robots), FU-3 (`published` is
permanent policy; WF4 needs its own promotion; workbook-mtime trap), FU-4 (EIP dependency +
IMDS assertion + recommended drift alert), FU-6 landmine (nine unseeded permission families
inert only via `Gate::before` — seed before touching it), FU-7 (PHP ≥ 8.3 floor; lock is
box-produced, not locally regenerable; mod_php SAPI split relevant only if `JWT_ALGO` becomes
EdDSA). Profile Blade email is read-only + test-enforced.
