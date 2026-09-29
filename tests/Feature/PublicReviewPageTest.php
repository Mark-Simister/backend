<?php

namespace Tests\Feature;

use App\Models\PublishedReviewPayload;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production-hardening tests for the public /review/{slug} route:
 * config-driven visibility gate, 404 behaviour, and crawlable HTML output.
 */
class PublicReviewPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These tests model the PUBLIC RENDERER host (review-bstg and the regional hosts in
     * front of it). PUBLIC_REVIEW_RENDERING_ENABLED now defaults to false, so that the
     * directly-reachable admin backend never becomes a second, indexable renderer of the
     * same content (R2). A renderer opts in explicitly.
     *
     * The disabled side of the flag is covered by PublicReviewRenderingFlagTest.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['reviews.rendering_enabled' => true]);
    }

    /**
     * Build a payload. PUBLIC DISPLAY SEMANTICS LIVE IN review_page_json - the flat
     * columns are retained because they are legitimate operational/query columns, but
     * the renderer must not use them as a display source. $overrides is merged into
     * review_page_json so each contract state is a deterministic in-test mutation
     * rather than a fixture file. $columns overrides flat columns (e.g. product_uid).
     */
    private function makePayload(string $status, string $slug = 'test-product-b000test', array $overrides = [], array $columns = []): PublishedReviewPayload
    {
        $rp = [
            'hero' => ['title' => 'Test Product Review', 'channel' => 'TestChannel', 'character_name' => 'Testy'],
            'quick_verdict' => ['summary' => 'A solid pick.'],
            'disclosure' => ['affiliate_disclosure_text' => 'Affiliate links may earn a commission.'],
            // confidence_tier is deliberately POPULATED here so the negative assertion
            // that it never reaches public HTML is meaningful.
            'review_facts' => [
                'beastiescore' => 4.5,
                'public_score' => 4.3,
                'public_rating_count_retailer' => 1234,
                'analysed_evidence_count' => 520,
                'source_count' => 5,
                'public_signal' => 'Very strong',
                'analysis_depth' => 'Standard',
                'analysis_depth_count' => 10,
                'confidence_tier' => 'High',
            ],
            'beastiescore' => [
                'final_score' => 4.5,
                'public_score' => 4.3,
                'confidence_tier' => 'High',
                'score_basis_summary' => 'Based on a public rating of 4.3/5 across 1,234 public ratings.',
                'analysis_depth_basis' => [
                    'professional_reviews' => 1,
                    'retailer_review_sources' => 2,
                    'video_reviews' => 7,
                    'total' => 10,
                ],
            ],
        ];

        $rp = array_replace_recursive($rp, $overrides);

        return PublishedReviewPayload::create(array_merge([
            'published_review_id' => 'pr__' . $slug,
            'review_slug' => $slug,
            'publish_status' => $status,
            'h1' => 'Test Product Review',
            'canonical_url' => 'https://example.test/review/' . $slug,
            'final_beastie_score' => 4.5,
            'public_score' => 4.3,
            'confidence_tier' => 'High',
            'public_rating_count' => 1234,
            'analysed_evidence_count' => 520,
            'source_count' => 5,
            'review_page_json' => $rp,
            'schema_json_ld' => [
                '@context' => 'https://schema.org',
                '@graph' => [['@type' => 'Product', 'name' => 'Test Product']],
            ],
        ], $columns));
    }

    /** Create a Region, bypassing mass-assignment (test schema-agnostic). */
    private function region(string $code): Region
    {
        $r = Region::firstOrNew(['region_code' => $code]);
        $r->forceFill(['region_name' => $code, 'is_active' => 1, 'currency' => 'USD', 'currency_symbol' => '$']);
        $r->save();
        return $r;
    }

    private function payloadForRegions(array $codes, string $slug): PublishedReviewPayload
    {
        $p = $this->makePayload('ready_for_review', $slug);
        $p->regions()->sync(collect($codes)->map(fn ($c) => $this->region($c)->id)->all());
        return $p;
    }

    private function reviewOn(string $slug, string $host)
    {
        // Full absolute URL so Symfony sets the request host (a 'Host' header
        // alone doesn't override getHost() in tests).
        return $this->get("http://{$host}/review/{$slug}");
    }

    public function test_region_gated_payload_resolves_only_on_assigned_regions(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->payloadForRegions(['AU', 'US'], 'regional-b000rgn');

        $this->reviewOn($p->review_slug, 'au.fstg.beastierated.com')->assertOk();
        $this->reviewOn($p->review_slug, 'us.fstg.beastierated.com')->assertOk();
        $this->reviewOn($p->review_slug, 'uk.fstg.beastierated.com')->assertNotFound();
        $this->reviewOn($p->review_slug, 'ca.fstg.beastierated.com')->assertNotFound();
    }

    public function test_empty_region_pivot_serves_all_regions(): void
    {
        // Backwards-compat: pipeline-seeded payloads (no pivot rows) resolve everywhere.
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'nopivot-b000all');

        foreach (['au', 'us', 'uk', 'ca'] as $r) {
            $this->reviewOn($p->review_slug, "$r.fstg.beastierated.com")->assertOk();
        }
        $this->reviewOn($p->review_slug, 'review-bstg.beastierated.com')->assertOk(); // unknown host, still all-region
    }

    public function test_global_region_row_serves_all_regions(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->payloadForRegions(['GLOBAL'], 'global-b000glb');

        foreach (['au', 'us', 'uk', 'ca'] as $r) {
            $this->reviewOn($p->review_slug, "$r.fstg.beastierated.com")->assertOk();
        }
    }

    public function test_unknown_host_hides_region_restricted_payload(): void
    {
        // Fail-safe: if the host can't be resolved to a region, a region-restricted
        // payload 404s (it must never leak to all regions).
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->payloadForRegions(['AU'], 'auonly-b000au');

        $this->reviewOn($p->review_slug, 'review-bstg.beastierated.com')->assertNotFound();
        $this->reviewOn($p->review_slug, '127.0.0.1')->assertNotFound();
        $this->reviewOn($p->review_slug, 'au.fstg.beastierated.com')->assertOk(); // but AU host does serve it
    }

    public function test_sitemap_filters_by_region(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $this->payloadForRegions(['AU'], 'auonly-sitemap-b000au');
        $this->makePayload('ready_for_review', 'allreg-sitemap-b000all'); // empty pivot = all

        $this->get('http://au.fstg.beastierated.com/sitemap.xml')
            ->assertOk()
            ->assertSee('auonly-sitemap-b000au', false)
            ->assertSee('allreg-sitemap-b000all', false);

        $this->get('http://uk.fstg.beastierated.com/sitemap.xml')
            ->assertOk()
            ->assertDontSee('auonly-sitemap-b000au', false)
            ->assertSee('allreg-sitemap-b000all', false);
    }

    public function test_region_gated_canonical_uses_request_host(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->payloadForRegions(['AU'], 'canon-b000au');

        $this->reviewOn($p->review_slug, 'au.fstg.beastierated.com')
            ->assertOk()
            ->assertSee('au.fstg.beastierated.com/review/' . $p->review_slug, false)
            ->assertDontSee('review-bstg', false);
    }

    public function test_ready_for_review_visible_when_configured(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $this->get('/review/' . $p->review_slug)
            ->assertOk()
            ->assertSee('Test Product Review', false);
    }

    public function test_published_visible_when_configured(): void
    {
        config(['reviews.public_statuses' => ['published']]);
        $p = $this->makePayload('published');

        $this->get('/review/' . $p->review_slug)->assertOk();
    }

    /**
     * Regression: an inline @if glued to a word char (e.g. "5@if(...)") is not
     * compiled by Blade and leaks as literal text. The Review Facts lines must render
     * their conditional suffixes AND never emit raw directives.
     *
     * AMENDED (RP-RENDER-IMPL-01): this test previously asserted that "High confidence"
     * RENDERS, which locked in a breach of the approved contract - confidence_tier must
     * never render. The raw-directive regression is retained; the confidence assertion
     * is inverted.
     */
    public function test_review_facts_render_without_raw_blade_directives(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        $res->assertSee('BeastieScore', false);
        $res->assertSee('1,234 ratings', false);

        foreach (['@if', '@endif', '@foreach', '@endforeach', '@php', '@else'] as $directive) {
            $res->assertDontSee($directive, false);
        }
    }

    /** confidence_tier is INTERNAL ONLY and must never appear in public HTML. */
    public function test_confidence_tier_never_renders(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        $res->assertDontSee('High confidence', false);
        $res->assertDontSee('Confidence:', false);
        $res->assertDontSee('confidence_tier', false);
    }

    /** No character or editorial component score may be presented publicly. */
    public function test_character_and_editorial_scores_never_render(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'ce-b000ce', [
            'beastiescore' => ['character_score' => 4.9, 'editorial_score' => -0.2],
        ]);

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        $res->assertDontSee('Character Score', false);
        $res->assertDontSee('Editorial Adjustment', false);
        $res->assertDontSee('4.9', false);
    }

    /** Public display semantics come from payload JSON, never the flat columns. */
    public function test_payload_json_is_the_display_authority(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        // Flat columns say 1.1 / 9999; payload JSON says 4.5 / 1234. JSON must win.
        $p = $this->makePayload('ready_for_review', 'auth-b000auth', [], [
            'final_beastie_score' => 1.1,
            'public_rating_count' => 9999,
        ]);

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        $res->assertSee('4.5', false);
        $res->assertSee('1,234 ratings', false);
        $res->assertDontSee('1.1/5', false);
        $res->assertDontSee('9,999', false);
    }

    /** public_signal / analysis_depth are the approved public evidence fields. */
    public function test_public_signal_and_analysis_depth_render(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        $res->assertSee('Very strong', false);
        $res->assertSee('Standard', false);
        $res->assertSee('independent sources examined', false);
    }

    /** beastiescore.raw is internal and must never be surfaced. */
    public function test_beastiescore_raw_is_never_surfaced(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'raw-b000raw', [
            'beastiescore' => ['raw' => ['safety_penalty' => 0.25, 'source_penalty' => 0.1, 'secret_internal' => 'LEAKME']],
        ]);

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        $res->assertDontSee('LEAKME', false);
        $res->assertDontSee('safety_penalty', false);
        $res->assertDontSee('source_penalty', false);
    }

    /** D3: a clean payload shows a visibly distinct no-adjustment state. */
    public function test_d3_no_adjustment_state(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'd3none-b000n', [
            'beastiescore' => ['public_score_adjustments' => [
                'base_public_rating' => 4.3, 'final_beastie_score' => 4.3,
                'adjustments' => [], 'net_adjustment' => 0,
                'no_adjustment_occurred' => true, 'adjustments_cancel_to_zero' => false,
            ]],
        ]);

        $this->get('/review/' . $p->review_slug)->assertOk()
            ->assertSee('data-adjustment-state="none"', false)
            ->assertSee('No adjustment was applied', false);
    }

    /** D3: one adjustment renders signed amount, reason and evidence references. */
    public function test_d3_single_adjustment(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'd3one-b000o', [
            'beastiescore' => ['public_score_adjustments' => [
                'base_public_rating' => 4.7, 'final_beastie_score' => 4.4,
                'adjustments' => [[
                    'kind' => 'divergence', 'amount' => -0.3,
                    'reason' => 'Independent reviews report rusting.',
                    'evidence_references' => ['https://example.test/expert-review'],
                ]],
                'net_adjustment' => -0.3,
                'no_adjustment_occurred' => false, 'adjustments_cancel_to_zero' => false,
            ]],
        ]);

        $this->get('/review/' . $p->review_slug)->assertOk()
            ->assertSee('data-adjustment-state="applied"', false)
            ->assertSee('-0.3', false)
            ->assertSee('Independent reviews report rusting.', false)
            ->assertSee('https://example.test/expert-review', false);
    }

    /** D3: multiple adjustments each render independently. */
    public function test_d3_multiple_adjustments(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'd3many-b000m', [
            'beastiescore' => ['public_score_adjustments' => [
                'base_public_rating' => 4.7, 'final_beastie_score' => 4.2,
                'adjustments' => [
                    ['kind' => 'divergence', 'amount' => -0.3, 'reason' => 'Durability shortfall.'],
                    ['kind' => 'safety', 'amount' => -0.2, 'reason' => 'Moderate safety risk.'],
                ],
                'net_adjustment' => -0.5,
                'no_adjustment_occurred' => false, 'adjustments_cancel_to_zero' => false,
            ]],
        ]);

        $this->get('/review/' . $p->review_slug)->assertOk()
            ->assertSee('Durability shortfall.', false)
            ->assertSee('Moderate safety risk.', false)
            ->assertSee('-0.5', false);
    }

    /** D3: cancellation to zero is visibly distinct from no adjustment. */
    public function test_d3_cancelling_adjustments(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'd3canc-b000c', [
            'beastiescore' => ['public_score_adjustments' => [
                'base_public_rating' => 4.5, 'final_beastie_score' => 4.5,
                'adjustments' => [
                    ['kind' => 'divergence', 'amount' => 0.2, 'reason' => 'Experts rate above the stars.'],
                    ['kind' => 'safety', 'amount' => -0.2, 'reason' => 'Moderate safety risk.'],
                ],
                'net_adjustment' => 0,
                'no_adjustment_occurred' => false, 'adjustments_cancel_to_zero' => true,
            ]],
        ]);

        $this->get('/review/' . $p->review_slug)->assertOk()
            ->assertSee('data-adjustment-cancels="1"', false)
            ->assertSee('cancel out', false)
            ->assertDontSee('data-adjustment-state="none"', false);
    }

    /** Retailer prices are suppressed while RP-PRICE-01 is unresolved. */
    public function test_retailer_price_is_suppressed_even_when_present(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'price-b000p', [
            'commerce' => ['retailers' => [
                ['name' => 'Amazon', 'url' => 'https://example.test/a', 'primary' => true, 'price' => 41.99, 'currency' => 'USD'],
            ]],
        ]);

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        $res->assertSee('Amazon', false);
        $res->assertDontSee('41.99', false);
        $res->assertDontSee('$41', false);
    }

    /** Sponsored treatment must not appear inside the national retailer loop. */
    public function test_sponsored_treatment_absent_from_national_loop(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review', 'spon-b000s', [
            'commerce' => ['retailers' => [
                ['name' => 'Walmart', 'url' => 'https://example.test/w', 'sponsored' => true],
            ]],
        ]);

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        $res->assertSee('data-retailers="national"', false);
        $res->assertSee('Walmart', false);
        $res->assertDontSee('>Ad<', false);
    }

    /** The local-retailer hydration mount contract exists below the national block. */
    public function test_local_retailer_mount_placeholder_exists(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $html = $this->get('/review/' . $p->review_slug)->assertOk()->getContent();

        $this->assertStringContainsString('data-island="local-retailers"', $html);
        $this->assertStringContainsString('data-island-state="ssr-placeholder"', $html);
        $this->assertLessThan(
            strpos($html, 'data-island="local-retailers"'),
            strpos($html, 'data-retailers="national"'),
            'the local mount must appear below the national retailer block'
        );
    }

    public function test_draft_is_hidden(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review', 'published']]);
        $p = $this->makePayload('draft');

        $this->get('/review/' . $p->review_slug)->assertNotFound();
    }

    public function test_status_not_in_whitelist_is_hidden(): void
    {
        // ready_for_review exists but the (production-like) gate is 'published' only.
        config(['reviews.public_statuses' => ['published']]);
        $p = $this->makePayload('ready_for_review');

        $this->get('/review/' . $p->review_slug)->assertNotFound();
    }

    public function test_robots_blocks_crawlers_when_not_indexable(): void
    {
        config(['reviews.indexable' => false]);
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false);
    }

    public function test_robots_allows_and_advertises_sitemap_when_indexable(): void
    {
        config(['reviews.indexable' => true]);
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Allow: /', false)
            ->assertSee('Sitemap:', false)
            ->assertDontSee('Disallow: /', false);
    }

    public function test_no_static_robots_txt_shadowing_the_dynamic_route(): void
    {
        // A static public/robots.txt is served by Apache BEFORE the Laravel route
        // (.htaccess RewriteCond !-f), silently overriding the env-aware
        // /robots.txt and defeating the staging noindex guard. Fail if re-added.
        $this->assertFileDoesNotExist(
            public_path('robots.txt'),
            'A static public/robots.txt would shadow the dynamic env-aware /robots.txt route.'
        );
    }

    public function test_public_seo_routes_are_stateless(): void
    {
        // Public SEO responses must not emit Set-Cookie (XSRF-TOKEN /
        // laravel_session): a Set-Cookie makes them uncacheable at the CDN and
        // hands crawlers a session. Assert the resolved middleware stack has no
        // session/CSRF/cookie middleware — environment-independent, so it guards
        // even where the test session driver wouldn't set a cookie anyway.
        $router = app('router');
        foreach (['reviews.sitemap', 'reviews.robots', 'public.reviews.show'] as $name) {
            $route = $router->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} should exist");
            $stateful = array_filter(
                $router->gatherRouteMiddleware($route),
                fn ($m) => str_contains($m, 'StartSession')
                    || str_contains($m, 'Csrf')
                    || str_contains($m, 'Cookie')
            );
            $this->assertSame([], array_values($stateful), "{$name} must be stateless (no session/CSRF/cookie middleware)");
        }
    }

    public function test_missing_slug_returns_404(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review', 'published']]);
        $this->get('/review/no-such-slug-b999none')->assertNotFound();
    }

    public function test_invalid_slug_returns_404(): void
    {
        // Underscores / spaces don't match the slug route constraint → no route → 404.
        $this->get('/review/not a valid slug')->assertNotFound();
        $this->get('/review/has_underscore')->assertNotFound();
    }

    public function test_numeric_legacy_url_without_mapping_404s(): void
    {
        // No matching video/payload → numeric legacy route 404s (never renders).
        $this->get('/review/999999')->assertNotFound();
    }

    public function test_jsonld_emitted_in_raw_html(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $this->get('/review/' . $p->review_slug)
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type"', false);
    }

    public function test_h1_emitted_in_raw_html(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $this->get('/review/' . $p->review_slug)
            ->assertOk()
            ->assertSee('<h1>', false);
    }

    public function test_canonical_is_slug_based(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $this->get('/review/' . $p->review_slug)
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('/review/' . $p->review_slug, false);
    }
}
