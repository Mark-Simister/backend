<?php

namespace Tests\Feature;

use App\Jobs\RefreshSeoPayloadJob;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\PublishedReviewPayload;
use App\Models\Region;
use App\Models\User;
use App\Models\Video;
use App\Services\ReviewPayloadPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 4 step 3 — triggers.
 *
 * The explicit "Publish to SEO" action is the ONLY thing that creates the first
 * payload. Afterwards VideoObserver + RefreshSeoPayloadJob keep it in sync, and
 * intentional takedowns (unpublish / not-a-review / no regions / deleted) withdraw
 * it — while a mere DATA GAP (e.g. product_name cleared) records an error and
 * leaves the live page up.
 */
class ReviewSeoTriggersTest extends TestCase
{
    use RefreshDatabase;

    private function realReview(int $id = 122): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/video_review_{$id}.json")), true);
    }

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

    private function payloadFor(Video $v): ?PublishedReviewPayload
    {
        return PublishedReviewPayload::where('video_id', $v->id)->first();
    }

    /** Publishing a public page requires video.edit, like the other sensitive video routes. */
    private function adminUser(): User
    {
        Permission::findOrCreate('video.edit', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('video.edit');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    // ───────────── the explicit gate ─────────────

    public function test_observer_never_creates_the_first_payload(): void
    {
        $v = $this->makeVideo(['review' => null]);

        $v->update(['review' => $this->realReview(122), 'product_name' => 'Changed']);

        $this->assertSame(0, PublishedReviewPayload::count(), 'only the explicit action may create the first payload');
        $this->assertNull($v->fresh()->seo_published_at);
    }

    public function test_publish_to_seo_action_creates_the_payload(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);

        $this->actingAs($this->adminUser())
            ->from('/admin')
            ->post(route('admin.videos.seo-publish', $v))
            ->assertRedirect('/admin')
            ->assertSessionHas('status');

        $payload = $this->payloadFor($v);
        $this->assertNotNull($payload);
        $this->assertSame('published', $payload->publish_status);
        $this->assertSame('rich', $payload->content_tier);
        $this->assertSame('published', $v->fresh()->seo_publish_status);
    }

    public function test_publish_to_seo_action_surfaces_gate_failure(): void
    {
        $v = $this->makeVideo(['product_name' => null]);

        $this->actingAs($this->adminUser())
            ->from('/admin')
            ->post(route('admin.videos.seo-publish', $v))
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('seo');

        $this->assertNull($this->payloadFor($v));
        $this->assertSame('error', $v->fresh()->seo_publish_status);
    }

    public function test_withdraw_action_takes_the_page_down(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);

        $this->actingAs($this->adminUser())
            ->from('/admin')
            ->post(route('admin.videos.seo-withdraw', $v))
            ->assertRedirect('/admin');

        $this->assertSame('withdrawn', $this->payloadFor($v)->publish_status);
    }

    // ───────────── observer-driven refresh ─────────────

    public function test_observer_refreshes_snapshot_when_review_content_added(): void
    {
        $v = $this->makeVideo(['review' => null]);
        $this->assertSame('thin', $this->publisher()->publish($v)->content_tier);

        $v->fresh()->update(['review' => $this->realReview(122)]);   // observer → job → refresh

        $this->assertSame('rich', $this->payloadFor($v)->content_tier);
    }

    public function test_observer_ignores_changes_that_do_not_affect_the_public_page(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);
        $before = $this->payloadFor($v)->updated_at;

        Queue::fake();
        $v->fresh()->update(['description' => 'internal note, not on the public page']);
        Queue::assertNothingPushed();
    }

    /** The publisher writes seo_* back to the video; that must not re-trigger the observer. */
    public function test_publisher_writeback_does_not_retrigger_the_observer(): void
    {
        Queue::fake();
        $v = $this->makeVideo(['review' => $this->realReview(122)]);

        $this->publisher()->publish($v);

        Queue::assertNothingPushed();   // no feedback loop
    }

    // ───────────── takedown vs data gap ─────────────

    public function test_unpublishing_the_video_withdraws_the_public_page(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);

        $v->fresh()->update(['status' => 'draft']);   // intentional takedown

        $this->assertSame('withdrawn', $this->payloadFor($v)->publish_status);
        $this->assertSame('withdrawn', $v->fresh()->seo_publish_status);
    }

    public function test_changing_review_type_withdraws_the_public_page(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);

        $v->fresh()->update(['review_type' => 'rating']);   // no longer a product review

        $this->assertSame('withdrawn', $this->payloadFor($v)->publish_status);
    }

    public function test_a_data_gap_errors_but_leaves_the_live_page_up(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);

        $v->fresh()->update(['product_name' => null]);   // data gap, NOT a takedown

        $payload = $this->payloadFor($v);
        $this->assertSame('published', $payload->publish_status, 'a data gap must not withdraw a live page');
        $this->assertSame('rich', $payload->content_tier);

        $v->refresh();
        $this->assertSame('error', $v->seo_publish_status);
        $this->assertStringContainsString('product_name', $v->seo_publish_error);
    }

    public function test_deleting_the_video_withdraws_the_public_page(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);

        $id = $v->id;
        $v->fresh()->delete();

        $this->assertSame('withdrawn', PublishedReviewPayload::where('video_id', $id)->first()->publish_status);
    }

    // ───────────── region-update hook (what VideoController dispatches) ─────────────

    public function test_region_change_refresh_job_resyncs_payload_regions(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)], ['AU']);
        $this->publisher()->publish($v);
        $this->assertSame(['AU'], $this->payloadFor($v)->regions()->pluck('region_code')->all());

        $v->regions()->sync([$this->region('UK')->id]);   // pivot write: no model event
        RefreshSeoPayloadJob::dispatchSync($v->id);        // what VideoController dispatches

        $this->assertSame(['UK'], $this->payloadFor($v)->fresh()->regions()->pluck('region_code')->all());
    }

    public function test_region_change_refresh_job_withdraws_when_all_regions_removed(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);

        $v->regions()->sync([]);
        RefreshSeoPayloadJob::dispatchSync($v->id);

        $this->assertSame('withdrawn', $this->payloadFor($v)->publish_status);
    }

    public function test_refresh_job_noops_for_a_video_never_published_to_seo(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);

        RefreshSeoPayloadJob::dispatchSync($v->id);

        $this->assertSame(0, PublishedReviewPayload::count());
    }

    // ───────────── step 4: permission gate + admin UI panel ─────────────

    public function test_publish_route_requires_video_edit_permission(): void
    {
        $v = $this->makeVideo(['review' => $this->realReview(122)]);

        $this->actingAs(User::factory()->create())   // authenticated, but no video.edit
            ->post(route('admin.videos.seo-publish', $v))
            ->assertForbidden();

        $this->assertSame(0, PublishedReviewPayload::count());
    }

    public function test_seo_panel_shows_live_state_and_withdraw_action(): void
    {
        view()->share('errors', new ViewErrorBag);
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);
        $v = $v->fresh();

        $html = view('admin.videos._seo-publish', ['video' => $v])->render();

        $this->assertStringContainsString('Public SEO review page', $html);
        $this->assertStringContainsString('/review/' . $v->review_slug, $html);
        $this->assertStringContainsString('rich content', $html);
        $this->assertStringContainsString('Withdraw from SEO', $html);
        $this->assertStringContainsString('Re-publish to SEO', $html);
    }

    public function test_seo_panel_explains_why_an_unpublishable_video_is_blocked(): void
    {
        view()->share('errors', new ViewErrorBag);
        $v = $this->makeVideo(['product_name' => null]);

        $html = view('admin.videos._seo-publish', ['video' => $v])->render();

        $this->assertStringContainsString('Not ready to publish', $html);
        $this->assertStringContainsString('product_name', $html);
        $this->assertStringContainsString('disabled', $html);              // publish button disabled
        $this->assertStringNotContainsString('Withdraw from SEO', $html);  // nothing live to withdraw
    }

    public function test_seo_panel_surfaces_last_error_while_page_stays_live(): void
    {
        view()->share('errors', new ViewErrorBag);
        $v = $this->makeVideo(['review' => $this->realReview(122)]);
        $this->publisher()->publish($v);
        $v->fresh()->update(['product_name' => null]);   // data gap → error, page stays live

        $html = view('admin.videos._seo-publish', ['video' => $v->fresh()])->render();

        $this->assertStringContainsString('Last publish failed', $html);
        $this->assertStringContainsString('still live and unchanged', $html);
    }
}
