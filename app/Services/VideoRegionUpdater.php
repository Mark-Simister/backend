<?php

namespace App\Services;

use App\Jobs\RefreshSeoPayloadJob;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

/**
 * Applies an admin video edit and its region selection as ONE committed unit.
 *
 * Why this exists (H-1). The naive sequence was:
 *
 *     $video->update($attributes);        // VideoObserver::saved() dispatches a refresh
 *     $video->regions()->sync($ids);      // ...pivot written AFTER that job was queued
 *     RefreshSeoPayloadJob::dispatch(...) // second refresh, to catch the pivot write
 *
 * Under Queue::fake() or the sync driver nothing interleaves, so this looked correct.
 * Under a real supervised worker the first job can be picked up BETWEEN the update and
 * the sync, and snapshot the payload against the OLD region set. The second job normally
 * corrects it — but if it fails, the page stays published to a region the admin just
 * removed. Clearing every region is the worst case: the first job sees the old regions
 * and keeps the page live.
 *
 * The fix needs both halves, and neither works alone:
 *
 *   1. this transaction, so the update and the pivot commit together; and
 *   2. 'after_commit' => true on the database queue connection, so no job — including
 *      the one the observer dispatches from inside update() — becomes visible to a
 *      worker until that commit lands.
 *
 * Without (2), the transaction makes it worse: jobs would enqueue mid-transaction and
 * read pre-commit state. Without (1), (2) has no transaction to wait for.
 *
 * Both refreshes are idempotent: RefreshSeoPayloadJob carries only the video id and
 * re-reads committed state, so whichever runs last writes the same snapshot.
 */
class VideoRegionUpdater
{
    /** @param  array<int|string>  $regionIds  empty array clears every region */
    public function update(Video $video, array $attributes, array $regionIds): Video
    {
        return DB::transaction(function () use ($video, $attributes, $regionIds) {
            $video->update($attributes);
            $video->regions()->sync($regionIds);

            // Pivot writes fire no model events, so VideoObserver cannot see a region
            // change. No-ops unless this video was explicitly published to SEO; withdraws
            // it if every region was removed.
            if ($video->isSeoPublished()) {
                RefreshSeoPayloadJob::dispatch($video->id);
            }

            return $video;
        });
    }
}
