<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The guards on 2025_08_17_060945_add_extra_fields_to_videos_table.
 *
 * Six of that migration's seven columns already exist on the long-lived staging
 * database while the migration is still Pending, so an unguarded run dies on the
 * first duplicate column and never reaches the later migrations. The guards have
 * to hold both ends: a fresh database must still receive all seven, and a
 * staging-like database must receive only the one it is missing, without the
 * migration touching the six that are already there.
 */
class VideosExtraFieldsMigrationGuardTest extends TestCase
{
    use RefreshDatabase;

    /** The seven columns the migration actively adds. Commented-out ones are not its behaviour. */
    private const COLUMNS = [
        'tags',
        'rating_type',
        'sponsorship_type',
        'highlight_tags',
        'auto_tags',
        'raw_video_file',
        'status',
    ];

    /** The six a staging-like database already has. */
    private const PRE_EXISTING = [
        'tags',
        'rating_type',
        'sponsorship_type',
        'highlight_tags',
        'auto_tags',
        'status',
    ];

    private function migration(): object
    {
        return require database_path('migrations/2025_08_17_060945_add_extra_fields_to_videos_table.php');
    }

    /** Name, type, nullability and default for the named columns, as the database reports them. */
    private function columnState(array $columns): array
    {
        $state = [];

        foreach (Schema::getColumns('videos') as $column) {
            if (in_array($column['name'], $columns, true)) {
                $state[$column['name']] = [
                    'type' => $column['type'],
                    'nullable' => $column['nullable'],
                    'default' => $column['default'],
                ];
            }
        }

        ksort($state);

        return $state;
    }

    public function test_a_fresh_database_receives_all_seven_columns(): void
    {
        foreach (self::COLUMNS as $column) {
            $this->assertTrue(
                Schema::hasColumn('videos', $column),
                "a fresh migrate should create videos.{$column}"
            );
        }
    }

    public function test_a_fresh_status_column_keeps_its_intended_not_null_definition(): void
    {
        $status = $this->columnState(['status'])['status'] ?? null;

        $this->assertNotNull($status, 'videos.status should exist after a fresh migrate');
        $this->assertFalse($status['nullable'], 'a fresh migrate should create videos.status NOT NULL');
    }

    public function test_a_staging_like_database_gains_only_the_missing_column(): void
    {
        // Staging-like: the other six are already present, raw_video_file is not.
        Schema::table('videos', function ($table) {
            $table->dropColumn('raw_video_file');
        });

        $this->assertFalse(
            Schema::hasColumn('videos', 'raw_video_file'),
            'the staging-like fixture should start without raw_video_file'
        );

        $before = $this->columnState(self::PRE_EXISTING);

        $this->migration()->up();

        $this->assertTrue(
            Schema::hasColumn('videos', 'raw_video_file'),
            'the guarded migration should add the one column that is missing'
        );

        $this->assertSame(
            $before,
            $this->columnState(self::PRE_EXISTING),
            'the guarded migration must leave the six pre-existing columns untouched'
        );
    }

    public function test_the_guarded_migration_does_not_change_status_nullability(): void
    {
        // The known staging divergence is that status permits NULL there. This
        // migration must not quietly reconcile it in either direction.
        Schema::table('videos', function ($table) {
            $table->dropColumn('raw_video_file');
        });

        $before = $this->columnState(['status'])['status'];

        $this->migration()->up();

        $this->assertSame(
            $before,
            $this->columnState(['status'])['status'],
            'the guarded migration must not alter videos.status'
        );
    }
}
