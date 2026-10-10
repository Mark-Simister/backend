<?php

namespace Tests\Feature\AdminContent;

use App\Models\PublishedReviewPayload;
use App\Services\Reviews\ReviewPayloadIngestor;
use App\Support\Reviews\ReviewFingerprints;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\TestCase;

/**
 * RP-ADMIN-CONTENT-MEDIA-F3-INGEST-FILTER-01: the seven Production OS columns that are
 * deliberately not persisted are removed by the supported ingestion service before the
 * save and before fingerprinting, while any other unknown column still fails loudly.
 */
class ReviewPayloadIngestorSourceFilterTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;

    /** The seven source-only columns, in the Ingestor's order, with sheet-shaped values. */
    private const SOURCE_ONLY = [
        'payload_schema_version' => 'confidence_fields_v7_2026-07-27',
        'public_signal' => 'Very strong',
        'analysis_depth' => 'Standard',
        'analysis_depth_count' => 10.0,
        'score_basis_summary' => 'Based on 28,177 ratings across 5 sources.',
        'buyer_questions_json' => '[{"question":"Is it quiet?"}]',
        'product_facts_json' => '{"capacity":"456 fl oz"}',
    ];

    /** The 36 sheet columns the table was created with (2026_07_09_130000): the tracked-seeder contract. */
    private const SUPPORTED_SHEET_COLUMNS = [
        'published_review_id', 'website_review_id', 'project_id', 'product_uid', 'market_id',
        'language_variant', 'currency_code', 'channel', 'character_id', 'character_name',
        'review_slug', 'canonical_url', 'h1', 'seo_title', 'seo_description', 'og_image_url',
        'hero_image_url', 'final_beastie_score', 'public_score', 'public_rating_count',
        'analysed_evidence_count', 'source_count', 'confidence_tier', 'status', 'publish_status',
        'qa_status', 'qa_errors_json', 'qa_warnings_json', 'review_page_json', 'schema_json_ld',
        'commerce_json', 'sources_json', 'safety_json', 'disclosure_json', 'created_at', 'updated_at',
    ];

    /** Where the authoritative values of the five mirrors live inside review_page_json. */
    private const PAGE_VALUES = [
        'payload_schema_version' => 'confidence_fields_v7_2026-07-27',
        'review_facts' => ['public_signal' => 'Very strong', 'analysis_depth' => 'Standard', 'analysis_depth_count' => 10],
        'beastiescore' => ['score_basis_summary' => 'Based on 28,177 ratings across 5 sources.'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureAdminContent();
    }

    public function test_the_seven_source_only_fields_are_removed_before_persistence(): void
    {
        $this->assertSame(array_keys(self::SOURCE_ONLY), ReviewPayloadIngestor::SOURCE_ONLY_FIELDS);

        $row = $this->reviewRow('pr_src_filter_t1', self::SOURCE_ONLY, self::PAGE_VALUES);
        $this->ingest($row);

        foreach (ReviewPayloadIngestor::SOURCE_ONLY_FIELDS as $field) {
            $this->assertFalse(Schema::hasColumn('published_review_payloads', $field), "{$field} must not be a column");
            $this->assertArrayNotHasKey($field, $this->persisted('pr_src_filter_t1'));
        }

        $this->assertStoredExactly(array_diff_key($row, self::SOURCE_ONLY));

        // The authoritative values of the five mirrors stay inside the stored review_page_json.
        $page = PublishedReviewPayload::query()->where('published_review_id', 'pr_src_filter_t1')->firstOrFail()->review_page_json;
        $this->assertSame('confidence_fields_v7_2026-07-27', $page['payload_schema_version']);
        $this->assertSame('Very strong', $page['review_facts']['public_signal']);
        $this->assertSame('Standard', $page['review_facts']['analysis_depth']);
        $this->assertSame(10, $page['review_facts']['analysis_depth_count']);
        $this->assertSame('Based on 28,177 ratings across 5 sources.', $page['beastiescore']['score_basis_summary']);
    }

    public function test_an_unexpected_eighth_field_still_fails_loudly(): void
    {
        $row = $this->reviewRow('pr_src_filter_t2', self::SOURCE_ONLY + ['rp_unexpected_source_field' => 'must not be dropped']);

        try {
            $this->ingest($row);
            $this->fail('An unknown source field was accepted or silently dropped.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('rp_unexpected_source_field', $e->getMessage());
        }

        $this->assertSame(0, DB::table('published_review_payloads')->where('published_review_id', 'pr_src_filter_t2')->count());
    }

    public function test_removing_the_source_only_fields_does_not_change_the_fingerprint(): void
    {
        $withSeven = $this->reviewRow('pr_src_filter_t3', self::SOURCE_ONLY, self::PAGE_VALUES);
        $withoutSeven = $this->reviewRow('pr_src_filter_t3', [], self::PAGE_VALUES);
        $this->assertSame($withoutSeven, array_diff_key($withSeven, self::SOURCE_ONLY));

        // The same review identity is ingested into a cleared table each time, because the
        // identity columns are part of the render projection.
        $this->ingest($withSeven);
        $a = $this->persisted('pr_src_filter_t3');
        DB::table('published_review_payloads')->where('published_review_id', 'pr_src_filter_t3')->delete();

        $this->ingest($withoutSeven);
        $b = $this->persisted('pr_src_filter_t3');
        unset($a['id'], $b['id']);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $a['content_fingerprint']);
        $this->assertSame($b['content_fingerprint'], $a['content_fingerprint']);
        $this->assertSame($b['content_fingerprint_scheme'], $a['content_fingerprint_scheme']);
        $this->assertSame($b, $a);

        $payload = PublishedReviewPayload::query()->where('published_review_id', 'pr_src_filter_t3')->firstOrFail();
        $this->assertSame($b['content_fingerprint'], ReviewFingerprints::content($payload, $b['content_fingerprint_scheme']));
    }

    public function test_a_row_with_only_previously_supported_columns_ingests_unchanged(): void
    {
        $this->assertSame([], array_values(array_intersect(self::SUPPORTED_SHEET_COLUMNS, ReviewPayloadIngestor::SOURCE_ONLY_FIELDS)));

        $row = $this->reviewRow('pr_src_filter_t4');
        $this->assertSame([], array_values(array_intersect(array_keys($row), ReviewPayloadIngestor::SOURCE_ONLY_FIELDS)));

        $payload = $this->ingest($row);

        $this->assertStoredExactly($row);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $payload->content_fingerprint);
        $this->assertSame(
            $payload->content_fingerprint,
            ReviewFingerprints::content($payload->fresh(), $payload->content_fingerprint_scheme)
        );
    }

    /** @return array<string, mixed> the stored row exactly as the database returns it */
    private function persisted(string $id): array
    {
        return (array) DB::table('published_review_payloads')->where('published_review_id', $id)->first();
    }

    /** Every input column reads back identically through the model casts. */
    private function assertStoredExactly(array $row): void
    {
        $payload = PublishedReviewPayload::query()->where('published_review_id', $row['published_review_id'])->firstOrFail();

        foreach ($row as $column => $value) {
            $this->assertSame($value, $payload->getAttribute($column), "column {$column}");
        }
    }
}
