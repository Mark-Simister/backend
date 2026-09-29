<?php

namespace Tests\Feature;

use App\Models\PublishedReviewPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public-eligibility boundary tests.
 *
 * Both /review/{slug} and /sitemap.xml consume PublishedReviewPayload::publicForRegion(),
 * so a single determination must govern both. These tests prove that for:
 *
 *   - the TEMPORARY D3 divergence-evidence hold (RP-DIV-02B1), and
 *   - D1, the qualified contradictory-safety predicate.
 *
 * The held reviews are NOT wrong. Their legacy divergence adjustments have not been
 * demonstrated to the current D3 standard, so they fail closed at the public boundary
 * until RP-DIV-03 grounds them.
 */
class PublicReviewEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'reviews.rendering_enabled' => true,
            'reviews.public_statuses' => ['ready_for_review', 'published'],
        ]);
    }

    private function payload(string $slug, array $columns = [], array $safety = null): PublishedReviewPayload
    {
        $rp = ['hero' => ['title' => 'T'], 'review_facts' => ['beastiescore' => 4.5]];
        if ($safety !== null) {
            $rp['safety'] = $safety;
        }

        return PublishedReviewPayload::create(array_merge([
            'published_review_id' => 'pr__' . $slug,
            'review_slug' => $slug,
            'publish_status' => 'ready_for_review',
            'h1' => 'T',
            'review_page_json' => $rp,
        ], $columns));
    }

    // ---------------------------------------------------------------- hold list

    public function test_the_configured_hold_contains_exactly_43_unique_identities(): void
    {
        $held = (array) config('reviews.divergence_hold_product_uids');

        $this->assertCount(43, $held, 'the hold must contain exactly 43 identities');
        $this->assertCount(43, array_unique($held), 'all 43 hold identities must be unique');

        foreach ($held as $uid) {
            $this->assertMatchesRegularExpression('/^US::BARKTASTIC::ASIN_B0[0-9A-Z]{8}$/', $uid);
        }
    }

    public function test_the_hold_includes_the_corrected_b01dsovb70_identity(): void
    {
        $this->assertContains(
            'US::BARKTASTIC::ASIN_B01DSOVB70',
            (array) config('reviews.divergence_hold_product_uids')
        );
    }

    // ------------------------------------------------- hold governs BOTH surfaces

    public function test_a_held_review_is_not_publicly_renderable(): void
    {
        $p = $this->payload('held-b01dsovb70', ['product_uid' => 'US::BARKTASTIC::ASIN_B01DSOVB70']);

        $this->get('/review/' . $p->review_slug)->assertNotFound();
    }

    public function test_a_held_review_is_absent_from_the_sitemap(): void
    {
        $this->payload('held-b01dsovb70', ['product_uid' => 'US::BARKTASTIC::ASIN_B01DSOVB70']);

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('held-b01dsovb70', false);
    }

    public function test_an_ordinary_review_remains_renderable_and_in_the_sitemap(): void
    {
        $p = $this->payload('ok-b000free', ['product_uid' => 'US::BARKTASTIC::ASIN_B000FREE00']);

        $this->get('/review/' . $p->review_slug)->assertOk();
        $this->get('/sitemap.xml')->assertOk()->assertSee('ok-b000free', false);
    }

    public function test_a_payload_without_a_product_uid_is_not_excluded_by_the_hold(): void
    {
        // NULL-safety: `product_uid NOT IN (...)` is NULL for NULL, which would
        // otherwise silently 404 every payload that carries no product_uid.
        $p = $this->payload('nouid-b000nul');

        $this->get('/review/' . $p->review_slug)->assertOk();
    }

    public function test_the_hold_matches_exactly_and_never_by_substring(): void
    {
        // A different ASIN that merely CONTAINS a held ASIN as a substring must not be held.
        $p = $this->payload('near-b000near', ['product_uid' => 'US::BARKTASTIC::ASIN_B01DSOVB71']);

        $this->get('/review/' . $p->review_slug)->assertOk();
    }

    // ------------------------------------------------------------------------ D1

    public function test_contradictory_safety_state_fails_closed_on_the_page(): void
    {
        foreach (['missing', 'unknown', 'stale'] as $i => $freshness) {
            $p = $this->payload('d1-' . $i . '-b000d1', [], [
                'safety_feed_status' => 'clear',
                'safety_feed_freshness_status' => $freshness,
            ]);

            $this->get('/review/' . $p->review_slug)
                ->assertNotFound("clear + {$freshness} must fail closed");
        }
    }

    public function test_contradictory_safety_state_is_absent_from_the_sitemap(): void
    {
        $this->payload('d1-sitemap-b000d1', [], [
            'safety_feed_status' => 'clear',
            'safety_feed_freshness_status' => 'stale',
        ]);

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('d1-sitemap-b000d1', false);
    }

    public function test_non_contradictory_safety_remains_eligible(): void
    {
        $p = $this->payload('d1-ok-b000ok', [], [
            'safety_feed_status' => 'clear',
            'safety_feed_freshness_status' => 'current',
        ]);

        $this->get('/review/' . $p->review_slug)->assertOk();
    }

    public function test_a_non_clear_status_with_stale_freshness_is_not_a_contradiction(): void
    {
        $p = $this->payload('d1-pm-b000pm', [], [
            'safety_feed_status' => 'possible_match',
            'safety_feed_freshness_status' => 'stale',
        ]);

        $this->get('/review/' . $p->review_slug)->assertOk();
    }

    public function test_a_payload_with_no_safety_block_remains_eligible(): void
    {
        $p = $this->payload('d1-none-b000nn');

        $this->get('/review/' . $p->review_slug)->assertOk();
    }
}
