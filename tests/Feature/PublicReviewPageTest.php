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
