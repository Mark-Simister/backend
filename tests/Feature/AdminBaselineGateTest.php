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
 * FU-6 (behaviour) — a web-authenticated user holding no web-guard permission is refused
 * everything under /admin.
 *
 * The routes below deliberately sample all SEVEN prefix('admin') groups, including
 * top-categories (which had no middleware at all) and the loose routes that never carried
 * their own permission.
 */
class AdminBaselineGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Role::findOrCreate('user', 'api');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** A frontend member — api-guard `user`, in the shared users table — loaded via web. */
    private function member(): User
    {
        $m = new ApiUser();
        $m->forceFill([
            'name' => 'Member', 'email' => 'member@example.com',
            'password' => bcrypt('x'), 'role' => 'user', 'is_verified' => true,
        ])->save();
        $m->assignRole(Role::findOrCreate('user', 'api'));

        return User::findOrFail($m->id);
    }

    /**
     * One representative route from each of the seven prefix('admin') groups.
     *
     * @return array<string, array{0:string,1:string}>
     */
    public static function adminWriteRoutes(): array
    {
        // Binding-free paths (raw id params, no route-model binding), so the baseline gate
        // is provably what returns 403 — not a route-model-binding 404 on a missing record.
        // seo-publish uses a `Video $video` bind and is covered separately below.
        return [
            'group@79 themes'            => ['post', '/admin/themes'],
            'group@83 top-categories'    => ['post', '/admin/top-categories'],
            'group@88 site-images'       => ['post', '/admin/site-images/home_hero'],
            'group@102 toggle-featured'  => ['post', '/admin/videos/toggle-featured'],
            'group@102 loose comment'    => ['delete', '/admin/comments/1'],
            'group@190 sub-admins'       => ['post', '/admin/sub-admins'],
            'group@207 product-messages' => ['delete', '/admin/product-messages/1'],
        ];
    }

    /**
     * @dataProvider adminWriteRoutes
     */
    public function test_a_zero_permission_member_is_forbidden(string $verb, string $path): void
    {
        $this->actingAs($this->member())->$verb($path)->assertForbidden();
    }

    /** group @97 (permission:video.edit) — with a real bound video, the baseline still 403s. */
    public function test_a_member_is_forbidden_on_a_model_bound_admin_route(): void
    {
        $char = \App\Models\Character::firstOrCreate(['name' => 'Hank']);
        $chan = \App\Models\Channel::firstOrCreate(['name' => 'Bark']);
        $cat = \App\Models\Category::firstOrCreate(['name' => 'Dogs'], ['slug' => 'dogs']);
        $video = new \App\Models\Video();
        $video->forceFill([
            'title' => 'V', 'type' => 'youtube', 'video_url' => '',
            'character_id' => $char->id, 'channel_id' => $chan->id, 'category_id' => $cat->id,
            'review_type' => 'review', 'status' => 'published',
        ])->save();

        $this->actingAs($this->member())
            ->post("/admin/videos/{$video->id}/seo-publish")
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login_even_on_the_group_that_had_no_auth(): void
    {
        // top-categories (group @83) previously had NO middleware — an unauthenticated
        // write reached the controller. The gate now intercepts guests here too.
        $response = $this->post('/admin/top-categories');
        $this->assertContains($response->status(), [302, 401, 403]);
        $this->assertGuest();
    }

    public function test_an_admin_with_a_permission_passes_the_baseline(): void
    {
        // Holds a single unrelated permission. The baseline lets them through; the route's
        // OWN permission is what then allows or refuses the specific action (task A).
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('faq.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // A GET they are NOT specifically permitted for still clears the baseline and is
        // then refused by the route's permission — i.e. not a 403 from the baseline gate.
        // Use the users index (needs user.view) to show the baseline is not the blocker:
        $this->actingAs($admin)->get('/admin/users')->assertForbidden();   // blocked by route perm, not baseline

        // And a route matching their permission succeeds through the baseline.
        $this->actingAs($admin)->get('/admin/faqs')->assertOk();
    }

    public function test_a_super_admin_passes_the_baseline_everywhere(): void
    {
        $super = User::factory()->create();
        $super->assignRole('super_admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($super)->get('/admin/users')->assertOk();
        $this->actingAs($super)->get('/admin/faqs')->assertOk();
    }
}
