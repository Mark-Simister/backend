<?php

namespace Tests\Feature\AdminContent;

use App\Models\PublishedReviewPayload;
use App\Services\Reviews\ReviewPayloadIngestor;
use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\RenderProjection;
use App\Support\Reviews\ReviewFingerprints;
use App\Support\Reviews\SafetyFeedContradiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\Feature\AdminContent\Concerns\PublicReleaseNegativeControls;
use Tests\Feature\AdminContent\Concerns\PublicSurfaceFixtures;
use Tests\Feature\AdminContent\Concerns\ReleasesPublicReviews;
use Tests\TestCase;

/**
 * RP-ADMIN-CONTENT-MEDIA-IMPL-B-REVIEWFIX-01/03. A row whose stored review_page_json is
 * not structurally evaluable (left by a raw, unsupported writer) is refused on its own: it
 * is a 404, never a 500, and it can neither fail nor truncate the sitemap, the legacy
 * redirect or the Best For gate for any other row. D1 (E5) keeps its exact semantics,
 * now evaluated in application code (DESIGN-CORR-03). (Bounded set evaluation, the
 * absence of SQL JSON parsing and the same-object rule are proved in
 * PublicReleaseSetEvaluationTest.)
 */
class MalformedPayloadIsolationTest extends TestCase
{
    use AdminContentFixtures;
    use PublicReleaseNegativeControls;
    use PublicSurfaceFixtures;
    use RefreshDatabase;
    use ReleasesPublicReviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->enablePublicRendering();
        $this->associations();
        $this->applyPublicReleaseNegativeControl();
    }

    /** Stored text the databases cannot evaluate in a JSON path. */
    private static function unevaluable(): array
    {
        return [
            'truncated JSON' => '{"safety": {"safety_feed_status": "clear"',
            'not JSON' => 'this is not JSON',
            'empty string' => '',
            'number beyond the double range' => '{"safety": {"safety_feed_status": "clear", "safety_feed_freshness_status": "fresh"}, "x": 1e400}',
            'nested beyond the MySQL limit' => '{"safety": {"safety_feed_status": "clear"}, "deep": ' . str_repeat('{"a":', 100) . '1' . str_repeat('}', 100) . '}',
        ];
    }

    /** $n nested arrays around a scalar. */
    private static function nested(int $n): array|int
    {
        return array_reduce(range(1, $n), fn ($inner) => ['a' => $inner], 1);
    }

    private function stored(PublishedReviewPayload $payload): mixed
    {
        return DB::table('published_review_payloads')->where('id', $payload->id)->value('review_page_json');
    }

    private function assertServed(PublishedReviewPayload $payload): void
    {
        foreach ($this->hosts() as $label => $host) {
            $this->get($host . '/review/' . $payload->review_slug)->assertOk()->assertSee('Test Feeder Review');
            $this->assertTrue($this->sitemapLists($host, $payload->review_slug), "{$label}: the sitemap should list {$payload->review_slug}");
        }
    }

    private function assertRefused(PublishedReviewPayload $payload, string $because): void
    {
        foreach ($this->hosts() as $label => $host) {
            $this->get($host . '/review/' . $payload->review_slug)->assertNotFound();
            $this->assertFalse($this->sitemapLists($host, $payload->review_slug), "{$because} / {$label}: the sitemap must exclude it");
        }
        // verify() judges exactly the object it is given (the same-object rule), so the next
        // verification is of the row as it is stored now.
        foreach (['AU', 'US', 'UK', 'CA', null] as $region) {
            $this->assertNull(app(PublicReleaseVerifier::class)->verify($payload->fresh(), $region), "{$because} / verify({$region})");
        }
    }

    // ---- malformed-row isolation -----------------------------------------------------

    public function test_an_unevaluable_row_is_refused_alone_on_the_page_and_the_sitemap(): void
    {
        $first = $this->releasedReview('pr_valid_first');

        $bad = [];
        $n = 0;
        foreach (self::unevaluable() as $label => $json) {
            $review = $this->releasedReview('pr_bad_' . (++$n));
            $fingerprint = $review->content_fingerprint;
            $this->raw($review, ['review_page_json' => $json]);

            // A raw writer: the stored fingerprint and the release binding are untouched,
            // so nothing but the stored JSON says this row is broken.
            $this->assertNotNull($fingerprint, $label);
            $this->assertSame($fingerprint, $review->fresh()->content_fingerprint, $label);
            $this->assertTrue($this->record($review)->isReleased(), $label);
            $this->assertFalse(PublicReleaseVerifier::evaluable($this->stored($review)), $label);
            $bad[$label] = $review;
        }

        $last = $this->releasedReview('pr_valid_last');

        foreach ($bad as $label => $review) {
            $this->assertRefused($review, $label);
        }
        $this->assertServed($first);
        $this->assertServed($last);
    }

    public function test_the_sitemap_keeps_every_valid_row_around_unevaluable_rows(): void
    {
        // Primary-key order: valid, bad, valid, bad, valid. Sitemap order (updated_at desc)
        // puts a bad row first and another last.
        $plan = [
            'pr_scan_1' => [true, '2026-06-01 00:00:00'],
            'pr_scan_2' => [false, '2026-12-31 00:00:00'],
            'pr_scan_3' => [true, '2026-07-01 00:00:00'],
            'pr_scan_4' => [false, '2020-01-01 00:00:00'],
            'pr_scan_5' => [true, '2026-08-01 00:00:00'],
        ];
        $reviews = [];
        foreach ($plan as $id => [$valid, $updatedAt]) {
            $reviews[$id] = $this->releasedReview($id);
            $this->raw($reviews[$id], $valid ? ['updated_at' => $updatedAt] : ['updated_at' => $updatedAt, 'review_page_json' => '{"safety": {']);
        }

        foreach ($this->hosts() as $label => $host) {
            $xml = $this->get($host . '/sitemap.xml')->assertOk()->getContent();

            $positions = [];
            foreach (['pr_scan_5', 'pr_scan_3', 'pr_scan_1'] as $id) {
                $position = strpos($xml, '/review/' . $reviews[$id]->review_slug . '</loc>');
                $this->assertNotFalse($position, "{$label}: {$id} must be listed");
                $positions[] = $position;
            }
            $this->assertSame($positions, array_values(collect($positions)->sort()->all()), "{$label}: newest first, as before");

            foreach (['pr_scan_2', 'pr_scan_4'] as $id) {
                $this->assertStringNotContainsString('/review/' . $reviews[$id]->review_slug . '<', $xml, "{$label}: {$id} must be left out");
            }
        }
    }

    public function test_unevaluable_rows_first_and_last_in_key_and_sitemap_order_are_omitted(): void
    {
        // Primary-key order: bad (first), valid, valid, bad, valid, bad (last). Sitemap order
        // (updated_at desc): s1 bad first, s5, s3, s2 valid, s6 bad, s4 bad last.
        $plan = [
            'pr_edge_1' => [false, '2026-12-31 00:00:00'],
            'pr_edge_2' => [true, '2026-06-01 00:00:00'],
            'pr_edge_3' => [true, '2026-07-01 00:00:00'],
            'pr_edge_4' => [false, '2020-01-01 00:00:00'],
            'pr_edge_5' => [true, '2026-08-01 00:00:00'],
            'pr_edge_6' => [false, '2026-05-01 00:00:00'],
        ];
        $reviews = [];
        foreach ($plan as $id => [$valid, $updatedAt]) {
            $reviews[$id] = $this->releasedReview($id);
            $this->raw($reviews[$id], $valid ? ['updated_at' => $updatedAt] : ['updated_at' => $updatedAt, 'review_page_json' => '{"safety": {']);
        }
        $this->assertSame(array_keys($plan), PublishedReviewPayload::query()->orderBy('id')->pluck('published_review_id')->all());

        foreach ($this->hosts() as $label => $host) {
            $xml = $this->get($host . '/sitemap.xml')->assertOk()->getContent();

            $positions = [];
            foreach (['pr_edge_5', 'pr_edge_3', 'pr_edge_2'] as $id) {
                $position = strpos($xml, '/review/' . $reviews[$id]->review_slug . '</loc>');
                $this->assertNotFalse($position, "{$label}: {$id} must be listed");
                $positions[] = $position;
            }
            $this->assertSame($positions, array_values(collect($positions)->sort()->all()), "{$label}: newest first, as before");

            foreach (['pr_edge_1', 'pr_edge_4', 'pr_edge_6'] as $id) {
                $this->assertStringNotContainsString('/review/' . $reviews[$id]->review_slug . '<', $xml, "{$label}: {$id} must be left out");
                $this->get($host . '/review/' . $reviews[$id]->review_slug)->assertNotFound();
            }
        }
    }

    public function test_the_best_for_gate_refuses_an_unevaluable_selected_review_without_failing(): void
    {
        [$payloads, $edition] = $this->releasedBestForEdition();
        $this->assertSame('', $this->bestForErrors($edition));

        $this->raw($payloads[3], ['review_page_json' => '{"safety": {']);

        $errors = $this->bestForErrors($edition);
        $this->assertStringContainsString('Review pr_bf_3 does not pass public-release verification', $errors);
        foreach ([1, 2, 4, 5] as $n) {
            $this->assertStringNotContainsString('Review pr_bf_' . $n . ' does not pass', $errors);
        }
    }

    public function test_the_legacy_redirect_refuses_an_unevaluable_review_and_never_reveals_its_slug(): void
    {
        $bad = $this->releasedReview('pr_redirect_bad', ['product_uid' => 'US::BARKTASTIC::ASIN_B0MALFORM1']);
        $this->raw($bad, ['review_page_json' => '{"safety": {']);
        $video = $this->legacyVideo('B0MALFORM1');

        foreach ($this->hosts() as $host) {
            $response = $this->get($host . '/review/' . $video)->assertNotFound();
            $this->assertNull($response->headers->get('Location'));
            $this->assertStringNotContainsString($bad->review_slug, $response->getContent());
        }

        // Two reviews share an ASIN: the unevaluable one (lower id) is skipped, not fatal.
        $sharedBad = $this->releasedReview('pr_shared_bad', ['product_uid' => 'US::BARKTASTIC::ASIN_B0SHARED01']);
        $sharedGood = $this->releasedReview('pr_shared_good', ['product_uid' => 'AU::BARKTASTIC::ASIN_B0SHARED01']);
        $this->raw($sharedBad, ['review_page_json' => '']);
        $shared = $this->legacyVideo('B0SHARED01');

        foreach ($this->hosts() as $host) {
            $this->get($host . '/review/' . $shared)->assertStatus(301)->assertRedirect($host . '/review/' . $sharedGood->review_slug);
        }
    }

    public function test_the_depth_limit_matches_what_the_database_can_evaluate(): void
    {
        // 100 nested containers (the page object + 99): MySQL evaluates it; still public.
        $within = $this->releasedReview('pr_depth_100', [], ['deep' => self::nested(99)]);
        $this->assertTrue(PublicReleaseVerifier::evaluable($this->stored($within)));

        // 101 (the page object + 100): MySQL refuses it (3157). The binding is forced to
        // match, so the evaluability check alone refuses it - the same on every database.
        $beyond = $this->ingest($this->reviewRow('pr_depth_101', [], ['deep' => self::nested(100)]));
        $this->releaseForPublic($beyond);
        $this->assertFalse(PublicReleaseVerifier::evaluable($this->stored($beyond)));

        $this->assertServed($within);
        $this->assertRefused($beyond, '101 nested containers');
    }

    public function test_evaluability_is_the_database_parser_boundary(): void
    {
        $this->assertTrue(PublicReleaseVerifier::evaluable(null), 'NULL: the JSON path is NULL');
        $this->assertTrue(PublicReleaseVerifier::evaluable('{"x": 1.7976931348623157e308}'), 'largest finite double');
        $this->assertTrue(PublicReleaseVerifier::evaluable('{"x": 4.9e-324}'), 'smallest subnormal');
        $this->assertTrue(PublicReleaseVerifier::evaluable('{"x": 18446744073709551616}'), 'a big integer is a finite double');
        $this->assertFalse(PublicReleaseVerifier::evaluable('{"x": 1e309}'));
        $this->assertFalse(PublicReleaseVerifier::evaluable('{"x": [-' . str_repeat('9', 400) . ']}'));
        $this->assertFalse(PublicReleaseVerifier::evaluable(42), 'a non-string stored value is not trusted');
        foreach (self::unevaluable() as $label => $json) {
            $this->assertFalse(PublicReleaseVerifier::evaluable($json), $label);
        }
    }

    // ---- supported writers stay fail closed ------------------------------------------

    public function test_supported_ingestion_never_stores_malformed_json_and_demotes_the_release(): void
    {
        $review = $this->releasedReview('pr_ingest');
        $valid = $this->releasedReview('pr_ingest_valid');

        $row = $this->reviewRow('pr_ingest');
        $row['review_page_json'] = '{"safety": {"safety_feed_status": "clear"';
        $result = app(ReviewPayloadIngestor::class)->upsert($row);

        $this->assertTrue(json_validate($this->stored($review)), 'the supported writer stores JSON text, never malformed JSON');
        $this->assertNotNull($result['invalidation']);
        $this->assertFalse($this->record($review)->isReleased(), 'the changed content demotes the release');

        $this->assertRefused($review, 'malformed input through supported ingestion');
        $this->assertServed($valid);
    }

    public function test_the_fingerprint_backfill_demotes_an_unevaluable_row_which_stays_refused(): void
    {
        $review = $this->releasedReview('pr_backfill');
        $valid = $this->releasedReview('pr_backfill_valid');
        $this->raw($review, ['review_page_json' => '{"safety": {']);

        $this->artisan('reviews:fingerprint-backfill', ['--check' => true])
            ->expectsOutputToContain('drift: pr_backfill')
            ->assertExitCode(1);
        $this->artisan('reviews:fingerprint-backfill', ['--apply' => true])
            ->expectsOutputToContain('invalidated: pr_backfill (fingerprint_unavailable)')
            ->assertExitCode(0);

        $this->assertNull($review->fresh()->content_fingerprint);
        $this->assertFalse($this->record($review)->isReleased());
        $this->assertRefused($review, 'after the backfill');
        $this->assertServed($valid);
    }

    // ---- D1 preservation -------------------------------------------------------------

    public function test_d1_is_unchanged_for_every_evaluable_row(): void
    {
        $verifier = app(PublicReleaseVerifier::class);

        $this->assertServed($this->releasedReview('pr_d1_clear_fresh'));

        foreach (['missing', 'unknown', 'stale'] as $freshness) {
            // Release refuses D1 content, so the binding is forced: E8 would accept the row.
            $review = $this->ingest($this->reviewRow('pr_d1_clear_' . $freshness, [], [
                'safety' => ['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => $freshness],
            ]));
            $this->releaseForPublic($review);
            $this->assertTrue(PublicReleaseVerifier::evaluable($this->stored($review)));
            $this->assertSame($this->record($review)->bound_content_fingerprint, ReviewFingerprints::content($review->fresh(), FingerprintScheme::P1));

            // ... and the JSON-free SQL gates admit it (DESIGN-CORR-03), so the one shared D1
            // implementation, on the in-memory projection, is what refuses it.
            $this->assertTrue($verifier->candidates(null)->whereKey($review->id)->exists(), "SQL gates: clear + {$freshness}");
            $this->assertTrue(app(SafetyFeedContradiction::class)->blocks(RenderProjection::build($review->fresh(), FingerprintScheme::P1)['review_page_json']), "D1: clear + {$freshness}");
            $this->assertRefused($review, "D1 clear + {$freshness}");
        }

        // A non-clear status is not a D1 contradiction, whatever its freshness.
        $nonClear = $this->ingest($this->reviewRow('pr_d1_not_clear', [], [
            'safety' => ['safety_feed_status' => 'alert', 'safety_feed_freshness_status' => 'stale'],
        ]));
        $this->releaseForPublic($nonClear);
        $this->assertServed($nonClear);

        // No safety block at all is not contradictory (the NULL-safe form).
        $row = $this->reviewRow('pr_d1_no_safety');
        unset($row['review_page_json']['safety']);
        $noSafety = $this->ingest($row);
        $this->releaseForPublic($noSafety);
        $this->assertServed($noSafety);

        // Resolving the contradiction makes the same review eligible again.
        $resolved = $this->ingest($this->reviewRow('pr_d1_clear_stale', [], [
            'safety' => ['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'fresh'],
        ]));
        $this->releaseForPublic($resolved);
        $this->assertServed($resolved);
    }

    /**
     * The exact D1 truth table of the one shared implementation, cross-checked against the
     * D1 SQL predicate it replaces (still in PublishedReviewPayload, evaluated here on valid
     * stored JSON): never a weakening on either engine, and identical to MySQL, the
     * production engine, whose PAD SPACE comparison blocked trailing-space variants.
     */
    public function test_d1_truth_table_is_exact(): void
    {
        $cases = [
            'clear + fresh' => [['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'fresh'], false],
            'clear + missing' => [['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'missing'], true],
            'clear + unknown' => [['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'unknown'], true],
            'clear + stale' => [['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'stale'], true],
            'non-clear + stale' => [['safety_feed_status' => 'alert', 'safety_feed_freshness_status' => 'stale'], false],
            'clear + absent freshness' => [['safety_feed_status' => 'clear'], false],
            'clear + null freshness' => [['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => null], false],
            'absent status + stale' => [['safety_feed_freshness_status' => 'stale'], false],
            'null status + stale' => [['safety_feed_status' => null, 'safety_feed_freshness_status' => 'stale'], false],
            'empty safety block' => [[], false],
            'Clear + stale (case)' => [['safety_feed_status' => 'Clear', 'safety_feed_freshness_status' => 'stale'], false],
            'CLEAR + STALE (case)' => [['safety_feed_status' => 'CLEAR', 'safety_feed_freshness_status' => 'STALE'], false],
            'clear + Missing (case)' => [['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'Missing'], false],
            'leading space status' => [['safety_feed_status' => ' clear', 'safety_feed_freshness_status' => 'stale'], false],
            'tab after status' => [['safety_feed_status' => "clear\t", 'safety_feed_freshness_status' => 'stale'], false],
            'trailing space status' => [['safety_feed_status' => 'clear ', 'safety_feed_freshness_status' => 'stale'], true],
            'trailing space freshness' => [['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => 'stale  '], true],
            'boolean status' => [['safety_feed_status' => true, 'safety_feed_freshness_status' => 'stale'], false],
            'numeric status' => [['safety_feed_status' => 1, 'safety_feed_freshness_status' => 'stale'], false],
            'array status' => [['safety_feed_status' => ['clear'], 'safety_feed_freshness_status' => 'stale'], false],
            'array freshness' => [['safety_feed_status' => 'clear', 'safety_feed_freshness_status' => ['stale']], false],
        ];

        $d1 = app(SafetyFeedContradiction::class);
        $mysql = DB::connection()->getDriverName() === 'mysql';
        $n = 0;
        foreach ($cases as $label => [$safety, $blocked]) {
            $row = $this->reviewRow('pr_d1_case_' . (++$n));
            $row['review_page_json']['safety'] = $safety;
            $payload = $this->ingest($row);
            $page = RenderProjection::build($payload->fresh(), FingerprintScheme::P1)['review_page_json'];

            $this->assertSame($blocked, $d1->blocks($page), $label);

            $sqlBlocked = ! PublishedReviewPayload::query()->whereKey($payload->id)->passesSafetyContradictionGate()->exists();
            if ($sqlBlocked) {
                $this->assertTrue($blocked, "{$label}: never weaker than the replaced SQL D1");
            }
            if ($mysql) {
                $this->assertSame($sqlBlocked, $blocked, "{$label}: identical to the replaced SQL D1 on MySQL");
            }
        }

        // Shapes that never reach a safety block.
        foreach ([null, 'a string page', ['safety' => 'not an object'], ['safety' => ['clear', 'stale']], []] as $page) {
            $this->assertFalse($d1->blocks($page));
        }
    }
}
