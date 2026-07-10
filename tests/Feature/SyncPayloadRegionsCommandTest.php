<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\Video;
use App\Services\ReviewPayloadPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P-5 — `reviews:sync-payload-regions` repairs an admin payload whose region pivot has
 * drifted from its video (a seeder rewrote video_region, or a refresh job was lost).
 */
class SyncPayloadRegionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function region(string $code): Region
    {
        $r = Region::firstOrNew(['region_code' => $code]);
        $r->forceFill(['region_name' => $code, 'is_active' => 1, 'currency' => 'USD', 'currency_symbol' => '$'])->save();

        return $r;
    }

    private function livePublishedVideo(array $regions = ['AU']): Video
    {
        Queue::fake();   // the observer must not queue refreshes while we set the scene

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
            'review' => ['quick_verdict' => ['beastie_take' => 'good']],
        ])->save();

        $v->regions()->sync(collect($regions)->map(fn ($c) => $this->region($c)->id)->all());
        $v = $v->fresh();

        app(ReviewPayloadPublisher::class)->publish($v);

        return $v->fresh();
    }

    private function payloadRegions(): array
    {
        return PublishedReviewPayload::first()->regions()->pluck('region_code')->sort()->values()->all();
    }

    public function test_it_reports_nothing_to_do_without_admin_payloads(): void
    {
        $this->artisan('reviews:sync-payload-regions')
            ->expectsOutputToContain('No admin-sourced payloads')
            ->assertSuccessful();
    }

    public function test_dry_run_reports_drift_without_repairing_it(): void
    {
        $v = $this->livePublishedVideo(['AU']);
        $v->regions()->sync([$this->region('UK')->id]);   // pivot rewritten behind the payload's back

        $this->artisan('reviews:sync-payload-regions --dry-run')
            ->expectsOutputToContain('dry run')
            ->assertSuccessful();

        $this->assertSame(['AU'], $this->payloadRegions(), 'a dry run must not write');
    }

    public function test_it_resyncs_a_drifted_payload_to_its_videos_regions(): void
    {
        $v = $this->livePublishedVideo(['AU']);
        $v->regions()->sync([$this->region('UK')->id]);

        $this->artisan('reviews:sync-payload-regions')->assertSuccessful();

        $this->assertSame(['UK'], $this->payloadRegions());
    }

    /** An admin payload must never have an empty pivot — that means ALL regions. */
    public function test_it_withdraws_a_payload_whose_video_lost_every_region(): void
    {
        $v = $this->livePublishedVideo(['AU']);
        $v->regions()->sync([]);

        $this->artisan('reviews:sync-payload-regions')->assertSuccessful();

        $this->assertSame('withdrawn', PublishedReviewPayload::first()->publish_status);
    }

    public function test_it_leaves_a_payload_in_sync_untouched(): void
    {
        $this->livePublishedVideo(['AU']);
        $before = PublishedReviewPayload::first()->updated_at;

        $this->artisan('reviews:sync-payload-regions')
            ->expectsOutputToContain('already match')
            ->assertSuccessful();

        $this->assertEquals($before, PublishedReviewPayload::first()->updated_at);
    }
}
