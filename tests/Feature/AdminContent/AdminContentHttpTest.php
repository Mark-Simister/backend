<?php

namespace Tests\Feature\AdminContent;

use App\Http\Middleware\AdminPrivateResponse;
use App\Models\MediaAttachment;
use App\Models\PublishedReviewPayload;
use App\Models\ReviewEditorialRecord;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\TestCase;

/**
 * Admin Content & Media routes, permissions and screens (design record section 11).
 * Following the FU-6 tests: a denial is a clean 403 from the permission middleware; a
 * grant is "not 403". Screens are additionally rendered end to end.
 */
class AdminContentHttpTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;

    private const NEW_PERMISSIONS = [
        'review.view', 'review.edit', 'review.qa', 'review.hold', 'review.release', 'review.withdraw',
        'media.view', 'media.manage',
        'bestfor.view', 'bestfor.edit', 'bestfor.publish', 'bestfor.hold', 'bestfor.unpublish',
    ];

    private PublishedReviewPayload $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->associations();
        $this->payload = $this->ingest($this->reviewRow());
    }

    private function adminWith(string ...$perms): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    private function assertGatePassed(TestResponse $r): void
    {
        $this->assertNotSame(403, $r->status(), 'the permission gate should have allowed this');
    }

    private function url(string $suffix = ''): string
    {
        return '/admin/review-editorial/' . $this->payload->published_review_id . $suffix;
    }

    /**
     * The complete frozen seed matrix (design record section 11), row by row:
     * permission => [super_admin, publisher, qa_reviewer, editor, sub_admin].
     */
    private const FROZEN_MATRIX = [
        'review.view' => [1, 1, 1, 1, 0],
        'review.edit' => [1, 1, 0, 1, 0],
        'review.qa' => [1, 1, 1, 0, 0],
        'review.hold' => [1, 1, 1, 0, 0],
        'review.release' => [1, 1, 0, 0, 0],
        'review.withdraw' => [1, 1, 0, 0, 0],
        'media.view' => [1, 1, 1, 1, 0],
        'media.manage' => [1, 1, 0, 1, 0],
        'bestfor.view' => [1, 1, 1, 1, 0],
        'bestfor.edit' => [1, 1, 0, 1, 0],
        'bestfor.publish' => [1, 1, 0, 0, 0],
        'bestfor.hold' => [1, 1, 0, 0, 0],
        'bestfor.unpublish' => [1, 0, 0, 0, 0],
    ];

    public function test_the_role_matrix_matches_the_frozen_design_record_exactly(): void
    {
        $roles = ['super_admin', 'publisher', 'qa_reviewer', 'editor', 'sub_admin'];
        $held = [];
        foreach ($roles as $role) {
            $held[$role] = Role::findByName($role, 'web')->permissions->pluck('name')->all();
        }

        foreach (self::FROZEN_MATRIX as $permission => $grants) {
            $this->assertNotNull(Permission::query()->where('name', $permission)->where('guard_name', 'web')->first(), "{$permission} is seeded");
            foreach ($roles as $i => $role) {
                $this->assertSame((bool) $grants[$i], in_array($permission, $held[$role], true), "{$role} / {$permission}");
            }
        }

        // The three new roles hold nothing beyond their matrix rows.
        foreach (['publisher' => 1, 'qa_reviewer' => 2, 'editor' => 3] as $role => $i) {
            $expected = array_keys(array_filter(self::FROZEN_MATRIX, fn ($g) => $g[$i] === 1));
            $this->assertEqualsCanonicalizing($expected, $held[$role], "{$role} holds exactly its matrix permissions");
        }

        // sub_admin is unchanged: exactly the pre-existing grants.
        $this->assertEqualsCanonicalizing([
            'category.view', 'category.create', 'category.edit', 'category.delete',
            'channel.view', 'channel.create', 'channel.edit', 'channel.delete',
            'character.view', 'character.create', 'character.edit', 'character.delete',
            'video.view', 'video.create', 'video.edit', 'video.delete',
        ], $held['sub_admin']);
    }

    public function test_no_route_uses_a_best_for_permission_in_sprint_a(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            // Declared route middleware only: no controller class is loaded.
            foreach ($route->middleware() as $middleware) {
                $this->assertFalse(is_string($middleware) && str_starts_with($middleware, 'permission:bestfor.'),
                    $route->uri() . ' must not use a bestfor.* permission before Sprint D');
            }
        }
    }

    public function test_every_new_route_is_admin_gated_private_and_carries_its_own_permission(): void
    {
        $routes = array_filter(Route::getRoutes()->getRoutes(), fn ($r) => str_starts_with($r->uri(), 'admin/review-editorial') || str_starts_with($r->uri(), 'admin/media-library'));
        $this->assertCount(26, $routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $label = implode('|', $route->methods()) . ' ' . $route->uri();
            $this->assertContains('web', $middleware, $label);
            $this->assertContains('auth', $middleware, $label);
            $this->assertContains(AdminPrivateResponse::class, $middleware, $label);
            $this->assertCount(1, array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'permission:')), "{$label} needs exactly one permission");
        }
    }

    public function test_guests_are_sent_to_login_and_frontend_members_are_refused(): void
    {
        $this->get('/admin/review-editorial')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get('/admin/review-editorial')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/media-library')->assertForbidden();
    }

    public function test_each_action_requires_its_own_permission(): void
    {
        $id = $this->payload->published_review_id;
        $matrix = [
            ['GET', '/admin/review-editorial', 'review.view'],
            ['GET', $this->url(), 'review.view'],
            ['GET', $this->url('/preview'), 'review.view'],
            ['GET', $this->url('/preview/frame'), 'review.view'],
            ['POST', $this->url('/associations'), 'review.edit'],
            ['POST', $this->url('/notes'), 'review.edit'],
            ['POST', $this->url('/media'), 'review.edit'],
            ['POST', $this->url('/media/1/discard'), 'review.edit'],
            ['POST', $this->url('/regions'), 'review.release'],
            ['POST', $this->url('/qa-pass'), 'review.qa'],
            ['POST', $this->url('/return'), 'review.qa'],
            ['POST', $this->url('/release'), 'review.release'],
            ['POST', $this->url('/unrelease'), 'review.release'],
            ['POST', $this->url('/hold'), 'review.hold'],
            ['POST', $this->url('/lift-hold'), 'review.hold'],
            ['POST', $this->url('/disposition/withdraw'), 'review.withdraw'],
            ['POST', $this->url('/disposition/classify'), 'review.withdraw'],
            ['POST', $this->url('/disposition/validity'), 'review.withdraw'],
            ['POST', $this->url('/disposition/availability'), 'review.withdraw'],
            ['POST', $this->url('/disposition/reinstate'), 'review.withdraw'],
            ['GET', '/admin/media-library', 'media.view'],
            ['GET', '/admin/media-library/1', 'media.view'],
            ['POST', '/admin/media-library/external', 'media.manage'],
            ['POST', '/admin/media-library/upload', 'media.manage'],
            ['POST', '/admin/media-library/1/metadata', 'media.manage'],
            ['POST', '/admin/media-library/1/archive', 'media.manage'],
        ];

        foreach ($matrix as [$method, $uri, $permission]) {
            $others = array_values(array_diff(self::NEW_PERMISSIONS, [$permission]));
            $this->actingAs($this->adminWith(...$others))->call($method, $uri)->assertForbidden();
            $this->assertGatePassed($this->actingAs($this->adminWith($permission))->call($method, $uri));
        }
        $this->assertNotNull($id);
    }

    public function test_responses_are_noindex_and_never_cached(): void
    {
        $user = $this->adminWith(...self::NEW_PERMISSIONS);

        foreach (['/admin/review-editorial', $this->url(), $this->url('/preview'), $this->url('/preview/frame'), '/admin/media-library'] as $uri) {
            $response = $this->actingAs($user)->get($uri)->assertOk();
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'), $uri);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), $uri);
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'), $uri);
        }

        $redirect = $this->actingAs($user)->post($this->url('/notes'), ['internal_notes' => 'x']);
        $this->assertStringContainsString('no-store', $redirect->headers->get('Cache-Control'));
    }

    public function test_the_queue_and_every_workspace_tab_render(): void
    {
        $user = $this->adminWith(...self::NEW_PERMISSIONS);
        $this->ingest($this->reviewRow('pr_legacy', ['source' => 'admin']));

        $this->actingAs($user)->get('/admin/review-editorial')->assertOk()->assertSee('Test Feeder Review')->assertSee('legacy');
        $this->actingAs($user)->get('/admin/review-editorial?flags[]=legacy')->assertOk()->assertSee('pr_legacy')->assertDontSee('pr_test_001');
        $this->actingAs($user)->get('/admin/review-editorial?flags[]=missing_associations&state=pending_review')->assertOk()->assertSee('pr_test_001');
        $this->actingAs($user)->get('/admin/review-editorial?flags[]=stale&flags[]=d3&flags[]=d1&flags[]=held&flags[]=withdrawn&flags[]=has_video')->assertOk();

        foreach (['overview', 'associations', 'media', 'qa', 'disposition', 'history'] as $tab) {
            $this->actingAs($user)->get($this->url('?tab=' . $tab))->assertOk();
        }
        $this->actingAs($user)->get('/admin/review-editorial/pr_legacy')->assertOk()->assertSee('Legacy admin-source row');
        $this->actingAs($user)->get('/admin/review-editorial/does-not-exist')->assertNotFound();
    }

    public function test_the_workspace_drives_the_two_key_lifecycle_end_to_end(): void
    {
        $user = $this->adminWith(...self::NEW_PERMISSIONS);
        $show = $this->url('?tab=qa');

        $this->actingAs($user)->post($this->url('/associations'), ['category_id' => $this->category->id])
            ->assertRedirect($this->url('?tab=associations'))->assertSessionHas('success');

        $now = $this->fp($this->payload);
        $this->actingAs($user)->post($this->url('/release'), ['content_fingerprint' => $now['content'], 'media_fingerprint' => $now['media'], 'reason' => 'early'])
            ->assertRedirect($show)->assertSessionHasErrors('editorial');

        $this->actingAs($user)->post($this->url('/qa-pass'), ['content_fingerprint' => $now['content'], 'media_fingerprint' => $now['media']])
            ->assertRedirect($show)->assertSessionHas('success');
        $this->actingAs($user)->post($this->url('/release'), ['content_fingerprint' => $now['content'], 'media_fingerprint' => $now['media']])
            ->assertSessionHasErrors('reason');
        $this->actingAs($user)->post($this->url('/release'), ['content_fingerprint' => $now['content'], 'media_fingerprint' => $now['media'], 'reason' => 'Ship it'])
            ->assertRedirect($show)->assertSessionHas('success');

        $record = ReviewEditorialRecord::query()->findOrFail($this->payload->published_review_id);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $record->editorial_state);
        $this->assertSame($user->id, $record->state_changed_by);

        $this->actingAs($user)->get($this->url('?tab=history'))->assertOk()->assertSee('review.released')->assertSee('Ship it');
    }

    public function test_media_can_be_staged_and_discarded_from_the_workspace(): void
    {
        $user = $this->adminWith(...self::NEW_PERMISSIONS);
        $video = $this->videoAsset();

        $this->actingAs($user)->post($this->url('/media'), ['media_asset_id' => $video->id, 'role' => 'video'])->assertSessionHas('success');
        $attachment = MediaAttachment::query()->firstOrFail();
        $this->assertSame(MediaAttachment::STATE_PENDING, $attachment->state);

        $this->actingAs($user)->post($this->url('/media'), ['media_asset_id' => $video->id, 'role' => 'poster'])->assertSessionHasErrors('editorial');
        $this->actingAs($user)->post($this->url('/media/' . $attachment->id . '/discard'), ['reason' => 'wrong cut'])->assertSessionHas('success');
        $this->assertSame(MediaAttachment::STATE_RETIRED, $attachment->fresh()->state);

        $other = $this->ingest($this->reviewRow('pr_other'));
        $foreign = $this->registry()->stage($this->imageAsset(), MediaAttachment::OWNER_REVIEW, $other->published_review_id, 'poster', null);
        $this->actingAs($user)->post($this->url('/media/' . $foreign->id . '/discard'), ['reason' => 'x'])->assertNotFound();
    }

    public function test_admin_requests_cannot_write_n8n_owned_fields(): void
    {
        $user = $this->adminWith(...self::NEW_PERMISSIONS);
        $before = PublishedReviewPayload::query()->findOrFail($this->payload->id)->getAttributes();
        $forged = [
            'publish_status' => 'published', 'review_page_json' => '{"hero":{"title":"forged"}}', 'h1' => 'forged',
            'review_slug' => 'forged', 'published_review_id' => 'forged', 'content_fingerprint' => str_repeat('c', 64),
        ];

        $this->actingAs($user)->post($this->url('/associations'), $forged + ['category_id' => $this->category->id]);
        $this->actingAs($user)->post($this->url('/notes'), $forged + ['internal_notes' => 'n']);
        $this->actingAs($user)->post($this->url('/hold'), $forged + ['reason' => 'r']);

        $this->assertSame($before, PublishedReviewPayload::query()->findOrFail($this->payload->id)->getAttributes());
        $this->assertSame(1, PublishedReviewPayload::query()->count());
    }

    public function test_the_preview_reuses_the_public_template_and_refuses_changed_content(): void
    {
        $user = $this->adminWith('review.view');
        $count = PublishedReviewPayload::query()->count();
        $fp = $this->fp($this->payload)['content'];

        $wrapper = $this->actingAs($user)->get($this->url('/preview'))->assertOk();
        $wrapper->assertSee($fp)->assertSee('preview/frame?fp=' . $fp, false);

        $frame = $this->actingAs($user)->get($this->url('/preview/frame?fp=' . $fp))->assertOk();
        $frame->assertSee('Test Feeder Review')->assertSee('<meta name="robots" content="noindex', false);
        $this->assertStringContainsString('application/ld+json', $frame->getContent());

        $this->ingest($this->reviewRow('pr_test_001', ['h1' => 'Changed meanwhile'], ['hero' => ['title' => 'Changed meanwhile']]));
        $this->actingAs($user)->get($this->url('/preview/frame?fp=' . $fp))->assertStatus(409);

        $this->assertSame($count, PublishedReviewPayload::query()->count(), 'the preview never creates a payload row');
        $this->assertDatabaseMissing('review_editorial_records', ['published_review_id' => $this->payload->published_review_id]);
    }

    public function test_the_media_library_registers_and_archives(): void
    {
        $user = $this->adminWith('media.view', 'media.manage');

        $this->actingAs($user)->post('/admin/media-library/external', ['reference' => 'https://vimeo.com/2468', 'title' => 'Feeder'])
            ->assertSessionHas('success');
        $asset = \App\Models\MediaAsset::query()->where('external_ref', 'vimeo:2468')->firstOrFail();

        $this->actingAs($user)->post('/admin/media-library/external', ['reference' => 'http://evil.example/x'])->assertSessionHasErrors('media');
        $this->actingAs($user)->get('/admin/media-library')->assertOk()->assertSee('vimeo:2468');
        $this->actingAs($user)->get('/admin/media-library/' . $asset->id)->assertOk()->assertSee('https://player.vimeo.com/video/2468');
        $this->actingAs($user)->post('/admin/media-library/' . $asset->id . '/metadata', ['title' => 'Feeder v2'])->assertSessionHas('success');
        $this->assertSame('Feeder v2', $asset->fresh()->title);

        $this->actingAs($user)->post('/admin/media-library/' . $asset->id . '/archive', [])->assertSessionHasErrors('reason');
        $this->actingAs($user)->post('/admin/media-library/' . $asset->id . '/archive', ['reason' => 'Superseded'])->assertSessionHas('success');
        $this->assertTrue($asset->fresh()->isArchived());
    }

    public function test_the_upload_route_refuses_audio_whose_content_is_not_audio(): void
    {
        $user = $this->adminWith('media.view', 'media.manage');
        $renamed = new \Illuminate\Http\UploadedFile(base_path('public/admin/assets/images/beastierated_logo.png'), 'song.mp3', 'audio/mpeg', null, true);

        $this->actingAs($user)->post('/admin/media-library/upload', ['kind' => 'audio', 'file' => $renamed, 'title' => 'Not a song'])
            ->assertSessionHasErrors('media');

        $this->assertSame(0, \App\Models\MediaAsset::query()->count());
    }

    public function test_the_sidebar_shows_the_entries_by_permission(): void
    {
        $this->actingAs($this->adminWith('review.view'))->get('/admin/review-editorial')
            ->assertSee('Review Editorial')->assertDontSee('Media Library');
        $this->actingAs($this->adminWith('media.view'))->get('/admin/media-library')
            ->assertSee('Media Library')->assertDontSee('Review Editorial');
    }

    public function test_the_new_screens_never_surface_the_legacy_publish_to_seo_route(): void
    {
        $user = $this->adminWith(...self::NEW_PERMISSIONS);

        foreach (['/admin/review-editorial', $this->url(), $this->url('?tab=qa'), $this->url('/preview')] as $uri) {
            $this->actingAs($user)->get($uri)->assertDontSee('seo-publish');
        }
    }
}
