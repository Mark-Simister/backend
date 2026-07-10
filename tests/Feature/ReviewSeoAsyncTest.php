<?php

namespace Tests\Feature;

use App\Jobs\RefreshSeoPayloadJob;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\Video;
use App\Services\ReviewPayloadPublisher;
use App\Services\VideoRegionUpdater;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * H-1 — the video update, the region pivot write and the refresh dispatch are one
 * committed unit.
 *
 * These tests run against the REAL database queue driver and REAL transactions. That is
 * the entire point: under Queue::fake() or the sync driver nothing can interleave, so the
 * race this guards against is invisible.
 *
 * The `jobs` table is inspected at the instant the video's `saved` event fires — the
 * moment VideoObserver dispatches a refresh, and the moment at which the region pivot has
 * not yet been written. A job row visible there is a job a worker could have picked up.
 */
class ReviewSeoAsyncTest extends TestCase
{
    /**
     * Deliberately NOT RefreshDatabase: it holds an open transaction for the whole test,
     * so after_commit callbacks would never fire and every assertion here would be a lie.
     * migrate:fresh drops and re-migrates without invoking any down().
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');

        config(['queue.default' => 'database']);   // phpunit.xml pins sync
    }

    // ───────────────────────── scaffolding ─────────────────────────

    private function region(string $code): Region
    {
        $r = Region::firstOrNew(['region_code' => $code]);
        $r->forceFill(['region_name' => $code, 'is_active' => 1, 'currency' => 'USD', 'currency_symbol' => '$'])->save();

        return $r;
    }

    private function makeVideo(array $regions = ['AU']): Video
    {
        $char = Character::firstOrNew(['name' => 'Hound Dog Hank']);
        $char->forceFill(['name' => 'Hound Dog Hank'])->save();
        $chan = Channel::firstOrNew(['name' => 'BarkTastic']);
        $chan->forceFill(['name' => 'BarkTastic'])->save();
        $cat = Category::firstOrNew(['name' => 'Dog Apparel']);
        $cat->forceFill(['name' => 'Dog Apparel', 'slug' => 'dog-apparel'])->save();

        $v = new Video();
        $v->forceFill([
            'title' => 'Vecomfy Fleece Dog Hoodie — Review',
            'type' => 'youtube',
            'video_url' => '',
            'character_id' => $char->id,
            'channel_id' => $chan->id,
            'category_id' => $cat->id,
            'product_name' => 'Vecomfy Fleece Dog Hoodie',
            'product_asin_sku' => 'B07HCB1JPS',
            'review_type' => 'review',
            'status' => 'published',
            'final_beastie_score' => 4.7,
            'public_rating' => 4.5,
            'review' => ['quick_verdict' => ['beastie_take' => 'good']],
        ])->save();

        $v->regions()->sync(collect($regions)->map(fn ($c) => $this->region($c)->id)->all());

        return $v->fresh();
    }

    /** A video already live on the public site, in AU only. */
    private function livePublishedVideo(): Video
    {
        $v = $this->makeVideo(['AU']);
        app(ReviewPayloadPublisher::class)->publish($v);

        return $v->fresh();
    }

    private function updater(): VideoRegionUpdater
    {
        return app(VideoRegionUpdater::class);
    }

