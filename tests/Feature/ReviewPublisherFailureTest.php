<?php

namespace Tests\Feature;

use App\Exceptions\ReviewPublishGateException;
use App\Jobs\RefreshSeoPayloadJob;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\Video;
use App\Services\ReviewPayloadPublisher;
use App\Support\Reviews\VideoReviewMapper;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * H-2 — expected gate failures vs unexpected failures.
 *
 * Runs against the REAL database queue driver (not Queue::fake, not sync) because the
 * whole point is what a worker does with a throwing job: retry it, then record it in
 * failed_jobs. A faked queue cannot observe any of that.
 *
 * The invariant these tests pin down, and which deployment verification relies on:
 *
 *   gate / data-quality failure  →  videos.seo_publish_status='error', failed_jobs EMPTY
 *   unexpected failure           →  reported + rethrown  →  retried  →  failed_jobs
 */
class ReviewPublisherFailureTest extends TestCase
{
    /**
     * Deliberately NOT RefreshDatabase: it wraps every test in an open transaction, so a
     * job dispatched with after_commit=true would never become visible. migrate:fresh
     * (drop + migrate, no rollback) gives a clean database with real commit semantics.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');

        // phpunit.xml pins QUEUE_CONNECTION=sync; these tests need a real queue.
        config(['queue.default' => 'database']);
    }

    // ───────────────────────── scaffolding ─────────────────────────

    private function region(string $code): Region
    {
        $r = Region::firstOrNew(['region_code' => $code]);
        $r->forceFill(['region_name' => $code, 'is_active' => 1, 'currency' => 'USD', 'currency_symbol' => '$'])->save();

        return $r;
    }

    private function makeVideo(array $attrs = [], array $regions = ['AU']): Video
    {
        $char = Character::firstOrNew(['name' => 'Hound Dog Hank']);
        $char->forceFill(['name' => 'Hound Dog Hank'])->save();
        $chan = Channel::firstOrNew(['name' => 'BarkTastic']);
        $chan->forceFill(['name' => 'BarkTastic'])->save();
        $cat = Category::firstOrNew(['name' => 'Dog Apparel']);
        $cat->forceFill(['name' => 'Dog Apparel', 'slug' => 'dog-apparel'])->save();

        $v = new Video();
        $v->forceFill(array_merge([
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
            'review' => null,
        ], $attrs))->save();

        $v->regions()->sync(collect($regions)->map(fn ($c) => $this->region($c)->id)->all());

        return $v->fresh();
    }

    private function publisher(): ReviewPayloadPublisher
    {
        return app(ReviewPayloadPublisher::class);
    }

    /** Drain the real queue in-process, exactly as the supervised worker would. */
    private function work(): void
    {
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--sleep' => 0,
        ]);
    }

    /** Make the mapper explode with an UNEXPECTED failure on the next publish/refresh. */
    private function breakTheMapper(string $message = 'mapper exploded'): void
    {
        $this->app->bind(VideoReviewMapper::class, fn () => new class($message) extends VideoReviewMapper {
            public function __construct(private string $message) {}

            public function map(Video $video, string $slug, string $tier): array
            {
                throw new \RuntimeException($this->message);
            }
        });
    }

    /** Edit a video without waking the observer, so the queue only holds what a test dispatched. */
    private function quietly(Video $video, array $attributes): void
    {
        Video::withoutEvents(fn () => $video->forceFill($attributes)->save());
    }

    // ───────────────── 1. a gate failure is recorded, and does not throw ─────────────────

    public function test_gate_failure_records_error_and_does_not_throw(): void
    {
        $v = $this->makeVideo(['product_name' => null]);

        $payload = $this->publisher()->publish($v);   // must NOT throw

        $this->assertNull($payload);
        $this->assertSame(0, PublishedReviewPayload::count());

        $v->refresh();
        $this->assertSame('error', $v->seo_publish_status);
        $this->assertStringContainsString('product_name', $v->seo_publish_error);
    }

    // ───────────────── 2. an unexpected exception is reported and rethrown ─────────────────

    public function test_unexpected_exception_is_reported_and_rethrown(): void
    {
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with(Mockery::type(\RuntimeException::class));
        $handler->shouldIgnoreMissing();
        $this->app->instance(ExceptionHandler::class, $handler);

        $v = $this->makeVideo();
        $this->breakTheMapper();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mapper exploded');

        $this->publisher()->publish($v);
    }

    /**
     * ReviewPublishGateException extends RuntimeException — the catch must discriminate by
     * exact type, not by the base class.
     *
     * Note the shape: `fail()` lives OUTSIDE the try. PHPUnit's AssertionFailedError is
     * itself a RuntimeException, so a `fail()` inside the try would be caught by our own
     * catch block and the test could never fail.
     */
    public function test_a_plain_runtime_exception_is_not_treated_as_a_gate_failure(): void
    {
        $v = $this->makeVideo();
        $this->breakTheMapper();

        $caught = null;
        try {
            $this->publisher()->publish($v);
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'a plain RuntimeException must not be swallowed as an expected gate failure');
        $this->assertNotInstanceOf(ReviewPublishGateException::class, $caught);
        $this->assertSame('mapper exploded', $caught->getMessage());
    }

    // ───────────────── 3. the queued job is retryable for unexpected failures ─────────────────

    public function test_unexpected_failure_exhausts_retries_and_lands_in_failed_jobs(): void
    {
        $v = $this->makeVideo(['review' => ['quick_verdict' => ['beastie_take' => 'good']]]);
        $this->publisher()->publish($v);          // a real live payload first
        $this->assertSame('published', PublishedReviewPayload::first()->publish_status);

        $this->breakTheMapper();
        RefreshSeoPayloadJob::dispatch($v->id);

        $this->assertSame(1, DB::table('jobs')->count(), 'the job must be queued, not run inline');

        $this->work();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count(), 'an unexpected failure must reach failed_jobs');

        // and the live page is still up, untouched
        $this->assertSame('published', PublishedReviewPayload::first()->publish_status);
    }

    public function test_the_job_declares_three_tries_so_transient_failures_retry(): void
    {
        $this->assertSame(3, (new RefreshSeoPayloadJob(1))->tries);
    }

    // ───── 4 + 5. gate failure: live payload unchanged, failed_jobs stays empty ─────

    public function test_gate_failure_leaves_the_live_payload_unchanged_and_never_reaches_failed_jobs(): void
    {
        $v = $this->makeVideo(['review' => ['quick_verdict' => ['beastie_take' => 'good']]]);
        $original = $this->publisher()->publish($v);
        $this->assertSame('published', $original->publish_status);

        // a DATA GAP appears (product_name cleared), then a refresh is queued
        $this->quietly($v->fresh(), ['product_name' => null]);
        RefreshSeoPayloadJob::dispatch($v->id);
        $this->assertSame(1, DB::table('jobs')->count());

        $this->work();

        // the job SUCCEEDED — a gate failure is not a job failure
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'failed_jobs must not be the gate-failure signal');

        // the live page is untouched...
        $payload = PublishedReviewPayload::first();
        $this->assertSame('published', $payload->publish_status);
        $this->assertSame($original->review_slug, $payload->review_slug);

        // ...and the ONLY failure signal is on the video, which is what deployment verification reads
        $this->assertSame('error', $v->fresh()->seo_publish_status);
        $this->assertStringContainsString('product_name', $v->fresh()->seo_publish_error);
    }
}
