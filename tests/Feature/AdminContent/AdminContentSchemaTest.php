<?php

namespace Tests\Feature\AdminContent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema details of the Sprint-A migrations: explicit index names and, on MySQL, the
 * binary collation of the exact-match columns.
 */
class AdminContentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_named_indexes_exist(): void
    {
        $indexes = fn (string $table) => collect(Schema::getIndexes($table))->pluck('name')->all();

        $this->assertContains('media_assets_external_ref_unique', $indexes('media_assets'));
        $this->assertContains('media_assets_provider_asset_unique', $indexes('media_assets'));
        $this->assertContains('media_assets_disk_path_unique', $indexes('media_assets'));
        $this->assertContains('media_att_active_slot_unique', $indexes('media_attachments'));
        $this->assertContains('rer_editorial_state_idx', $indexes('review_editorial_records'));
        $this->assertContains('admin_audit_subject_idx', $indexes('admin_audit_events'));
        $this->assertContains('prp_content_fingerprint_idx', $indexes('published_review_payloads'));
        $this->assertContains('channels_pipeline_code_unique', $indexes('channels'));
        $this->assertContains('characters_pipeline_code_unique', $indexes('characters'));
    }

    public function test_exact_match_columns_are_binary_collated_on_mysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->assertSame('sqlite', DB::getDriverName(), 'SQLite compares text with BINARY collation by default');

            return;
        }

        $collation = fn (string $table, string $column) => collect(Schema::getColumns($table))->firstWhere('name', $column)['collation'] ?? null;

        $this->assertSame('utf8mb4_bin', $collation('media_assets', 'external_ref'));
        $this->assertSame('utf8mb4_bin', $collation('media_assets', 'provider_asset_id'));
        $this->assertSame('utf8mb4_bin', $collation('channels', 'pipeline_code'));
        $this->assertSame('utf8mb4_bin', $collation('characters', 'pipeline_code'));
    }
}
