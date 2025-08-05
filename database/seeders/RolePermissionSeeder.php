<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // Users
            'user.view',
            'user.create',
            'user.edit',
            'user.delete',

            // Categories
            'category.view',
            'category.create',
            'category.edit',
            'category.delete',

            // Channels
            'channel.view',
            'channel.create',
            'channel.edit',
            'channel.delete',

            // Characters
            'character.view',
            'character.create',
            'character.edit',
            'character.delete',

            // Videos
            'video.view',
            'video.create',
            'video.edit',
            'video.delete',
            'video.publish',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $subAdmin = Role::firstOrCreate(['name' => 'sub_admin']);
        $user = Role::firstOrCreate(['name' => 'user']);

        // Assign all permissions to super admin
        $superAdmin->syncPermissions(Permission::all());

        // Assign selected permissions to sub admin
        $subAdmin->syncPermissions([
            // Categories
            'category.view',
            'category.create',
            'category.edit',
            'category.delete',

            // Channels
            'channel.view',
            'channel.create',
            'channel.edit',
            'channel.delete',

            // Characters
            'character.view',
            'character.create',
            'character.edit',
            'character.delete',

            // Videos
            'video.view',
            'video.create',
            'video.edit',
            'video.delete',
        ]);

        // Assign no permissions to user
        $user->syncPermissions([]);
    }
}
