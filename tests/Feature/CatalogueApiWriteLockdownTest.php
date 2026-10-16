<?php

namespace Tests\Feature;

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CharacterRoleController;
use App\Http\Controllers\CharacterTagController;
use App\Http\Controllers\RegionController;
use App\Models\ApiUser;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Character;
use App\Models\CharacterRole;
use App\Models\CharacterTag;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * SEC-CHAR-AUTH-01 - catalogue writes go through the admin panel only.
 *
 * routes/api.php exposed create/update/delete for characters, categories, regions,
 * channels, character tags and character roles to every authenticated API member: the
 * group was only `auth:api` + `check_blocked`, and the 18 controller methods check nothing.
 * The permission-gated admin panel (routes/web.php, per-verb Spatie permissions) is the
 * supported editor, so the API write routes are removed rather than permission-gated -
 * admin roles belong to the web User model and never reach the API's ApiUser.
 *
 * The lockdown cases assert the secure outcome (no route: 404/405, catalogue unchanged),
 * so against the vulnerable routes they fail with the status the member actually received.
 * Admin-panel checks follow AdminResourceVerbPermissionTest: a grant is "not 403".
 */
class CatalogueApiWriteLockdownTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_ACTIONS = [
        CharacterController::class . '@store_api',
        CharacterController::class . '@update_api',
        CharacterController::class . '@destroy_api',
        CategoryController::class . '@store_api',
        CategoryController::class . '@update_api',
        CategoryController::class . '@destroy_api',
        RegionController::class . '@store_api',
        RegionController::class . '@update_api',
        RegionController::class . '@destroy_api',
        ChannelController::class . '@store_api',
        ChannelController::class . '@update_api',
        ChannelController::class . '@destroy_api',
        CharacterTagController::class . '@store_api',
        CharacterTagController::class . '@update_api',
        CharacterTagController::class . '@destroy_api',
        CharacterRoleController::class . '@store_api',
        CharacterRoleController::class . '@update_api',
        CharacterRoleController::class . '@destroy_api',
    ];

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        // The JWT provider (the `api` guard) refuses to build without a signing secret, and
        // phpunit.xml sets none. A throwaway in-memory value, set before any request
        // resolves the guard; it signs nothing real and is never written anywhere.
        config(['jwt.secret' => Str::random(64)]);
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // The public character and its links; the write targets below are unreferenced,
        // so a successful write against the vulnerable routes is a plain 2xx.
        $linkedCategory = Category::forceCreate(['name' => 'Lockdown Linked Category']);
        $us = Region::forceCreate(['region_name' => 'United States', 'region_code' => 'US', 'currency' => 'USD']);
        $character = Character::forceCreate(['name' => 'Lockdown Pup', 'category_id' => $linkedCategory->id]);
        $character->regions()->attach($us->id);

        $this->ids = [
            'linked_category' => $linkedCategory->id,
            'character' => $character->id,
            'category' => Category::forceCreate(['name' => 'Lockdown Target Category'])->id,
            'channel' => Channel::forceCreate(['name' => 'Lockdown Target Channel'])->id,
            'region' => Region::forceCreate(['region_name' => 'Target Region', 'region_code' => 'TR', 'currency' => 'TRC'])->id,
            'tag' => CharacterTag::forceCreate(['name' => 'Lockdown Tag'])->id,
            'role' => CharacterRole::forceCreate(['name' => 'Lockdown Role'])->id,
        ];
    }

    /** The 18 removed API write routes: method, URI template, payload template. */
    public static function removedWriteRoutes(): array
    {
        return [
            'create character' => ['POST', 'api/characters', []],
            'update character' => ['POST', 'api/characters/{character}', ['name' => 'Renamed by member', 'category_id' => '{linked_category}']],
            'delete character' => ['DELETE', 'api/characters/{character}', []],
            'create category' => ['POST', 'api/categories', []],
            'update category' => ['PUT', 'api/categories/{category}', ['name' => 'Renamed by member', 'channel_id' => '{channel}']],
            'delete category' => ['DELETE', 'api/categories/{category}', []],
            'create region' => ['POST', 'api/regions', []],
            'update region' => ['PUT', 'api/regions/{region}', ['region_name' => 'Renamed by member', 'region_code' => 'ZZ', 'currency' => 'ZZZ']],
            'delete region' => ['DELETE', 'api/regions/{region}', []],
            'create channel' => ['POST', 'api/channels', []],
            'update channel' => ['PUT', 'api/channels/{channel}', ['name' => 'Renamed by member']],
            'delete channel' => ['DELETE', 'api/channels/{channel}', []],
            'create character tag' => ['POST', 'api/character-tags', []],
            'update character tag' => ['PUT', 'api/character-tags/{tag}', ['name' => 'Renamed by member']],
            'delete character tag' => ['DELETE', 'api/character-tags/{tag}', []],
            'create character role' => ['POST', 'api/character-roles', []],
            'update character role' => ['PUT', 'api/character-roles/{role}', ['name' => 'Renamed by member']],
            'delete character role' => ['DELETE', 'api/character-roles/{role}', []],
        ];
    }

    #[DataProvider('removedWriteRoutes')]
    public function test_member_cannot_write_the_catalogue_through_the_api(string $method, string $uri, array $payload): void
    {
        $before = $this->catalogueSnapshot();
        $response = $this->actingAs($this->apiMember(), 'api')->json($method, $this->fill($uri), $this->fill($payload));

        $this->assertRefusedAndUnchanged($response, [404, 405], "member {$method} {$uri}", $before);
    }

    #[DataProvider('removedWriteRoutes')]
    public function test_super_admin_role_on_an_api_account_opens_no_catalogue_write(string $method, string $uri, array $payload): void
    {
        $before = $this->catalogueSnapshot();
        $response = $this->actingAs($this->apiMember('super_admin'), 'api')->json($method, $this->fill($uri), $this->fill($payload));

        $this->assertRefusedAndUnchanged($response, [404, 405], "super_admin API account {$method} {$uri}", $before);
    }

    #[DataProvider('removedWriteRoutes')]
    public function test_anonymous_visitor_cannot_write_the_catalogue_through_the_api(string $method, string $uri, array $payload): void
    {
        $before = $this->catalogueSnapshot();
        $response = $this->json($method, $this->fill($uri), $this->fill($payload));

        // Already refused by auth:api (401) before the fix; no route (404/405) after it.
        $this->assertRefusedAndUnchanged($response, [401, 404, 405], "anonymous {$method} {$uri}", $before);
    }

    public function test_no_route_dispatches_to_a_removed_api_write_method(): void
    {
        $registered = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array($route->getActionName(), self::REMOVED_ACTIONS, true)) {
                $registered[] = implode('|', $route->methods()) . ' ' . $route->uri() . ' -> ' . $route->getActionName();
            }
        }

        $this->assertSame([], $registered, "API catalogue write routes are still registered:\n" . implode("\n", $registered));
    }

    public function test_authorised_web_admins_keep_character_management(): void
    {
        $id = $this->ids['character'];

        $this->assertGatePassed($this->actingAs($this->webAdmin('character.view', 'character.create'))->get('/admin/characters/create'));
        $this->assertGatePassed($this->actingAs($this->webAdmin('character.view', 'character.edit'))->put("/admin/characters/{$id}", []));
        $this->assertGatePassed($this->actingAs($this->webAdmin('character.view', 'character.delete'))->delete("/admin/characters/{$id}"));
    }

    public function test_web_users_without_character_write_permissions_are_refused(): void
    {
        $viewer = $this->webAdmin('character.view');
        $id = $this->ids['character'];

        $this->actingAs($viewer)->get('/admin/characters/create')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/characters', [])->assertForbidden();
        $this->actingAs($viewer)->put("/admin/characters/{$id}", [])->assertForbidden();
        $this->actingAs($viewer)->delete("/admin/characters/{$id}")->assertForbidden();
    }

    public function test_anonymous_web_visitors_cannot_reach_character_management(): void
    {
        $this->get('/admin/characters/create')->assertRedirectContains('login');
        $this->post('/admin/characters', [])->assertRedirectContains('login');
    }

    public function test_public_character_reads_still_work(): void
    {
        $id = $this->ids['character'];

        $this->getJson('/api/characters')->assertOk()->assertJsonFragment(['name' => 'Lockdown Pup']);
        $this->getJson("/api/characters/{$id}")->assertOk()->assertJsonFragment(['name' => 'Lockdown Pup']);
        $this->getJson('/api/characters/region/us')->assertOk()->assertJsonFragment(['name' => 'Lockdown Pup']);
        $this->getJson("/api/character-detail/{$id}/us")->assertOk();
        $this->getJson("/api/character-details/{$id}/us")->assertOk();
    }

    public function test_member_reads_and_channel_follow_still_work(): void
    {
        $member = $this->apiMember();

        $this->actingAs($member, 'api')->getJson('/api/categories/' . $this->ids['category'])->assertOk();
        $this->actingAs($member, 'api')->getJson('/api/regions/' . $this->ids['region'])->assertOk();
        $this->actingAs($member, 'api')->getJson('/api/channels/' . $this->ids['channel'])->assertOk();
        $this->actingAs($member, 'api')->getJson('/api/character-tags/' . $this->ids['tag'])->assertOk();
        $this->actingAs($member, 'api')->getJson('/api/character-roles/' . $this->ids['role'])->assertOk();

        $this->actingAs($member, 'api')->postJson('/api/channels/' . $this->ids['channel'] . '/follow')->assertSuccessful();
        $this->assertDatabaseHas('follow_channels', ['user_id' => $member->id, 'channel_id' => $this->ids['channel']]);
    }

    /** A verified, unblocked API member - the account any public sign-up produces. */
    private function apiMember(string ...$extraRoles): ApiUser
    {
        $member = ApiUser::findOrFail(User::factory()->create()->id);
        $member->forceFill(['role' => 'user', 'is_verified' => true, 'is_blocked' => false])->save();
        $member->assignRole(Role::findOrCreate('user', 'api'));
        foreach ($extraRoles as $role) {
            $member->assignRole(Role::findOrCreate($role, 'api'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $member->fresh();
    }

    private function webAdmin(string ...$permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function assertGatePassed(TestResponse $response): void
    {
        $this->assertNotSame(403, $response->status(), 'the permission gate should have allowed this verb');
    }

    private function assertRefusedAndUnchanged(TestResponse $response, array $allowed, string $attempt, array $before): void
    {
        $after = $this->catalogueSnapshot();
        $message = json_decode((string) $response->getContent(), true)['message'] ?? '';
        // The failure message records what the request actually did to the data, so a real
        // mutation is never mistaken for a validation-only response (or the reverse).
        $this->assertContains(
            $response->status(),
            $allowed,
            sprintf(
                '%s should be refused (%s); it got HTTP %d %s | data: %s',
                $attempt,
                implode('/', $allowed),
                $response->status(),
                is_string($message) ? $message : '',
                $this->describeChanges($before, $after)
            )
        );
        $this->assertEquals($before, $after, "{$attempt} changed catalogue data: " . $this->describeChanges($before, $after));
    }

    private function describeChanges(array $before, array $after): string
    {
        $changes = [];
        foreach ($before as $table => $rows) {
            $old = array_column($rows, null, 'id');
            $new = array_column($after[$table], null, 'id');
            foreach (array_diff_key($new, $old) as $id => $row) {
                $changes[] = "{$table} #{$id} created";
            }
            foreach (array_diff_key($old, $new) as $id => $row) {
                $changes[] = "{$table} #{$id} deleted";
            }
            foreach (array_intersect_key($old, $new) as $id => $row) {
                $fields = array_keys(array_diff_assoc(array_map('strval', array_filter($new[$id], 'is_scalar')), array_map('strval', array_filter($row, 'is_scalar'))));
                $fields = array_values(array_diff($fields, ['updated_at']));
                if ($fields) {
                    $label = $row['name'] ?? $row['region_name'] ?? '';
                    $changes[] = sprintf('%s #%d updated (%s) [%s -> %s]', $table, $id, implode(', ', $fields), $label, $new[$id]['name'] ?? $new[$id]['region_name'] ?? '');
                }
            }
        }

        return $changes ? implode('; ', $changes) : 'no catalogue data changed';
    }

    /** Every row of the six catalogue tables, so any create, update or delete shows up. */
    private function catalogueSnapshot(): array
    {
        $snapshot = [];
        foreach (['characters', 'categories', 'regions', 'channels', 'character_tags', 'character_roles'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    /** Replaces {key} placeholders (in a URI or in payload values) with fixture ids. */
    private function fill(string|array $template): string|array
    {
        if (is_array($template)) {
            return array_map(fn ($value) => is_string($value) ? $this->fill($value) : $value, $template);
        }

        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) $this->ids[$m[1]], $template);
    }
}
