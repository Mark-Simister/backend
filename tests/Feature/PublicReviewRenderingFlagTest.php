<?php

namespace Tests\Feature;

use App\Models\PublishedReviewPayload;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R2 — PUBLIC_REVIEW_RENDERING_ENABLED, and the promise that public SEO routes never write.
 *
 * The admin backend is directly reachable and carries the same renderer code. With the flag
 * off it must 404 the review pages and the sitemap — but still answer /robots.txt with
 * "Disallow: /", because this branch deletes the static public/robots.txt and Apache would
 * otherwise hand crawlers a 404.
 */
class PublicReviewRenderingFlagTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'vecomfy-fleece-dog-hoodie-b07hcb1jps';

    private function globalPayload(): PublishedReviewPayload
    {
        $p = new PublishedReviewPayload();
        $p->forceFill([
            'published_review_id' => 'pipeline__1',
            'review_slug' => self::SLUG,
            'publish_status' => 'published',
            'source' => 'pipeline',
            'h1' => 'Vecomfy Fleece Dog Hoodie',
            'review_page_json' => [],
        ])->save();

        return $p;   // empty pivot = all regions (pipeline back-compat)
    }

    // ─────────────── disabled (the admin backend) ───────────────

    public function test_rendering_is_disabled_by_default(): void
    {
        $this->assertFalse(config('reviews.rendering_enabled'));
    }

    public function test_a_disabled_host_serves_no_review_page_and_no_sitemap(): void
    {
        $this->globalPayload();
        config(['reviews.rendering_enabled' => false]);

        $this->get('http://stg.beastierated.com/review/' . self::SLUG)->assertNotFound();
        $this->get('http://stg.beastierated.com/sitemap.xml')->assertNotFound();
        $this->get('http://stg.beastierated.com/review/122')->assertNotFound();
    }

    public function test_a_disabled_host_still_answers_robots_with_disallow_and_no_sitemap_line(): void
    {
        config(['reviews.rendering_enabled' => false]);

        $response = $this->get('http://stg.beastierated.com/robots.txt');

        $response->assertOk();
        $response->assertSee('Disallow: /', false);
        $response->assertDontSee('Sitemap:', false);
    }

    /** Even if someone sets PUBLIC_REVIEW_INDEXABLE=true, a non-rendering host stays closed. */
    public function test_indexable_cannot_override_a_disabled_host(): void
    {
        config(['reviews.rendering_enabled' => false, 'reviews.indexable' => true]);

        $response = $this->get('http://stg.beastierated.com/robots.txt');

        $response->assertSee('Disallow: /', false);
        $response->assertDontSee('Sitemap:', false);
        $response->assertDontSee('Allow: /', false);
    }

    // ─────────────── enabled (the renderer) ───────────────

    public function test_an_enabled_host_serves_the_review_page_and_the_sitemap(): void
    {
        $this->globalPayload();
        config(['reviews.rendering_enabled' => true]);

        $this->get('http://review-bstg.beastierated.com/review/' . self::SLUG)->assertOk();
        $this->get('http://review-bstg.beastierated.com/sitemap.xml')
            ->assertOk()
            ->assertSee(self::SLUG, false);
    }

    public function test_an_enabled_and_indexable_host_advertises_its_sitemap(): void
    {
        config(['reviews.rendering_enabled' => true, 'reviews.indexable' => true]);

        $this->get('http://review-bstg.beastierated.com/robots.txt')
            ->assertSee('Allow: /', false)
            ->assertSee('Sitemap: http://review-bstg.beastierated.com/sitemap.xml', false);
    }

    // ─────────────── the read-only promise ───────────────

    /**
     * The renderer runs on a SELECT-only MySQL account. A single INSERT into `sessions` or
     * `cache` on any of these routes is a 500 for a public visitor, so prove there are none.
     */
    public function test_public_seo_routes_perform_no_database_writes(): void
    {
        $this->globalPayload();
        config(['reviews.rendering_enabled' => true]);

        // phpunit.xml pins SESSION_DRIVER=array and CACHE_STORE=array, under which a
        // session or cache write touches no database and this test could never fail.
        // Point both at the database — as review-bstg's .env did before the cutover —
        // so that a StartSession left in the stack shows up as an INSERT.
        config(['session.driver' => 'database', 'cache.default' => 'database']);

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->get('http://au.fstg.beastierated.com/review/' . self::SLUG)->assertOk();
        $this->get('http://au.fstg.beastierated.com/sitemap.xml')->assertOk();
        $this->get('http://au.fstg.beastierated.com/robots.txt')->assertOk();

        $this->assertSame([], $writes, 'public SEO routes must never write to the database');
    }

    public function test_public_seo_routes_emit_no_set_cookie(): void
    {
        $this->globalPayload();
        config(['reviews.rendering_enabled' => true]);

        foreach (['/review/' . self::SLUG, '/sitemap.xml', '/robots.txt'] as $path) {
            $response = $this->get('http://au.fstg.beastierated.com' . $path);
            $this->assertEmpty($response->headers->getCookies(), "{$path} must not set a cookie");
        }
    }
}
