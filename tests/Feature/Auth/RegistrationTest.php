<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RegisteredUserController::store() calls assignRole('super_admin'), so registration
     * throws Spatie's RoleDoesNotExist against a database with no roles — which is what
     * RefreshDatabase leaves behind. Seeding the real RolePermissionSeeder keeps role
     * assignment inside the test's coverage instead of stubbing it out, and proves the
     * role name the controller uses matches the one the seeder creates.
     */
    private function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $this->seedRoles();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'name' => 'Test User']);
    }

    /**
     * Documents current behaviour — and it is not behaviour anyone should want. `POST
     * /register` sits behind `guest` middleware only, and every account it creates is
     * granted `super_admin` on the web guard. Anyone who can reach the admin backend can
     * make themselves an administrator of it.
     *
     * Asserted rather than quietly fixed: changing authentication behaviour on a
     * reachable admin host is not a test-cleanup decision. A deliberate fix will fail
     * this test, which is exactly the point — it forces the change to be reviewed.
     */
    public function test_registration_currently_grants_super_admin_to_anyone(): void
    {
        $this->seedRoles();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('super_admin'), 'public registration grants super_admin');
    }
}
