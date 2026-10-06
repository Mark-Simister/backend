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

            // FU-6: a single permission for the admin-chrome routes that had no natural
            // permission family and were only super-admin-reachable by accident (the
            // Gate::before bypass over an unseeded permission). Granted to super_admin
            // ONLY, below - never sub_admin. It DELIBERATELY bundles unrelated concerns
            // (site images, global colours, themes, top-categories, tag creation, and
            // product-message deletion). Split it into per-domain permissions BEFORE it is
            // ever granted to a sub_admin, or a colour-editor also gains message deletion.
            'settings.manage',

            // Admin Content & Media Operations (design record section 11): the complete
            // frozen seed matrix. Every action with public consequences has its own
            // explicit permission. The bestfor.* permissions are seeded now, with the rest
            // of the matrix; no route or screen uses them until the Best For curation
            // sprint (Sprint D).
            'review.view',
            'review.edit',
            'review.qa',
            'review.hold',
            'review.release',
            'review.withdraw',
            'media.view',
            'media.manage',
            'bestfor.view',
            'bestfor.edit',
            'bestfor.publish',
            'bestfor.hold',
            'bestfor.unpublish',   // unpublish and restore - super_admin only
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

        // Admin Content & Media Operations roles (design record section 11), least
        // privilege. sub_admin deliberately gains nothing new. One authorised person may
        // hold both review.qa and review.release (M4); QA pass and Release remain two
        // separately permissioned, separately audited transitions.
        Role::firstOrCreate(['name' => 'publisher'])->syncPermissions([
            'review.view', 'review.edit', 'review.qa', 'review.hold', 'review.release', 'review.withdraw',
            'media.view', 'media.manage',
            'bestfor.view', 'bestfor.edit', 'bestfor.publish', 'bestfor.hold',
        ]);
        Role::firstOrCreate(['name' => 'qa_reviewer'])->syncPermissions([
            'review.view', 'review.qa', 'review.hold',
            'media.view',
            'bestfor.view',
        ]);
        Role::firstOrCreate(['name' => 'editor'])->syncPermissions([
            'review.view', 'review.edit',
            'media.view', 'media.manage',
            'bestfor.view', 'bestfor.edit',
        ]);
    }
}
