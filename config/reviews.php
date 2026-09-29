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

    /*
    |--------------------------------------------------------------------------
    | TEMPORARY D3 divergence-evidence hold (RP-DIV-02B1)
    |--------------------------------------------------------------------------
    | These reviews carry LEGACY DIVERGENCE ADJUSTMENTS whose evidentiary
    | grounding has NOT been demonstrated to the current D3 standard. They are
    | NOT declared wrong and their stored scores are untouched. They must fail
    | closed at the public publication/indexing boundary for as long as the
    | displayed BeastieScore incorporates the unresolved divergence adjustment
    | without its required D3 disclosure.
    |
    | This is a TEMPORARY evidence hold, not a general publication mechanism.
    | It is cleared by RP-DIV-03 (divergence evidence grounding), not by editing
    | this list to make a page visible.
    |
    | Identity is the exact `product_uid` (US::BARKTASTIC::ASIN_<ASIN>) and is
    | matched exactly - never by LIKE or substring. Enforced once, in
    | PublishedReviewPayload::scopePublicForRegion(), so /review/{slug} and the
    | sitemap cannot diverge.
    */
    'divergence_hold_product_uids' => [
        'US::BARKTASTIC::ASIN_B00008DFGY',
        'US::BARKTASTIC::ASIN_B000296N7S',
        'US::BARKTASTIC::ASIN_B0002AR0I8',
        'US::BARKTASTIC::ASIN_B0002J1FOE',
        'US::BARKTASTIC::ASIN_B000L3XYZ4',
        'US::BARKTASTIC::ASIN_B000NVA06A',
        'US::BARKTASTIC::ASIN_B001FK4BLI',
        'US::BARKTASTIC::ASIN_B001IN3JCY',
        'US::BARKTASTIC::ASIN_B004QN0M2S',
        'US::BARKTASTIC::ASIN_B005VS9WO6',
        'US::BARKTASTIC::ASIN_B0062JFGFC',
        'US::BARKTASTIC::ASIN_B007R1BN56',
        'US::BARKTASTIC::ASIN_B007S9JOO4',
        'US::BARKTASTIC::ASIN_B0081XIKYG',
        'US::BARKTASTIC::ASIN_B00JZIDFKK',
        'US::BARKTASTIC::ASIN_B00P0YQYYW',
        'US::BARKTASTIC::ASIN_B00WWP1U7I',
        'US::BARKTASTIC::ASIN_B01DOP5S9K',
        'US::BARKTASTIC::ASIN_B01DSOVB70',
        'US::BARKTASTIC::ASIN_B01L0QQNJE',
        'US::BARKTASTIC::ASIN_B06XKKPPMP',
        'US::BARKTASTIC::ASIN_B076F7HM8T',
        'US::BARKTASTIC::ASIN_B07BVL8TQF',
        'US::BARKTASTIC::ASIN_B07HCB1JPS',
        'US::BARKTASTIC::ASIN_B07L3HPQG7',
        'US::BARKTASTIC::ASIN_B07NSFR681',
        'US::BARKTASTIC::ASIN_B07VT1468W',
        'US::BARKTASTIC::ASIN_B07XF3TH73',
        'US::BARKTASTIC::ASIN_B07ZN238CL',
        'US::BARKTASTIC::ASIN_B07ZPPSR2L',
        'US::BARKTASTIC::ASIN_B08CK5Z5Q1',
        'US::BARKTASTIC::ASIN_B08NCDBT7Q',
        'US::BARKTASTIC::ASIN_B097S74FZK',
        'US::BARKTASTIC::ASIN_B09D7DWTVB',
        'US::BARKTASTIC::ASIN_B09LD2CD1L',
        'US::BARKTASTIC::ASIN_B0B2DM5Q7N',
        'US::BARKTASTIC::ASIN_B0B5ZJY9MT',
        'US::BARKTASTIC::ASIN_B0CC23VLBL',
        'US::BARKTASTIC::ASIN_B0CFFKWYH6',
        'US::BARKTASTIC::ASIN_B0CL3WC4MT',
        'US::BARKTASTIC::ASIN_B0CLB6BD1B',
        'US::BARKTASTIC::ASIN_B0D5B6RLYY',
        'US::BARKTASTIC::ASIN_B0DK4787M3',
    ],

];
