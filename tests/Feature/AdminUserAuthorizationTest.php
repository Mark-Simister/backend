<?php

namespace Tests\Feature;

use App\Models\ApiUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A2 — /admin/users was gated by `permission:user.view|user.create|user.edit|user.delete`.
 *
 * Spatie's pipe means ANY of them, applied to the whole resource, so a view-only sub-admin
 * could create, edit and DELETE users. `toggle-block` carried no permission at all — the
 * enclosing group is `middleware(['auth'])` — so any authenticated user could block anyone,
 * including a super_admin. And because ApiUser::$table = 'users', a public frontend member's
 * credentials authenticate at the admin /login, which made "any authenticated user" include
 * the membership.
 *
 * `exists:roles,name` was not a control either: `super_admin` exists on the `web` guard, so
 * it validated, and `$user->update(['role' => ...])` wrote `users.role = 'super_admin'`
 * before `assignRole()` failed on the guard mismatch.
 */
class AdminUserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Role::findOrCreate('user', 'api');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** A sub-admin holding ONLY user.view. */
    private function viewOnlyAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('user.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin;
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin;
    }

    /** A public frontend member. Same `users` table as the admins — that is the point. */
    private function member(): ApiUser
    {
        $m = new ApiUser();
        $m->forceFill([
            'name' => 'Member',
            'email' => 'member' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_verified' => true,
        ])->save();

        $m->assignRole(Role::findOrCreate('user', 'api'));

        return $m->fresh();
    }

    // ───────────── each verb needs its own permission ─────────────

    public function test_view_only_admin_may_list_users(): void
    {
        $this->actingAs($this->viewOnlyAdmin())->get('/admin/users')->assertOk();
    }

    public function test_view_only_admin_cannot_create_a_user(): void
    {
        $this->actingAs($this->viewOnlyAdmin())
            ->post('/admin/users', [
                'name' => 'New', 'email' => 'new@example.com', 'phone' => null,
                'password' => 'password', 'password_confirmation' => 'password',
                'role' => 'user',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_view_only_admin_cannot_update_a_user(): void
    {
        $target = $this->member();

        $this->actingAs($this->viewOnlyAdmin())
            ->put('/admin/users/' . $target->id, [
                'name' => 'Renamed', 'phone' => null, 'role' => 'user',
            ])
            ->assertForbidden();

        $this->assertNotSame('Renamed', $target->fresh()->name);
    }

    public function test_view_only_admin_cannot_delete_a_user(): void
    {
        $target = $this->member();

        $this->actingAs($this->viewOnlyAdmin())
            ->delete('/admin/users/' . $target->id)
            ->assertForbidden();

        $this->assertNotNull(ApiUser::find($target->id));
    }

    // ───────────── toggle-block is no longer auth-only ─────────────

    public function test_a_merely_authenticated_user_cannot_block_anyone(): void
    {
        $victim = $this->superAdmin();
        $nobody = User::factory()->create();      // authenticated, zero permissions

        $this->actingAs($nobody)
            ->post('/admin/users/' . $victim->id . '/toggle-block')
            ->assertForbidden();

        $this->assertFalse((bool) $victim->fresh()->is_blocked);
    }

    public function test_view_only_admin_cannot_block_anyone(): void
    {
        $victim = $this->superAdmin();

        $this->actingAs($this->viewOnlyAdmin())
            ->post('/admin/users/' . $victim->id . '/toggle-block')
            ->assertForbidden();

        $this->assertFalse((bool) $victim->fresh()->is_blocked);
    }

    // ───────────── the role cannot be taken from the request unchecked ─────────────

    public function test_a_user_editor_cannot_grant_super_admin(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(Permission::findOrCreate('user.edit', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $target = $this->member();

        $this->actingAs($editor)
            ->put('/admin/users/' . $target->id, [
                'name' => 'Escalated', 'phone' => null, 'role' => 'super_admin',
            ])
            ->assertStatus(302)               // validation rejects it: no api-guard super_admin
            ->assertSessionHasErrors('role');

        // and crucially the role COLUMN was not written before the failure
        $this->assertNotSame('super_admin', $target->fresh()->role);
    }

    public function test_a_user_creator_cannot_mint_a_super_admin(): void
    {
        $creator = User::factory()->create();
        $creator->givePermissionTo(Permission::findOrCreate('user.create', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($creator)
            ->post('/admin/users', [
                'name' => 'Root', 'email' => 'root@example.com', 'phone' => null,
                'password' => 'password', 'password_confirmation' => 'password',
                'role' => 'super_admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'root@example.com']);
    }

    // ───────────── the legitimate paths still work ─────────────

    public function test_a_user_creator_may_create_an_ordinary_member(): void
    {
        $creator = User::factory()->create();
        $creator->givePermissionTo(Permission::findOrCreate('user.create', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($creator)
            ->post('/admin/users', [
                'name' => 'New', 'email' => 'new@example.com', 'phone' => null,
                'password' => 'password', 'password_confirmation' => 'password',
                'role' => 'user',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'user']);
    }

    public function test_a_user_editor_may_block_a_member(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(Permission::findOrCreate('user.edit', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $target = $this->member();

        $this->actingAs($editor)
            ->post('/admin/users/' . $target->id . '/toggle-block')
            ->assertOk();

        $this->assertTrue((bool) User::find($target->id)->is_blocked);
    }
}
