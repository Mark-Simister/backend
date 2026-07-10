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
 * FU-6 gate A — each verb of the admin resources requires its OWN permission.
 *
 * The resources were gated by `permission:x.view|x.create|x.edit|x.delete` on the whole
 * resource. Spatie's pipe means ANY, so a view-only sub_admin could create, edit and
 * delete. Split per verb via four `Route::resource()->only(...)` registrations, which
 * preserves every route name and binding param (verified against a route-table snapshot).
 *
 * Tests the AUTHORIZATION decision, not controller rendering: a denial is a clean 403 from
 * the permission middleware (the controller never runs); a grant is asserted as "not 403"
 * because the controller may then 500 rendering an admin Blade view in the test env, which
 * is irrelevant to whether the gate let the request through.
 *
 * `channels` is the representative because `channel.*` is actually seeded. A super_admin is
 * deliberately NOT tested: the committed Gate::before bypass (AppServiceProvider) short-
 * circuits every permission check, so a super_admin never reaches this middleware. See FU-6.
 */
class AdminResourceVerbPermissionTest extends TestCase
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
        // The permission middleware did NOT reject it. Anything but 403 means it passed
        // (200, or a 500 from the view — not our concern here).
        $this->assertNotSame(403, $r->status(), 'the permission gate should have allowed this verb');
    }

    /**
     * A real Channel id, so PUT/DELETE reach the permission middleware. Route-model binding
     * (SubstituteBindings, web group) runs before the route's permission middleware, so a
     * nonexistent id 404s before the 403 we want to assert.
     */
    private function channelId(): int
    {
        return \App\Models\Channel::firstOrCreate(['name' => 'Bark'])->id;
    }

    public function test_view_only_admin_is_denied_every_write_verb(): void
    {
        $viewer = $this->adminWith('channel.view');
        $id = $this->channelId();

        $this->actingAs($viewer)->get('/admin/channels/create')->assertForbidden();   // create
        $this->actingAs($viewer)->post('/admin/channels', [])->assertForbidden();      // store
        $this->actingAs($viewer)->put("/admin/channels/{$id}", [])->assertForbidden(); // update
        $this->actingAs($viewer)->delete("/admin/channels/{$id}")->assertForbidden();  // destroy
    }

    public function test_create_permission_confers_create_but_not_delete(): void
    {
        $creator = $this->adminWith('channel.view', 'channel.create');
        $id = $this->channelId();

        $this->assertGatePassed($this->actingAs($creator)->get('/admin/channels/create'));
        $this->actingAs($creator)->delete("/admin/channels/{$id}")->assertForbidden();
    }

    public function test_delete_permission_confers_delete_but_not_create(): void
    {
        $deleter = $this->adminWith('channel.view', 'channel.delete');
        $id = $this->channelId();

        $this->actingAs($deleter)->get('/admin/channels/create')->assertForbidden();
        $this->assertGatePassed($this->actingAs($deleter)->delete("/admin/channels/{$id}"));
    }

    public function test_edit_permission_confers_edit_but_not_delete(): void
    {
        $editor = $this->adminWith('channel.view', 'channel.edit');
        $id = $this->channelId();

        $this->assertGatePassed($this->actingAs($editor)->put("/admin/channels/{$id}", []));
        $this->actingAs($editor)->delete("/admin/channels/{$id}")->assertForbidden();
    }
}
