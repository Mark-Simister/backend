<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public review visibility
    |--------------------------------------------------------------------------
    | publish_status values that make a published_review_payload publicly
    | visible at /review/{slug} (and eligible for the sitemap).
    |
    | ONLY 'published' is publicly renderable. 'draft' and 'ready_for_review' are
    | workflow states, not visibility states:
    |
    |     draft → ready_for_review → published → public website
    |
    | A pipeline row must be promoted to 'published' before it appears publicly,
    | and that promotion originates in the Published_Review_Payloads spreadsheet:
    | the seeder's updateOrCreate() overwrites publish_status from the JSON, so a
    | DB-only UPDATE is reverted by the next import. See FU-3 in
    | docs/PHASE4-ADMIN-PUBLISH-SEO.md.
    */
    'public_statuses' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PUBLIC_REVIEW_STATUSES', 'published'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Public review rendering (R2)
    |--------------------------------------------------------------------------
    | Whether THIS deployment serves public review pages at all. Defaults to
    | false, so an unconfigured host — notably the directly-reachable admin
    | backend — never becomes a second, indexable renderer of the same content.
    |
    | Gates /review/{id}, /review/{slug} and /sitemap.xml. Does NOT gate
    | /robots.txt: this branch deletes the static public/robots.txt, so a host
    | with rendering disabled must still answer robots with "Disallow: /"
    | rather than 404.
    */
    'rendering_enabled' => filter_var(env('PUBLIC_REVIEW_RENDERING_ENABLED', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Trusted reverse proxies (R1)
    |--------------------------------------------------------------------------
    | Comma-separated proxy addresses whose X-Forwarded-Host / X-Forwarded-Proto
    | headers may be believed. Default: NONE — fail closed in every environment,
    | production included. No request header can alter this, and no environment
    | NAME selects it; only a deliberate TRUSTED_PROXIES entry opts a host in.
    |
    | Set this to the address the proxied connection ACTUALLY arrives from —
    | verified against the origin's access log, not assumed to be 127.0.0.1.
    | Never '*': the admin backend is reachable directly, so a wildcard would let
    | any client forge X-Forwarded-Host and select a region.
    |
    | Consumed by App\Providers\ProxyTrustServiceProvider, NOT bootstrap/app.php:
    | Middleware::trustProxies() calls the static TrustProxies::at() before the
    | env/config bootstrappers run, where config() throws and env() returns null.
    */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Indexability
    |--------------------------------------------------------------------------
    | Whether public review pages + sitemap may be crawled/indexed. Defaults to
    | true ONLY in production, so local/staging emit <meta robots noindex> and
    | never get accidentally indexed. Override with PUBLIC_REVIEW_INDEXABLE.
    */
    'indexable' => (bool) env('PUBLIC_REVIEW_INDEXABLE', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Rendered-HTML cache TTL (seconds)
    |--------------------------------------------------------------------------
    | Public review HTML is cached keyed by review_slug + updated_at. 0 disables
    | caching (default locally, so dev always sees fresh output). Set e.g. 3600
    | in production. Only public (200) responses are cached — never 404s.
    */
    'cache_ttl' => (int) env('PUBLIC_REVIEW_CACHE_TTL', 0),

    /*
    |--------------------------------------------------------------------------
    | Regional host → region code
    |--------------------------------------------------------------------------
    | Maps the leading host label (e.g. "au" in au.fstg.beastierated.com) to a
    | Region.region_code, for region-gating /review/{slug}. Extend here when a
    | new regional host goes live (e.g. 'eu' => 'EU'). A host not listed here
    | resolves to null → only all-region payloads serve (fail-safe).
    */
    'region_hosts' => [
        'au' => 'AU',
        'us' => 'US',
        'uk' => 'UK',
        'ca' => 'CA',
    ],

];
