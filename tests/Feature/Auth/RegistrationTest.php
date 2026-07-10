<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There is no self-service registration on this backend.
 *
 * `GET/POST /register` used to sit behind `guest` middleware only, and
 * RegisteredUserController::store() granted `super_admin` on the web guard to every
 * account it created. `User` does not implement MustVerifyEmail, so the `verified`
 * middleware on /dashboard never blocked anything: a registrant was authenticated,
 * super-admin and on the dashboard in one request.
 *
 * These tests are the load-bearing part of the fix. Reintroducing either route — or the
 * controller behind it — fails the suite.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_screen_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_cannot_be_submitted(): void
    {
        $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_no_route_is_named_register(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('register'),
            'a named `register` route would let a Blade link resurrect the endpoint'
        );
    }

    /**
     * The controller itself is gone, so it cannot be re-wired by accident.
     *
     * Asserted on the file rather than class_exists(): a stale optimised classmap still
     * maps the class name to the deleted path, so class_exists() tries to include it and
     * raises an ErrorException instead of returning false.
     */
    public function test_the_registration_controller_no_longer_exists(): void
    {
        $this->assertFileDoesNotExist(app_path('Http/Controllers/Auth/RegisteredUserController.php'));
    }

    /** Admins are created by the seeder, not by a public endpoint. */
    public function test_the_seeder_is_the_supported_way_to_mint_an_admin(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
    }
}
