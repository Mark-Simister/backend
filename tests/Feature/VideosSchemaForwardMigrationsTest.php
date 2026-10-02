<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The two forward migrations that close the known `videos` schema gaps.
 *
 * `review` is read by the payload pipeline and written by the tests but was never
 * created by any migration, on a fresh database or on staging. `status` is declared
 * NOT NULL by the migration that adds it, yet staging holds it nullable. Both are
 * fixed forward, and both have to hold in two directions: a fresh database must end
 * up with the intended contract, and an environment that already diverges must be
 * reconciled without losing what is already there.
 */
class VideosSchemaForwardMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private function reviewMigration(): object
    {
        return require database_path('migrations/2026_10_02_100000_add_review_to_videos_table.php');
    }

    private function statusMigration(): object
    {
        return require database_path('migrations/2026_10_02_100100_reconcile_status_nullability_on_videos_table.php');
    }

    /** The database's own view of one `videos` column. */
    private function column(string $name): ?array
    {
        foreach (Schema::getColumns('videos') as $column) {
            if ($column['name'] === $name) {
                return $column;
            }
        }

        return null;
    }

    /** Defaults come back quoted on some drivers; compare the value, not the quoting. */
    private function defaultOf(string $name): ?string
    {
        $default = $this->column($name)['default'] ?? null;

        return $default === null ? null : trim($default, "'\"");
    }

    /**
     * The smallest row `videos` will accept, derived from the schema itself rather
     * than hard-coded, so this does not drift when columns are added elsewhere.
     */
    private function minimalVideoRow(): array
    {
        $row = [];

        foreach (Schema::getColumns('videos') as $column) {
            if ($column['nullable'] || $column['auto_increment'] || $column['default'] !== null) {
                continue;
            }

            $row[$column['name']] = str_contains($column['type_name'], 'int')
                || str_contains($column['type_name'], 'decimal')
                || str_contains($column['type_name'], 'float')
                || str_contains($column['type_name'], 'double')
                ? 0
                : 'fixture';
        }

        return $row;
    }

    // ---- review ----------------------------------------------------------

    public function test_a_fresh_database_has_a_nullable_review_column(): void
    {
        $review = $this->column('review');

        $this->assertNotNull($review, 'a fresh migrate should create videos.review');
        $this->assertTrue($review['nullable'], 'videos.review should be nullable');
        $this->assertNull($review['default'], 'videos.review should have no default');
    }

    public function test_the_review_column_carries_no_index_or_unique_constraint(): void
    {
        $indexed = collect(Schema::getIndexes('videos'))
            ->contains(fn ($index) => in_array('review', $index['columns'], true));

        $this->assertFalse($indexed, 'videos.review should carry no index or unique constraint');
    }

    public function test_the_review_migration_is_a_no_op_when_the_column_already_exists(): void
    {
        // The fresh migrate has already created it, so this is the pre-existing-column path.
        $this->assertTrue(Schema::hasColumn('videos', 'review'));

        $before = $this->column('review');

        $this->reviewMigration()->up();

        $this->assertTrue(Schema::hasColumn('videos', 'review'), 'the column must survive a second run');
        $this->assertSame($before, $this->column('review'), 'the guard must prevent any second addition');
    }

    public function test_review_column_round_trips_structured_json_data(): void
    {
        $row = $this->minimalVideoRow();
        $row['review'] = json_encode(['quick_verdict' => ['beastie_take' => 'good']]);

        $id = DB::table('videos')->insertGetId($row);

        $stored = json_decode(DB::table('videos')->where('id', $id)->value('review'), true);

        $this->assertSame(['quick_verdict' => ['beastie_take' => 'good']], $stored);
    }

    // ---- status ----------------------------------------------------------

    public function test_a_fresh_database_has_a_not_null_status_defaulting_to_draft(): void
    {
        $status = $this->column('status');

        $this->assertNotNull($status);
        $this->assertFalse($status['nullable'], 'a fresh migrate should leave videos.status NOT NULL');
        $this->assertSame('draft', $this->defaultOf('status'));
    }

    public function test_the_reconciliation_makes_a_nullable_status_not_null_and_preserves_rows(): void
    {
        // Staging-like: status nullable, enum and default intact, no NULL rows.
        $this->statusMigration()->down();
        $this->assertTrue($this->column('status')['nullable'], 'the fixture should start nullable');

        $row = $this->minimalVideoRow();
        $row['status'] = 'published';
        $id = DB::table('videos')->insertGetId($row);

        $this->assertSame(0, DB::table('videos')->whereNull('status')->count(), 'no NULL rows before reconciliation');

        $this->statusMigration()->up();

        $status = $this->column('status');
        $this->assertFalse($status['nullable'], 'status should be NOT NULL after reconciliation');
        $this->assertSame('draft', $this->defaultOf('status'), 'the default must be unchanged');
        $this->assertSame('published', DB::table('videos')->where('id', $id)->value('status'), 'existing data must survive');
    }

    public function test_the_status_down_path_restores_nullable_without_changing_the_default(): void
    {
        $this->statusMigration()->down();

        $status = $this->column('status');
        $this->assertTrue($status['nullable'], 'down() should restore nullability');
        $this->assertSame('draft', $this->defaultOf('status'), 'down() must not change the default');
    }
}
