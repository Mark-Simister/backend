<?php

namespace Tests\Feature;

use App\Models\PublishedReviewPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production-hardening tests for the public /review/{slug} route:
 * config-driven visibility gate, 404 behaviour, and crawlable HTML output.
 */
class PublicReviewPageTest extends TestCase
{
    use RefreshDatabase;

    private function makePayload(string $status, string $slug = 'test-product-b000test'): PublishedReviewPayload
    {
        return PublishedReviewPayload::create([
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
            'review_page_json' => [
                'hero' => ['title' => 'Test Product Review', 'channel' => 'TestChannel', 'character_name' => 'Testy'],
                'quick_verdict' => ['summary' => 'A solid pick.'],
                'disclosure' => ['affiliate_disclosure_text' => 'Affiliate links may earn a commission.'],
            ],
            'schema_json_ld' => [
                '@context' => 'https://schema.org',
                '@graph' => [['@type' => 'Product', 'name' => 'Test Product']],
            ],
        ]);
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
     * compiled by Blade and leaks as literal text. The Review Facts BeastieScore
     * / Public-score / Evidence lines must render their conditional suffixes AND
     * never emit raw directives.
     */
    public function test_beastiescore_confidence_renders_without_raw_blade_directives(): void
    {
        config(['reviews.public_statuses' => ['ready_for_review']]);
        $p = $this->makePayload('ready_for_review');

        $res = $this->get('/review/' . $p->review_slug)->assertOk();

        // Facts render, including the conditional confidence text.
        $res->assertSee('BeastieScore', false);
        $res->assertSee('High confidence', false);
        $res->assertSee('1,234 ratings', false);

        // No raw Blade directive may appear anywhere in the HTML.
        foreach (['@if', '@endif', '@foreach', '@endforeach', '@php', '@else'] as $directive) {
            $res->assertDontSee($directive, false);
        }
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
