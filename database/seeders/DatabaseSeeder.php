<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Reviews\AdminPayloadGuard;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Refuse to run against a database that holds admin-published review pages. The
        // content seeders rewrite `videos` and the `video_region` pivot, which would
        // silently change which regions a live public page serves. A bare `db:seed` runs
        // THIS class - the realistic accident. See App\Support\Reviews\AdminPayloadGuard.
        AdminPayloadGuard::assertSafe($this->command, 'DatabaseSeeder');

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
        RolePermissionSeeder::class,
    ]);
    }
}
