<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `role`, `is_verified` and `is_blocked` are privilege columns. They are removed from
 * User::$fillable and listed in $guarded, so no `fill()`, `create()` or `update()` can set
 * them from request data — only an explicit assignment (or forceFill) after an
 * authorization check.
 *
 * The trap this guards against: ProfileController::update() does
 * `$request->user()->fill($request->validated())`. Today ProfileUpdateRequest::rules()
 * returns only name and email. One added line in rules() would have made every user able
 * to promote themselves.
 *
 * Note that $guarded alone would not have worked. Model::isFillable() returns true as soon
 * as the key is in $fillable and never consults $guarded — so listing `role` in both would
 * have read like protection while changing nothing.
 */
class UserMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_not_mass_assignable(): void
    {
        $user = new User();
        $user->fill(['name' => 'Test', 'role' => 'super_admin']);

        $this->assertSame('Test', $user->name);
        $this->assertNull($user->role, 'role must never be fillable');
    }

    public function test_is_verified_and_is_blocked_are_not_mass_assignable(): void
    {
        $user = new User();
        $user->fill(['name' => 'Test', 'is_verified' => true, 'is_blocked' => true]);

        $this->assertNull($user->is_verified);
        $this->assertNull($user->is_blocked);
    }

    public function test_create_silently_ignores_the_privilege_columns(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'is_blocked' => true,
        ]);

        $this->assertNotSame('super_admin', $user->fresh()->role);
        $this->assertNotTrue((bool) $user->fresh()->is_blocked);
    }

    /** A profile update cannot escalate, even if someone widens ProfileUpdateRequest. */
    public function test_a_profile_update_cannot_set_a_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Renamed',
            'email' => $user->email,
            'role' => 'super_admin',
        ])->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Renamed', $user->name);
        $this->assertNotSame('super_admin', $user->role);
    }

    /** Explicit assignment still works — that is how the seeder and console mint admins. */
    public function test_an_explicit_assignment_still_sets_the_role(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['role' => 'sub_admin'])->save();

        $this->assertSame('sub_admin', $user->fresh()->role);
    }
}
