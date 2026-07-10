<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * FU-6 gate A (loose routes) — the ~31 privileged routes that carried no permission of
 * their own now do, and the permission lines up with the trust level of the thing they
 * change:
 *
 *   payload-feeding (comments, featured, similar-products, insights, affiliate links,
 *   product reviews, vimeo assign)  → video.edit
 *   bloopers                         → character.edit
 *   admin chrome (themes, colours, top-categories, site-images, tag creation,
 *   product-message deletion)        → settings.manage  (super_admin only)
 *
 * Assertions test the authorization decision: a denial is a clean 403 from the permission
 * middleware; a grant is "not 403" (the controller may 500 rendering a view, irrelevant).
 * super_admin is never asserted — Gate::before bypasses the check (FU-6).
 */
class AdminLooseRoutePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
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

    // ── video.edit gates the payload-feeding routes ──

    public function test_video_editor_may_toggle_featured_but_a_settings_manager_may_not(): void
    {
        $this->assertGatePassed(
            $this->actingAs($this->adminWith('video.edit'))->post('/admin/videos/toggle-featured')
        );

        $this->actingAs($this->adminWith('settings.manage'))
            ->post('/admin/videos/toggle-featured')
            ->assertForbidden();
    }

    public function test_video_editor_is_denied_the_settings_routes(): void
    {
        // tag creation is admin chrome → settings.manage, not video.edit
        $this->actingAs($this->adminWith('video.edit'))
            ->post('/admin/tags', ['name' => 'x'])
            ->assertForbidden();
    }

    // ── settings.manage gates admin chrome ──

    public function test_settings_manager_may_create_a_tag(): void
    {
        $this->assertGatePassed(
            $this->actingAs($this->adminWith('settings.manage'))->post('/admin/tags', ['name' => 'x'])
        );
    }

    public function test_settings_manager_may_reach_the_site_image_manager(): void
    {
        $this->assertGatePassed(
            $this->actingAs($this->adminWith('settings.manage'))->get('/admin/site-images')
        );

        $this->actingAs($this->adminWith('video.edit'))
            ->get('/admin/site-images')
            ->assertForbidden();
    }

    // ── character.edit gates bloopers ──

    public function test_character_editor_may_add_a_blooper_but_a_video_editor_may_not(): void
    {
        $char = \App\Models\Character::firstOrCreate(['name' => 'Hank']);

        $this->assertGatePassed(
            $this->actingAs($this->adminWith('character.edit'))
                ->post("/admin/characters/{$char->id}/bloopers", [])
        );

        $this->actingAs($this->adminWith('video.edit'))
            ->post("/admin/characters/{$char->id}/bloopers", [])
            ->assertForbidden();
    }

    // ── the accident is now a decision: settings.manage is not a sub_admin default ──

    public function test_settings_manage_is_not_granted_to_sub_admin_by_the_seeder(): void
    {
        $sub = \Spatie\Permission\Models\Role::findByName('sub_admin', 'web');

        $this->assertFalse($sub->hasPermissionTo('settings.manage'),
            'settings.manage bundles unrelated concerns and must stay super_admin-only until split');
    }
}
