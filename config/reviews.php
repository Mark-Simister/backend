<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public review visibility
    |--------------------------------------------------------------------------
    | publish_status values that make a published_review_payload publicly
    | visible at /review/{slug} (and eligible for the sitemap).
    |
    | Local / staging default includes 'ready_for_review' because that's the
    | only status the pipeline currently produces. In PRODUCTION set:
    |     PUBLIC_REVIEW_STATUSES=published
    */
    'public_statuses' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PUBLIC_REVIEW_STATUSES', 'ready_for_review,published'))
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

];
