<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\RoleResolver;

/**
 * Idempotent: re-running syncs catalog roles/permissions without touching custom roles.
 *
 * TASK-0037 (permission program D6, P0-11): the seeder runs on every deploy against live traffic. It used to delete all rows
 * of a role and insert them again, one role after another and outside a transaction — between the two statements every
 * member of that role held nothing (a 403 storm on deploy), and a failure half-way left roles empty. Now one transaction,
 * and within it only the difference: rows the catalogue dropped are deleted by name, missing rows are inserted, the rest
 * is never touched, so no role is empty at any point.
 */
final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
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

            $roles = RoleCatalog::all();
            foreach (RoleResolver::catalogue() as $key => $permissions) {
                $role = $roles[$key];
                DB::table('roles')->updateOrInsert(['key' => $key], [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'scope_type' => $role['scope'],
                    'is_staff' => $role['staff'],
                    'assignable' => $key !== 'platform_owner',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $stored = DB::table('role_permissions')->where('role_key', $key)->pluck('permission_key')->all();
                $dropped = array_values(array_diff($stored, $permissions));
                if ($dropped !== []) {
                    DB::table('role_permissions')->where('role_key', $key)->whereIn('permission_key', $dropped)->delete();
                }
                $missing = array_values(array_diff($permissions, $stored));
                if ($missing !== []) {
                    DB::table('role_permissions')->insert(array_map(fn (string $permission) => ['role_key' => $key, 'permission_key' => $permission], $missing));
                }
            }
        });
    }
}
