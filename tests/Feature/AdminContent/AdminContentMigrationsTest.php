<?php

namespace Tests\Feature\AdminContent;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The seven additive Sprint-A migrations are exactly reversible and re-runnable, on
 * whichever connection the suite runs against (SQLite in memory, or the isolated MySQL
 * 8.0.46 qualification schema). Uses DatabaseMigrations because MySQL DDL cannot run
 * inside the RefreshDatabase transaction.
 */
class AdminContentMigrationsTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATIONS = [
        '2026_10_06_100000_create_media_assets_table',
        '2026_10_06_100100_create_media_attachments_table',
        '2026_10_06_100200_create_review_editorial_records_table',
        '2026_10_06_100300_create_admin_audit_events_table',
        '2026_10_06_100400_add_content_fingerprint_to_published_review_payloads_table',
        '2026_10_06_100500_add_admin_content_fields_to_channels_table',
        '2026_10_06_100600_add_admin_content_fields_to_characters_table',
    ];

    private function assertSprintASchema(bool $present): void
    {
        foreach (['media_assets', 'media_attachments', 'review_editorial_records', 'admin_audit_events'] as $table) {
            $this->assertSame($present, Schema::hasTable($table), $table);
        }
        foreach ([
            'published_review_payloads' => ['content_fingerprint', 'content_fingerprint_scheme'],
            'channels' => ['bio', 'category_label', 'pipeline_code'],
            'characters' => ['insights', 'production_profile', 'pipeline_code'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertSame($present, Schema::hasColumn($table, $column), "{$table}.{$column}");
            }
        }
    }

    public function test_exactly_seven_migrations_are_the_latest_batch_and_roll_back_cleanly(): void
    {
        $latest = DB::table('migrations')->orderByDesc('id')->limit(7)->pluck('migration')->sort()->values()->all();
        $this->assertSame(self::MIGRATIONS, $latest);
        $this->assertSprintASchema(true);

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 7]));
        $this->assertSprintASchema(false);
        $this->assertTrue(Schema::hasTable('published_review_payloads'), 'pre-existing tables are untouched');
        $this->assertTrue(Schema::hasColumn('published_review_payloads', 'review_page_json'));

        // Re-migrating leaves the seven as the last batch, which is all the DatabaseMigrations
        // teardown rolls back: no historic migration's down() is exercised.
        $this->assertSame(0, Artisan::call('migrate'));
        $this->assertSprintASchema(true);
    }
}
