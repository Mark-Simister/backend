<?php

namespace Tests\Feature\AdminContent;

use App\Models\PublishedReviewPayload;
use App\Support\Reviews\FingerprintScheme;
use App\Support\Reviews\ProjectionException;
use App\Support\Reviews\RenderProjection;
use App\Support\Reviews\ReviewFingerprints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\TestCase;

/**
 * The P1/A1 render projection and its fingerprint, built from rows actually stored and
 * read back through the model (design record sections 6.2 and 6.6). Raw stored-text
 * variants are written with the query builder, as a bypass writer would.
 */
class RenderProjectionTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;

    private PublishedReviewPayload $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->payload = $this->ingest($this->reviewRow());
    }

    private function contentFp(?string $scheme = FingerprintScheme::P1): ?string
    {
        return ReviewFingerprints::content($this->payload->fresh(), $scheme);
    }

    private function writeRaw(array $columns): void
    {
        DB::table('published_review_payloads')->where('id', $this->payload->id)->update($columns);
    }

    private function mutatePage(callable $mutate): void
    {
        $rp = json_decode(DB::table('published_review_payloads')->where('id', $this->payload->id)->value('review_page_json'), true);
        $mutate($rp);
        $this->writeRaw(['review_page_json' => json_encode($rp)]);
    }

    public function test_ingestion_stores_the_derived_fingerprint_under_the_active_scheme(): void
    {
        $fresh = $this->payload->fresh();

        $this->assertSame(FingerprintScheme::P1, $fresh->content_fingerprint_scheme);
        $this->assertSame($this->contentFp(), $fresh->content_fingerprint);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fresh->content_fingerprint);
    }

    public function test_stored_text_variants_that_decode_identically_collide(): void
    {
        $base = $this->contentFp();
        $rp = json_decode(DB::table('published_review_payloads')->where('id', $this->payload->id)->value('review_page_json'), true);

        // Pretty-printed with escaped slashes and unicode.
        $this->writeRaw(['review_page_json' => json_encode($rp, JSON_PRETTY_PRINT)]);
        $this->assertSame($base, $this->contentFp());

        // An empty object written as {} instead of [] (and back).
        $rp['buyer_fit'] = [];
        $this->writeRaw(['review_page_json' => json_encode($rp)]);
        $withList = $this->contentFp();
        $this->writeRaw(['review_page_json' => str_replace('"buyer_fit":[]', '"buyer_fit":{}', json_encode($rp))]);
        $this->assertSame($withList, $this->contentFp());

        // 4.70 vs 4.7 in the stored text.
        $text = json_encode($rp);
        $this->writeRaw(['review_page_json' => $text]);
        $plain = $this->contentFp();
        $this->writeRaw(['review_page_json' => str_replace('"final_score":4.5', '"final_score":4.50', $text)]);
        $this->assertSame($plain, $this->contentFp());
    }

    public function test_clock_derived_and_unrendered_changes_do_not_change_the_fingerprint(): void
    {
        $base = $this->contentFp();

        $this->writeRaw(['updated_at' => '2026-10-05 23:59:59', 'qa_status' => 'changed', 'project_id' => 'p-2', 'status' => 'x']);
        $this->assertSame($base, $this->contentFp(), 'updated_at and unrendered columns are outside the projection');

        $this->mutatePage(function (array &$rp) {
            $rp['beastiescore']['raw']['calculated_at'] = '2026-10-05T23:59:59Z';
            $rp['beastiescore']['raw']['preserved_at'] = '2026-10-05T23:59:59Z';
            $rp['safety']['safety_age_days'] = 9;
            $rp['editorial_research_report'] = ['anything' => 'unrendered'];
            $rp['qa_meta'] = ['run' => 7];
        });
        $this->assertSame($base, $this->contentFp(), 'beastiescore.raw, safety_age_days and unrendered keys are excluded');
    }

    public function test_rendered_and_identity_changes_change_the_fingerprint(): void
    {
        $cases = [
            'flat h1' => fn () => $this->writeRaw(['h1' => 'Another title']),
            'product_uid' => fn () => $this->writeRaw(['product_uid' => 'PUID_OTHER']),
            'created_at' => fn () => $this->writeRaw(['created_at' => '2026-10-02 10:00:00']),
            'hero title' => fn () => $this->mutatePage(function (array &$rp) { $rp['hero']['title'] = 'Changed'; }),
            'safety_feed_freshness_status' => fn () => $this->mutatePage(function (array &$rp) { $rp['safety']['safety_feed_freshness_status'] = 'stale'; }),
            'nested member order' => fn () => $this->mutatePage(function (array &$rp) { $rp['hero'] = array_reverse($rp['hero'], true); }),
            'array order' => fn () => $this->mutatePage(function (array &$rp) { $rp['pros_cons']['pros'] = ['Small hopper', 'Quiet motor']; }),
            'null vs absent' => fn () => $this->mutatePage(function (array &$rp) { $rp['hero']['subtitle'] = null; }),
            'schema_json_ld' => fn () => $this->writeRaw(['schema_json_ld' => json_encode(['@context' => 'https://schema.org', '@graph' => [['@type' => 'Product', 'name' => 'Other']]])]),
        ];

        foreach ($cases as $label => $mutate) {
            $this->refreshPayloadFromRow();
            $base = $this->contentFp();
            $mutate();
            $this->assertNotSame($base, $this->contentFp(), "{$label} must change the fingerprint");
        }
    }

    public function test_integer_and_float_spellings_of_the_same_number_are_distinguished(): void
    {
        $this->writeRaw(['review_page_json' => str_replace('"final_score":4.5', '"final_score":4', $this->rawPage())]);
        $integer = $this->contentFp();
        $this->writeRaw(['review_page_json' => str_replace('"final_score":4', '"final_score":4.0', $this->rawPage())]);
        $float = $this->contentFp();
        $this->writeRaw(['review_page_json' => str_replace('"final_score":4.0', '"final_score":4.00', $this->rawPage())]);

        $this->assertNotNull($integer);
        $this->assertNotSame($integer, $float, '4 vs 4.0 is preserved');
        $this->assertSame($float, $this->contentFp(), '4.0 vs 4.00 collapses');
    }

    public function test_top_level_page_keys_use_the_scheme_defined_order(): void
    {
        $base = $this->contentFp();
        $this->mutatePage(function (array &$rp) { $rp = array_reverse($rp, true); });

        $this->assertSame($base, $this->contentFp(), 'the projection, not the producer, fixes the top-level order');
        $this->assertSame(
            ['page_identity', 'hero', 'quick_verdict', 'review_facts', 'beastiescore', 'pros_cons', 'video', 'safety', 'disclosure'],
            array_keys(RenderProjection::build($this->payload->fresh(), FingerprintScheme::P1)['review_page_json'])
        );
    }

    public function test_p1_and_a1_differ_for_a_payload_carrying_a_video(): void
    {
        $this->assertNotSame($this->contentFp(FingerprintScheme::P1), $this->contentFp(FingerprintScheme::A1));
    }

    public function test_a1_drops_the_payload_video_and_every_video_object_node(): void
    {
        $a1 = RenderProjection::build($this->payload->fresh(), FingerprintScheme::A1);

        $this->assertArrayNotHasKey('video', $a1['review_page_json']);
        $this->assertSame([['@type' => 'Product', 'name' => 'Test Feeder']], $a1['schema_json_ld']['@graph']);

        // A video-only change is invisible under A1 and visible under P1.
        $p1Before = $this->contentFp(FingerprintScheme::P1);
        $a1Before = $this->contentFp(FingerprintScheme::A1);
        $this->mutatePage(function (array &$rp) { $rp['video']['vimeo_id'] = '987654321'; });
        $this->writeRaw(['schema_json_ld' => json_encode(['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'Product', 'name' => 'Test Feeder'],
            ['@type' => ['VideoObject', 'CreativeWork'], 'name' => 'Other video'],
        ]])]);

        $this->assertSame($a1Before, $this->contentFp(FingerprintScheme::A1));
        $this->assertNotSame($p1Before, $this->contentFp(FingerprintScheme::P1));
    }

    public function test_invalid_or_unloaded_rows_fail_closed(): void
    {
        $this->writeRaw(['review_page_json' => '{"hero": {"title": "broken"']);
        $this->assertNull($this->contentFp(), 'invalid stored JSON yields no fingerprint');

        $this->refreshPayloadFromRow();
        $partial = PublishedReviewPayload::query()->select(['id', 'published_review_id', 'review_page_json'])->find($this->payload->id);
        $this->assertNull(ReviewFingerprints::content($partial, FingerprintScheme::P1), 'a partially loaded row cannot be fingerprinted');

        $this->assertNull(ReviewFingerprints::content($this->payload->fresh(), FingerprintScheme::M1), 'M1 is not a content scheme');
        $this->assertNull(ReviewFingerprints::content($this->payload->fresh(), null));

        $this->expectException(ProjectionException::class);
        RenderProjection::build($this->payload->fresh(), 'br-render-canonical-v1:X9');
    }

    private function rawPage(): string
    {
        return (string) DB::table('published_review_payloads')->where('id', $this->payload->id)->value('review_page_json');
    }

    private function refreshPayloadFromRow(): void
    {
        $this->payload = $this->ingest($this->reviewRow());
    }
}