    private function work(): void
    {
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0]);
    }

    private function payloadRegions(): array
    {
        return PublishedReviewPayload::first()->regions()->pluck('region_code')->sort()->values()->all();
    }

    // ───────────────────────── the configuration itself ─────────────────────────

    public function test_the_database_queue_defers_dispatch_until_commit(): void
    {
        $this->assertTrue(config('queue.connections.database.after_commit'));
    }

    // ───────── P-6: the renderer's null queue discards without executing ─────────

    public function test_a_null_queue_connection_is_defined(): void
    {
        $this->assertSame('null', config('queue.connections.null.driver'));
    }

    /**
     * review-bstg runs QUEUE_CONNECTION=null on a SELECT-only account. `sync` would not
     * do: it runs the job inline and the job's body writes. `null` must discard the
     * dispatch — no jobs row, no application write, no invalid-connection exception.
     */
    public function test_the_null_queue_discards_the_job_without_executing_it(): void
    {
        $v = $this->livePublishedVideo();
        $before = PublishedReviewPayload::first()->updated_at;

        config(['queue.default' => 'null']);

        RefreshSeoPayloadJob::dispatch($v->id);   // must not throw

        $this->assertSame(0, DB::table('jobs')->count(), 'null must not write to jobs');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertEquals($before, PublishedReviewPayload::first()->updated_at, 'the job must never execute');
    }

    /** `.env`'s bare `null` is cast to PHP null by Env, so queue.default arrives as null. */
    public function test_a_php_null_queue_default_also_resolves_to_the_null_driver(): void
    {
        $v = $this->livePublishedVideo();
        $before = PublishedReviewPayload::first()->updated_at;

        config(['queue.default' => null]);

        RefreshSeoPayloadJob::dispatch($v->id);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertEquals($before, PublishedReviewPayload::first()->updated_at);
    }

    // ───────── no job may be observable before the pivot is synchronised ─────────

    public function test_the_observers_refresh_is_not_visible_before_the_transaction_commits(): void
    {
        $v = $this->livePublishedVideo();

        DB::transaction(function () use ($v) {
            $v->update(['product_name' => 'Renamed Product']);   // VideoObserver dispatches here

            $this->assertSame(0, DB::table('jobs')->count(),
                'a refresh dispatched by the observer must not be visible to a worker mid-transaction');
        });

        $this->assertGreaterThan(0, DB::table('jobs')->count(), 'and it must appear once committed');
    }

    /**
     * The controller path: NO ambient transaction. We probe from inside the video's own
     * `saved` event — the exact instant VideoObserver dispatches a refresh, and the exact
     * instant at which the region pivot has not yet been written.
     *
     * Wrapping the updater in the test's own DB::transaction would prove nothing about the
     * updater: the outer transaction would defer the jobs by itself.
     */
    public function test_no_refresh_job_can_observe_the_update_before_regions_are_synced(): void
    {
        $v = $this->livePublishedVideo();
        $uk = $this->region('UK');

        $levelDuringSave = null;
        $jobsDuringSave = null;
        Event::listen('eloquent.saved: ' . Video::class, function () use (&$levelDuringSave, &$jobsDuringSave) {
            $levelDuringSave = DB::transactionLevel();
            $jobsDuringSave = DB::table('jobs')->count();
        });

        $this->assertSame(0, DB::transactionLevel(), 'precondition: the controller has no ambient transaction');

        $this->updater()->update($v, ['product_name' => 'Renamed Product'], [$uk->id]);

        $this->assertGreaterThan(0, $levelDuringSave,
            'the video update and the pivot write must happen inside one transaction');
        $this->assertSame(0, $jobsDuringSave,
            'no refresh job may be visible to a worker while the region pivot is still unwritten');

        $this->assertGreaterThan(0, DB::table('jobs')->count(), 'and the jobs appear once committed');
    }

    // ───────── every queued refresh reads the FINAL committed region set ─────────

    public function test_every_queued_refresh_reads_the_final_committed_region_set(): void
    {
        $v = $this->livePublishedVideo();
        $this->assertSame(['AU'], $this->payloadRegions());

        $uk = $this->region('UK');
        $this->updater()->update($v, ['product_name' => 'Renamed Product'], [$uk->id]);

        // observer + explicit → two jobs, both released by the same commit
        $this->assertSame(2, DB::table('jobs')->count());

        $this->work();

        $this->assertSame(['UK'], $this->payloadRegions());
        $this->assertSame('published', PublishedReviewPayload::first()->publish_status);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    // ───────── clearing all regions can never leave the old set live ─────────

    public function test_clearing_all_regions_withdraws_and_never_serves_the_previous_region_set(): void
    {
        $v = $this->livePublishedVideo();
        $this->assertSame('published', PublishedReviewPayload::first()->publish_status);

        // Worst case for H-1: at `saved` the pivot still holds AU. A refresh visible here
        // would re-snapshot the page as live in a region the admin just removed.
        $jobsDuringSave = null;
        $auStillAttached = null;
        Event::listen('eloquent.saved: ' . Video::class, function () use ($v, &$jobsDuringSave, &$auStillAttached) {
            $jobsDuringSave = DB::table('jobs')->count();
            $auStillAttached = $v->regions()->count();
        });

        $this->updater()->update($v, ['product_name' => 'Renamed Product'], []);   // every region removed

        $this->assertSame(1, $auStillAttached, 'precondition: the old pivot row is still present at save time');
        $this->assertSame(0, $jobsDuringSave, 'no job may be queued while the stale region is still attached');

        $this->work();

        $payload = PublishedReviewPayload::first();
        $this->assertSame('withdrawn', $payload->publish_status,
            'a video with no regions must not remain publicly renderable');
        $this->assertSame('withdrawn', $v->fresh()->seo_publish_status);
    }

    // ───────── two refreshes for the same commit are idempotent ─────────

    public function test_two_refresh_jobs_after_the_same_committed_update_are_idempotent(): void
    {
        $v = $this->livePublishedVideo();
        $uk = $this->region('UK');
        $slug = PublishedReviewPayload::first()->review_slug;

        $this->updater()->update($v, ['product_name' => 'Renamed Product'], [$uk->id]);
        RefreshSeoPayloadJob::dispatch($v->id);   // a third, for good measure

        $this->assertSame(3, DB::table('jobs')->count());

        $this->work();

        $this->assertSame(1, PublishedReviewPayload::count(), 'refreshes must not create duplicate payloads');

        $payload = PublishedReviewPayload::first();
        $this->assertSame($slug, $payload->review_slug, 'the public URL is immutable across refreshes');
        $this->assertSame('published', $payload->publish_status);
        $this->assertSame(['UK'], $this->payloadRegions());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }
}
