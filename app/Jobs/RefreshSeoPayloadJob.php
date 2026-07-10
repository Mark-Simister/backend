<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\ReviewPayloadPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-snapshots an ALREADY SEO-published video after an admin edit, off the
 * request cycle. Never creates the first payload — ReviewPayloadPublisher::refresh()
 * no-ops until the explicit "Publish to SEO" action has run.
 *
 * Carries the id (not the model) so a stale serialized snapshot can't be used.
 */
class RefreshSeoPayloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $videoId) {}

    public function handle(ReviewPayloadPublisher $publisher): void
    {
        $video = Video::find($this->videoId);
        if (! $video) {
            return;   // deleted between dispatch and handling
        }

        $publisher->refresh($video);
    }
}
