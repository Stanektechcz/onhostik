<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin', 'web');
        Role::findOrCreate('customer', 'web');
        Role::findOrCreate('support', 'web');

        // The access-admin gate checks the admin role directly; the permission
        // exists so future granular grants (e.g. support read-only) are possible.
        $accessAdmin = Permission::findOrCreate('access-admin', 'web');
        $admin->givePermissionTo($accessAdmin);
    }
}
