<?php

namespace App\Console\Commands;

use App\Models\PublishedReviewPayload;
use App\Models\Video;
use App\Services\ReviewPayloadPublisher;
use Illuminate\Console\Command;

/**
 * Reconciles every admin-authored payload's region pivot with its video's regions.
 *
 * Region changes reach the payload through VideoRegionUpdater → RefreshSeoPayloadJob.
 * If a seeder rewrites `video_region` directly, or a job is lost, the public page can
 * end up serving in regions the video no longer targets. This repairs that.
 *
 * A payload whose video has no regions is withdrawn: an admin payload must never have an
 * empty pivot, because "empty pivot" means ALL regions for pipeline back-compatibility.
 */
class SyncPayloadRegions extends Command
{
    protected $signature = 'reviews:sync-payload-regions {--dry-run : Report drift without writing}';

    protected $description = 'Re-sync admin payload regions from their videos (repairs seeder/queue drift)';

    public function handle(ReviewPayloadPublisher $publisher): int
    {
        $dry = (bool) $this->option('dry-run');
        $payloads = PublishedReviewPayload::where('source', 'admin')->whereNotNull('video_id')->get();

        if ($payloads->isEmpty()) {
            $this->info('No admin-sourced payloads. Nothing to reconcile.');

            return self::SUCCESS;
        }

        $drifted = 0;

        foreach ($payloads as $payload) {
            $video = Video::find($payload->video_id);

            if (! $video) {
                $this->warn("payload #{$payload->id} ({$payload->review_slug}): video {$payload->video_id} is gone");
                continue;
            }

            $want = $video->regions()->pluck('regions.region_code')->sort()->values()->all();
            $have = $payload->regions()->pluck('region_code')->sort()->values()->all();

            if ($want === $have) {
                continue;
            }

            $drifted++;
            $this->line(sprintf(
                '%s: payload [%s] → video [%s]%s',
                $payload->review_slug,
                implode(',', $have) ?: '-',
                implode(',', $want) ?: '-',
                $dry ? '  (dry run)' : ''
            ));

            if (! $dry) {
                // refresh() withdraws when the video has no regions, and re-snapshots
                // otherwise — the same path the queue takes, so behaviour cannot diverge.
                $publisher->refresh($video);
            }
        }

        $this->info($drifted === 0
            ? "All {$payloads->count()} admin payload(s) already match their video's regions."
            : ($dry ? "{$drifted} payload(s) have drifted. Re-run without --dry-run to repair."
                    : "Reconciled {$drifted} payload(s)."));

        return self::SUCCESS;
    }
}
