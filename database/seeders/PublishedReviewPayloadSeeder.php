<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PublishedReviewPayload;

/**
 * Imports the Published_Review_Payloads (via extract_published_payloads.py →
 * published_review_payloads.json) into the published_review_payloads table.
 * Idempotent, keyed by published_review_id. Re-run after the pipeline adds
 * or updates rows in the sheet.
 */
class PublishedReviewPayloadSeeder extends Seeder
{
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

        $count = 0;
        foreach ($rows as $r) {
            if (empty($r['published_review_id']) || empty($r['review_slug'])) {
                continue;
            }
            // The *_json fields arrive as decoded arrays; the model's array casts
            // re-encode them on save. Scalars pass through untouched.
            PublishedReviewPayload::updateOrCreate(
                ['published_review_id' => $r['published_review_id']],
                $r
            );
            $count++;
        }

        $this->command?->info("PublishedReviewPayloadSeeder: imported {$count} payload(s).");
    }
}
