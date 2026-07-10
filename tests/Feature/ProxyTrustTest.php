<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Providers\ProxyTrustServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

/**
 * R1 — reverse-proxy trust is environment-controlled and fails closed.
 *
 * The assertion that matters is not "getHost() changed". It is that a forged
 * X-Forwarded-Host from an untrusted client cannot SELECT A REGION: an AU-only review
 * page must stay invisible on every other host, however the client labels itself.
 *
 * `$this->get()` builds the request with REMOTE_ADDR = 127.0.0.1, so pointing
 * trusted_proxies at a different address models an untrusted direct client.
 */
class ProxyTrustTest extends TestCase
{
    use RefreshDatabase;

    private const AU_SLUG = 'vecomfy-fleece-dog-hoodie-b07hcb1jps';

    protected function setUp(): void
    {
        parent::setUp();

        // TrustProxies keeps its configuration in STATIC properties, which leak between
        // tests in the same process. Reset before each, or one test's trusted proxy
        // silently grants the next test's "untrusted" client.
        TrustProxies::at([]);

        config(['reviews.rendering_enabled' => true]);
    }

    protected function tearDown(): void
    {
        TrustProxies::at([]);
        parent::tearDown();
    }

    /** Re-run the provider so a config change takes effect (boot() already ran). */
    private function trust(array $proxies): void
    {
        config(['reviews.trusted_proxies' => $proxies]);
        (new ProxyTrustServiceProvider($this->app))->boot();
    }

    private function auOnlyPayload(): PublishedReviewPayload
    {
        $au = Region::firstOrNew(['region_code' => 'AU']);
        $au->forceFill(['region_name' => 'AU', 'is_active' => 1, 'currency' => 'AUD', 'currency_symbol' => '$'])->save();

        $p = new PublishedReviewPayload();
        $p->forceFill([
            'published_review_id' => 'admin__1',
            'review_slug' => self::AU_SLUG,
            'publish_status' => 'published',
            'source' => 'admin',
            'h1' => 'Vecomfy Fleece Dog Hoodie',
            'review_page_json' => [],
        ])->save();

        $p->regions()->sync([$au->id]);

        return $p;
    }

    // ─────────────── fail closed ───────────────

    public function test_no_trusted_proxies_by_default(): void
    {
        $this->assertSame([], config('reviews.trusted_proxies'));
    }

    public function test_spoofed_forwarded_host_from_an_untrusted_client_cannot_select_a_region(): void
    {
        $this->auOnlyPayload();
        $this->trust([]);                       // fail closed: trust nobody

        $this->get('http://review-bstg.beastierated.com/review/' . self::AU_SLUG, [
            'X-Forwarded-Host' => 'au.fstg.beastierated.com',
        ])->assertNotFound();                   // the forged header was ignored → no region → 404
    }

    public function test_a_client_that_is_not_the_configured_proxy_is_ignored(): void
    {
        $this->auOnlyPayload();
        $this->trust(['10.99.99.99']);          // the real proxy is elsewhere; we are 127.0.0.1

        $this->get('http://review-bstg.beastierated.com/review/' . self::AU_SLUG, [
            'X-Forwarded-Host' => 'au.fstg.beastierated.com',
        ])->assertNotFound();
    }

    // ─────────────── the genuine proxy is honoured ───────────────

    public function test_the_configured_proxy_may_set_the_forwarded_host(): void
    {
        $this->auOnlyPayload();
        $this->trust(['127.0.0.1']);            // the address the request actually arrives from

        $response = $this->get('http://review-bstg.beastierated.com/review/' . self::AU_SLUG, [
            'X-Forwarded-Host' => 'au.fstg.beastierated.com',
            'X-Forwarded-Proto' => 'https',
        ]);

        $response->assertOk();
        $response->assertSee('https://au.fstg.beastierated.com/review/' . self::AU_SLUG, false);
    }

    /** Regional canonical generation, through the proxy, on every live region. */
    public function test_canonical_follows_the_forwarded_host_for_every_region(): void
    {
        $p = $this->auOnlyPayload();
        foreach (['US', 'UK', 'CA'] as $code) {
            $r = Region::firstOrNew(['region_code' => $code]);
            $r->forceFill(['region_name' => $code, 'is_active' => 1, 'currency' => 'USD', 'currency_symbol' => '$'])->save();
            $p->regions()->attach($r->id);
        }
        $this->trust(['127.0.0.1']);

        foreach (['au', 'us', 'uk', 'ca'] as $host) {
            $this->get('http://review-bstg.beastierated.com/review/' . self::AU_SLUG, [
                'X-Forwarded-Host' => "{$host}.fstg.beastierated.com",
                'X-Forwarded-Proto' => 'https',
            ])
                ->assertOk()
                ->assertSee("https://{$host}.fstg.beastierated.com/review/" . self::AU_SLUG, false);
        }
    }

    /** X-Forwarded-For is deliberately not trusted: the renderer does no IP-based logic. */
    public function test_forwarded_for_is_not_trusted_even_from_the_proxy(): void
    {
        $this->trust(['127.0.0.1']);

        $this->get('http://review-bstg.beastierated.com/robots.txt', [
            'X-Forwarded-For' => '203.0.113.7',
        ])->assertOk();

        $this->assertSame('127.0.0.1', request()->ip());
    }
}
