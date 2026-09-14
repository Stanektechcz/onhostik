<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;

/** Idempotent: re-running syncs catalog roles/permissions without touching custom roles. */
final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        foreach (PermissionCatalog::all() as $key => $def) {
            DB::table('permission_definitions')->updateOrInsert(['key' => $key], [
                'description' => $def['description'],
                'risk' => $def['risk'],
                'step_up' => PermissionCatalog::requiresStepUp($key),
                'four_eyes' => PermissionCatalog::requiresFourEyes($key),
                'audience' => $def['audience'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (RoleCatalog::all() as $key => $role) {
            DB::table('roles')->updateOrInsert(['key' => $key], [
                'name' => $role['name'],
                'description' => $role['description'],
                'scope_type' => $role['scope'],
                'is_staff' => $role['staff'],
                'assignable' => $key !== 'platform_owner',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('role_permissions')->where('role_key', $key)->delete();
            DB::table('role_permissions')->insert(array_map(
                fn (string $permission) => ['role_key' => $key, 'permission_key' => $permission],
                $role['permissions'],
            ));
        }
    }
}
