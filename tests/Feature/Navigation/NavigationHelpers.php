<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;

/* Shared by the navigation tests (one global function namespace: the helper is defined once). */

if (! function_exists('navApiStaff')) {
    /** A member of staff bound to a one-off global role that carries exactly `$permissions`. */
    function navApiStaff(array $permissions): User
    {
        $role = 'navtest_'.Str::lower(Str::random(10));
        DB::table('roles')->insert(['key' => $role, 'name' => $role, 'scope_type' => 'global', 'is_staff' => true, 'assignable' => false, 'created_at' => now(), 'updated_at' => now()]);
        if ($permissions !== []) {
            DB::table('role_permissions')->insert(array_map(fn (string $p) => ['role_key' => $role, 'permission_key' => $p], array_values(array_unique($permissions))));
        }
        $user = User::factory()->staff()->create();
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);

        return $user;
    }
}
