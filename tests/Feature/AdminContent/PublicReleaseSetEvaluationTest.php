<?php

namespace Tests\Feature\AdminContent;

use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Support\Reviews\PublicReleaseVerifier;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\Feature\AdminContent\Concerns\PublicReleaseNegativeControls;
use Tests\Feature\AdminContent\Concerns\PublicSurfaceFixtures;
use Tests\Feature\AdminContent\Concerns\ReleasesPublicReviews;
use Tests\TestCase;

/**
 * RP-ADMIN-CONTENT-MEDIA-IMPL-B-REVIEWFIX-03 (DESIGN-CORR-03). The public candidate SQL
 * is JSON-free and set-oriented; structural evaluability, D1 and E8 run in memory on the
 * very objects that SQL loaded; E8's editorial-record and media reads are batched.
 */
class PublicReleaseSetEvaluationTest extends TestCase
{
    use AdminContentFixtures;
    use PublicReleaseNegativeControls;
    use PublicSurfaceFixtures;
    use RefreshDatabase;
    use ReleasesPublicReviews;

    private const AU = 'http://au.fstg.beastierated.com';
    private const BAD = '{"safety": {';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->enablePublicRendering();
        $this->applyPublicReleaseNegativeControl();
    }

    /** The SQL-gate statement: the only public statement that reads the disposition table. */
    private static function isSqlGate(string $sql): bool
    {
        return str_contains($sql, 'review_publication_dispositions');
    }

    /** Any database JSON function call, JSON operator, or SQL mention of review_page_json. */
    private static function parsesJson(string $sql): bool
    {
        return (bool) preg_match('/\bjson_[a-z_]+\s*\(|->>|review_page_json/i', $sql);
    }

    /** @return string[] every SELECT the action ran, as the database received it */
    private function selectsOf(Closure $action): array
    {
        $selects = [];
        $recording = true;
        DB::listen(function (QueryExecuted $query) use (&$selects, &$recording) {
            if ($recording && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selects[] = $query->sql;
            }
        });

        try {
            $action();
        } finally {
            $recording = false;
        }

        return $selects;
    }

    private function assertNoJsonParsing(array $selects, string $label): void
    {
        $this->assertNotEmpty(array_filter($selects, fn ($sql) => self::isSqlGate($sql)), "{$label}: the SQL gates ran");
        foreach ($selects as $sql) {
            $this->assertFalse(self::parsesJson($sql), "{$label}: {$sql}");
        }
    }

    /**
     * $count released rows; those at $badAt are then made unevaluable by a raw writer.
     *
     * @return array{0: PublishedReviewPayload[], 1: PublishedReviewPayload[]}  [valid, bad]
     */
    private function releasedRows(string $prefix, int $count, array $badAt): array
    {
        $valid = [];
        $bad = [];
        for ($i = 0; $i < $count; $i++) {
            $payload = $this->ingest($this->reviewRow($prefix . '_' . $i));
            $this->releaseForPublic($payload);
            if (in_array($i, $badAt, true)) {
                $this->raw($payload, ['review_page_json' => self::BAD]);
                $bad[] = $payload;
            } else {
                $valid[] = $payload->fresh();
            }
        }

        return [$valid, $bad];
    }

    private function assertListsExactly(string $xml, array $valid, array $bad): void
    {
        $this->assertSame(count($valid), substr_count($xml, '<loc>'));
        foreach ($valid as $payload) {
            $this->assertStringContainsString('/review/' . $payload->review_slug . '</loc>', $xml);
        }
        foreach ($bad as $payload) {
            $this->assertStringNotContainsString('/review/' . $payload->review_slug . '<', $xml);
        }
    }

    // ---- no SQL JSON -----------------------------------------------------------------

    public function test_no_public_surface_asks_the_database_to_parse_review_json(): void
    {
        $this->associations();
        [$payloads, $edition] = $this->releasedBestForEdition();   // AU reviews pr_bf_1 .. pr_bf_5
        $this->raw($payloads[3], ['review_page_json' => self::BAD]);
        $validVideo = $this->legacyVideo('B0BF00001');
        $badVideo = $this->legacyVideo('B0BF00003');

        $surfaces = [
            'page, valid review' => fn () => $this->get(self::AU . '/review/' . $payloads[1]->review_slug)->assertOk(),
            'page, unevaluable review' => fn () => $this->get(self::AU . '/review/' . $payloads[3]->review_slug)->assertNotFound(),
            'sitemap' => fn () => $this->get(self::AU . '/sitemap.xml')->assertOk(),
            'legacy redirect, valid review' => fn () => $this->get(self::AU . '/review/' . $validVideo)->assertStatus(301),
            'legacy redirect, unevaluable review' => fn () => $this->get(self::AU . '/review/' . $badVideo)->assertNotFound(),
            'Best For publication gate' => fn () => $this->assertStringContainsString('Review pr_bf_3 does not pass public-release verification', $this->bestForErrors($edition)),
        ];

        foreach ($surfaces as $label => $surface) {
            $this->assertNoJsonParsing($this->selectsOf($surface), $label);
        }
    }

    // ---- query shape -----------------------------------------------------------------

    public function test_the_sql_gates_run_once_per_chunk_not_once_per_row(): void
    {
        $this->assertSame(200, PublicReleaseVerifier::SET_CHUNK);

        // 100 valid released rows plus 3 unevaluable ones (first, middle and last by key).
        [$valid, $bad] = $this->releasedRows('pr_shape_a', 103, [0, 50, 102]);
        $xml = '';
        $before = $this->selectsOf(function () use (&$xml) {
            $xml = $this->get(self::AU . '/sitemap.xml')->assertOk()->getContent();
        });
        $this->assertListsExactly($xml, $valid, $bad);
        $this->assertNoJsonParsing($before, 'sitemap, 103 rows');
        $this->assertCount(1, array_filter($before, fn ($sql) => self::isSqlGate($sql)));

        // 100 more valid rows, and one more unevaluable row on the chunk boundary
        // (absolute position 199: the last row of the first chunk of 200).
        [$moreValid, $moreBad] = $this->releasedRows('pr_shape_b', 101, [96]);
        $this->assertSame(204, PublishedReviewPayload::query()->count());
        $after = $this->selectsOf(function () use (&$xml) {
            $xml = $this->get(self::AU . '/sitemap.xml')->assertOk()->getContent();
        });
        $this->assertListsExactly($xml, array_merge($valid, $moreValid), array_merge($bad, $moreBad));
        $this->assertNoJsonParsing($after, 'sitemap, 204 rows');
        $this->assertCount(2, array_filter($after, fn ($sql) => self::isSqlGate($sql)));

        // +101 rows: one more chunk (SQL gates, editorial records, active media) - not ~100
        // more statements of any kind.
        $this->assertLessThanOrEqual(3, count($after) - count($before));
    }

    public function test_e8_reads_editorial_records_and_media_once_per_chunk(): void
    {
        $this->associations();
        [$plain] = $this->releasedRows('pr_e8', 40, []);
        $withMedia = [];
        foreach ([1, 2] as $n) {
            $payload = $this->ingest($this->reviewRow('pr_e8_media_' . $n));
            $this->confirm($payload);
            $this->registry()->stage($this->videoAsset('vimeo:88800' . $n), MediaAttachment::OWNER_REVIEW, $payload->published_review_id, 'video', null);
            $this->qaPass($payload);
            $this->release($payload);
            $withMedia[] = $payload->fresh();
        }

        $verifier = app(PublicReleaseVerifier::class);
        $one = $this->selectsOf(fn () => $this->assertCount(1, $verifier->verifyMany([$withMedia[0]], null)));
        $all = $this->selectsOf(function () use ($verifier, $plain, $withMedia) {
            $verified = $verifier->verifyMany(array_merge($plain, $withMedia), null);
            $this->assertCount(42, $verified);
            $this->assertCount(1, $verified->last()->media, 'batched media still verify per review');
        });

        // SQL gates, editorial records, active media, their assets - for one review or 42.
        $this->assertCount(4, $one);
        $this->assertCount(4, $all);
    }

    // ---- same object -----------------------------------------------------------------

    public function test_a_raw_write_after_the_load_never_reaches_sql_and_only_affects_the_next_verification(): void
    {
        $this->associations();
        $target = $this->releasedReview('pr_same_object');
        $neighbour = $this->releasedReview('pr_same_object_valid');
        $original = DB::table('published_review_payloads')->where('id', $target->id)->value('review_page_json');

        // A raw writer corrupts the row the moment the SQL gates have loaded it - on the
        // request's own connection, so every later statement of this request sees the write
        // whatever the isolation level: the worst case for check-then-use.
        $corruptAfterLoad = function () use ($target) {
            $armed = true;
            DB::listen(function (QueryExecuted $query) use (&$armed, $target) {
                if ($armed && self::isSqlGate($query->sql)) {
                    $armed = false;
                    DB::table('published_review_payloads')->where('id', $target->id)->update(['review_page_json' => self::BAD]);
                }
            });
        };
        $stored = fn () => DB::table('published_review_payloads')->where('id', $target->id)->value('review_page_json');

        // The page: this request verifies and renders the object it loaded.
        $corruptAfterLoad();
        $selects = $this->selectsOf(fn () => $this->get(self::AU . '/review/' . $target->review_slug)
            ->assertOk()->assertSee('A solid pick for busy owners.'));
        $this->assertSame(self::BAD, $stored(), 'the write landed during the request');
        $this->assertNoJsonParsing($selects, 'page during a raw write');

        // The next verification loads the corrupted row and refuses it structurally.
        $this->get(self::AU . '/review/' . $target->review_slug)->assertNotFound();
        $this->assertNull(app(PublicReleaseVerifier::class)->verify($target->fresh(), null));
        $this->assertFalse($this->sitemapLists(self::AU, $target->review_slug));
        $this->assertTrue($this->sitemapLists(self::AU, $neighbour->review_slug));

        // The sitemap: the same, for a collection surface.
        DB::table('published_review_payloads')->where('id', $target->id)->update(['review_page_json' => $original]);
        $this->get(self::AU . '/review/' . $target->review_slug)->assertOk();
        $corruptAfterLoad();
        $xml = '';
        $selects = $this->selectsOf(function () use (&$xml) {
            $xml = $this->get(self::AU . '/sitemap.xml')->assertOk()->getContent();
        });
        $this->assertSame(self::BAD, $stored());
        $this->assertNoJsonParsing($selects, 'sitemap during a raw write');
        $this->assertStringContainsString('/review/' . $target->review_slug . '</loc>', $xml, 'verified as loaded');
        $this->assertStringContainsString('/review/' . $neighbour->review_slug . '</loc>', $xml);

        $this->assertFalse($this->sitemapLists(self::AU, $target->review_slug), 'the next sitemap refuses it');
        $this->assertTrue($this->sitemapLists(self::AU, $neighbour->review_slug));
    }
}
