# Phase 3 — Public Review Routing & Canonical Host

Goal: public review pages at `/review/{slug}` must be served by **Laravel**
(server-rendered crawlable HTML), not by the React SPA host (which returns the
`<div id="root">` shell). This is **staging routing correctness** — the hosts
below (`*.fstg.beastierated.com`) are staging; production hostnames are TBD, and
the app derives the host from the request at runtime, so no final prod name is
baked in.

## CONFIRMED from repo (2026-07-09): single Apache server, two hostnames, no CDN

- **Single server**: both `stg.yml` workflows SSH to the same `SERVER_IP`/`SERVER_USER`
  (backend → `/home/beastierated/backend/`, frontend → `bash /home/beastierated/deployment/deploy.sh`).
- **Two hostnames on that one server** (separate Apache vhosts):
  - `*.fstg.beastierated.com` (au/us/uk/ca) = **SPA / frontend** (docroot = the built
    SPA, `frontend/.htaccess` fallback).
  - `bstg.beastierated.com` = **Laravel / backend** — the SPA calls the API at the
    ABSOLUTE `https://bstg.beastierated.com/api/` (`frontend/src/api/axiosConfig.js`),
    i.e. `/api` is a **separate origin**, NOT path-routed on the fstg host.
- **No CDN / Cloudflare / CloudFront / nginx** config anywhere in either repo.
- **`fstg`/`bstg` are STAGING names**; the app derives host from the request, so no
  final prod name is baked in.

**Implication for `/review/*`:** because there is no existing path-proxy from
`fstg → Laravel` (the SPA reaches the backend by calling `bstg` directly), routing
`/review/*` on the public regional hosts to Laravel is **new server config**, added
in the `*.fstg.beastierated.com` Apache vhost. Two ways, single-server:

1. **Filesystem Alias → backend/public (preferred, no Host risk).** In the fstg
   vhost, `Alias /review /home/beastierated/backend/public` won't work directly
   (Laravel needs its front controller); instead mount Laravel's public dir for
   those paths with its own `<Directory>` + rewrite, OR run Laravel as its own
   vhost and `RewriteRule ^/review [P]` / `ProxyPass` to it. Apache serves the
   request in-process → `Request::getHost()` is the real `au.fstg…` natively.
2. **Reverse proxy fstg → bstg (Host risk — needs `ProxyPreserveHost On`).** If you
   `ProxyPass /review/ http://bstg.beastierated.com/review/`, Laravel will see
   `bstg…` as the host UNLESS `ProxyPreserveHost On` (or forwarded `X-Forwarded-Host`)
   is set — then every canonical/OG/JSON-LD URL collapses to `bstg`. TrustProxies is
   already configured to honour `X-Forwarded-Host`.

## Architecture (the vhost that needs the rule is on the SERVER, not in the repo)

- **React SPA** is Apache-served: `frontend/.htaccess` is the classic SPA
  fallback — any path that isn't a real file/dir rewrites to `/index.html`.
  Deployed per region: `au|us|uk|ca.fstg.beastierated.com` (map in
  `frontend/index.html`: AU→au, US→us, GB/UK→uk, CA→ca, base `fstg.beastierated.com`).
  **Consequence:** on the SPA host, `/review/*` currently returns the React shell.
- **Laravel** is a separate app (standard `backend/public/.htaccess` front
  controller). Owns `/api/*` today; now also `/review/*`, `/sitemap.xml`, `/robots.txt`.
- **CDN / reverse proxy in front (CloudFront / Cloudflare / nginx / ALB): UNKNOWN
  from the repo — this is the one thing to confirm.** Whatever owns the
  `*.fstg.beastierated.com` DNS + TLS is where the path routing rule below goes.

## Required routing rule (apply at whichever layer owns the public host)

```
/review/*     -> Laravel origin
/sitemap.xml  -> Laravel origin
/robots.txt   -> Laravel origin   (Laravel now generates it, env-aware)
/api/*        -> Laravel origin   (unchanged)
/assets/*     -> React/static assets (unchanged)
everything else -> React SPA (index.html fallback, unchanged)
```

Ordering matters: the SEO/Laravel paths must be matched **before** the SPA
catch-all, or the shell wins.

