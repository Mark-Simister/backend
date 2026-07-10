<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\PublishedReviewPayload;

/**
 * Imports the Published_Review_Payloads (via extract_published_payloads.py →
 * published_review_payloads.json) into the published_review_payloads table.
 * Idempotent, keyed by published_review_id. Re-run after the pipeline adds
 * or updates rows in the sheet.
 */
class PublishedReviewPayloadSeeder extends Seeder
{
    /** Sheet columns that must land as a MySQL-compatible DATETIME. */
    private const DATE_COLUMNS = ['created_at', 'updated_at'];

    /** Decimal columns (MySQL DECIMAL). */
    private const FLOAT_COLUMNS = ['final_beastie_score', 'public_score'];

    /** Whole-number columns (MySQL UNSIGNED INT). */
    private const INT_COLUMNS = ['public_rating_count', 'analysed_evidence_count', 'source_count'];

    public function run(): void
    {
        $path = database_path('seeders/data/published_review_payloads.json');
        if (! is_file($path)) {
            $this->command?->warn("PublishedReviewPayloadSeeder: {$path} not found — run extract_published_payloads.py first.");
            return;
        }
        $rows = json_decode(file_get_contents($path), true) ?: [];
        if (! $rows) {
            $this->command?->warn('PublishedReviewPayloadSeeder: no payloads to import.');
            return;
        }

        $force = (bool) ($this->command?->option('force') ?? false);
        $count = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            if (empty($r['published_review_id']) || empty($r['review_slug'])) {
                continue;
            }

            $existing = PublishedReviewPayload::where('published_review_id', $r['published_review_id'])->first();

            // A withdrawn page was taken down deliberately. updateOrCreate() would
            // overwrite publish_status from the sheet and put it straight back on the
            // public web. A takedown is a public-safety action; a routine import must
            // not undo it. Forward transitions (→ published) still flow from the sheet.
            if ($existing && $existing->publish_status === 'withdrawn' && ! $force) {
                $this->command?->warn("  skipped {$existing->review_slug}: withdrawn (re-run with --force to resurrect)");
                $skipped++;
                continue;
            }

            // Normalise raw sheet values that SQLite tolerated but MySQL strict
            // mode rejects (ISO-8601 datetimes, stringy numerics). The *_json
            // fields stay decoded arrays; the model's array casts re-encode them.
            PublishedReviewPayload::updateOrCreate(
                ['published_review_id' => $r['published_review_id']],
                $this->sanitize($r)
            );
            $count++;
        }

        $this->command?->info("PublishedReviewPayloadSeeder: imported {$count} payload(s)"
            . ($skipped ? ", skipped {$skipped} withdrawn." : '.'));
    }

    /**
     * Convert raw sheet values into MySQL-strict-mode-safe types, preserving the
     * pipeline's actual values (never falling back to Laravel's auto 'now').
     */
    private function sanitize(array $r): array
    {
        // Datetimes: the sheet emits ISO 8601 with milliseconds + 'Z'
        // (e.g. 2026-07-09T03:13:31.983Z). SQLite stored it verbatim; MySQL's
        // DATETIME strict parsing rejects it. Reformat to 'Y-m-d H:i:s' while
        // keeping the original instant. Null/blank stays null (NOT 'now').
        foreach (self::DATE_COLUMNS as $col) {
            if (! array_key_exists($col, $r)) {
                continue;
            }
            if ($r[$col] === null || $r[$col] === '') {
                $r[$col] = null;
                continue;
            }
            try {
                $r[$col] = Carbon::parse($r[$col])->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $r[$col] = null; // unparseable → null, never a fabricated timestamp
            }
        }

        // Decimals: coerce to float, else null — so a stray '', '4.7 ' or 'N/A'
        // from the sheet can't trip strict numeric parsing on DECIMAL columns.
        foreach (self::FLOAT_COLUMNS as $col) {
            if (array_key_exists($col, $r)) {
                $r[$col] = is_numeric($r[$col]) ? (float) $r[$col] : null;
            }
        }

        // Whole numbers: coerce to int (rounding e.g. 7763.0), else null.
        foreach (self::INT_COLUMNS as $col) {
            if (array_key_exists($col, $r)) {
                $r[$col] = is_numeric($r[$col]) ? (int) round((float) $r[$col]) : null;
            }
        }

        return $r;
    }
}