### CloudFront (if that's the front)
Add cache behaviors (higher precedence than `Default (*)`), each with the Laravel
origin: `/review/*`, `/sitemap.xml`, `/robots.txt`, `/api/*`. Forward the `Host`
header (or set Origin `X-Forwarded-Host`) so Laravel sees the regional host.

### Cloudflare
Origin Rules / Workers: route `/review/*`, `/sitemap.xml`, `/robots.txt`, `/api/*`
to the Laravel origin; everything else to the SPA origin. Preserve `Host`.

### nginx (if the SPA host is nginx)
```nginx
location ^~ /review/    { proxy_pass http://LARAVEL_ORIGIN; include proxy_params; }
location = /sitemap.xml { proxy_pass http://LARAVEL_ORIGIN; include proxy_params; }
location = /robots.txt  { proxy_pass http://LARAVEL_ORIGIN; include proxy_params; }
location ^~ /api/       { proxy_pass http://LARAVEL_ORIGIN; include proxy_params; }
location / {
    try_files $uri /index.html;   # SPA fallback
}
# proxy_params must forward Host + X-Forwarded-Proto/Host so canonical is correct.
```

### Apache SPA host (mod_proxy, before the SPA rewrite in frontend/.htaccess)
```apache
# --- send SEO/Laravel paths to Laravel BEFORE the SPA fallback ---
ProxyPreserveHost On
ProxyPass        /review/    http://LARAVEL_ORIGIN/review/
ProxyPassReverse /review/    http://LARAVEL_ORIGIN/review/
ProxyPass        /sitemap.xml http://LARAVEL_ORIGIN/sitemap.xml
ProxyPass        /robots.txt  http://LARAVEL_ORIGIN/robots.txt
ProxyPass        /api/        http://LARAVEL_ORIGIN/api/
# (existing SPA fallback rewrite stays below / in .htaccess)
```
`ProxyPreserveHost On` (or `X-Forwarded-Host`) is required so Laravel builds the
canonical from the regional host.

## App-side pieces already implemented

- **TrustProxies** (`bootstrap/app.php`) — trusts the proxy's `X-Forwarded-*` so
  `Request::getHost()/getScheme()` reflect the real public regional host. Tighten
  `at: '*'` to the proxy IP range in prod if the app is directly reachable.
- **Per-region canonical / OG / JSON-LD** (`PublicReviewPageController`) — computed
  from the request host at request time; the pre-built JSON-LD's internal host is
  rewritten to the serving host; the stored `canonical_url` host is fallback-only
  (non-regional requests). Rendered-HTML cache key includes the host.
- **robots.txt + sitemap.xml** (`SitemapController`) — env-aware
  (`PUBLIC_REVIEW_INDEXABLE`): staging = `Disallow: /`, production = `Allow` +
  `Sitemap: {host}/sitemap.xml`. Sitemap `<loc>` uses the request host.

## Production env (per host)
```
PUBLIC_REVIEW_STATUSES=published      # staging keeps ready_for_review,published
PUBLIC_REVIEW_INDEXABLE=true          # staging: false (noindex + Disallow)
PUBLIC_REVIEW_CACHE_TTL=3600          # local: 0
CACHE_STORE=redis|file                # so the TTL actually persists
```

## Validation (run against the ACTUAL public host after routing is applied)
```powershell
curl.exe -sL https://au.fstg.beastierated.com/review/vecomfy-fleece-lining-extra-warm-dog-hoodie-in-winter-small-dog-jacket-puppy-coats-with-hooded-red-xs-b07hcb1jps -o public-host-review.html
Select-String -Path public-host-review.html -Pattern "BeastieScore","application/ld+json","<h1","Affiliate","Product","Vecomfy"
# Fail signal: the page is ONLY <div id="root"></div> (SPA shell) → /review/* not reaching Laravel.
curl.exe -I https://au.fstg.beastierated.com/sitemap.xml   # expect 200 + application/xml
curl.exe -sL https://au.fstg.beastierated.com/robots.txt   # staging: Disallow: / ; prod: Allow + Sitemap
```
Also confirm the canonical in the returned HTML is `https://au.fstg.beastierated.com/review/{slug}` (the host it was fetched on), not `us.…`.
