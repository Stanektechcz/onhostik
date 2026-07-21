<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role and permission management from the UI (audit G96) — previously roles
 * existed only in a seeder, so changing who can do what meant a deploy.
 *
 * Safety rails that are about data integrity, not permissions:
 *  - the 'admin' role cannot be deleted or stripped of permissions, or the
 *    system would lock everyone out;
 *  - an admin cannot remove their OWN admin role for the same reason.
 */
class RolePermissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9\-_]+$/', 'unique:roles,name'],
        ]);

        Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        activity('system')
            ->causedBy($request->user())
            ->withProperties(['role' => $validated['name']])
            ->log('role.created');

        return back()->with('status', "Role {$validated['name']} byla vytvořena.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'admin') {
            return back()->withErrors(['role' => 'Roli admin nelze smazat — přišli byste o přístup do systému.']);
        }

        $name = $role->name;
        $role->delete();

        activity('system')
            ->causedBy($request->user())
            ->withProperties(['role' => $name])
            ->log('role.deleted');

        return back()->with('status', "Role {$name} byla smazána.");
    }

    /** Replace a role's permission set wholesale. */
    public function syncPermissions(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($role->name === 'admin' && empty($validated['permissions'])) {
            return back()->withErrors(['role' => 'Roli admin nelze odebrat všechna oprávnění.']);
        }

        $role->syncPermissions($validated['permissions'] ?? []);

        activity('system')
            ->causedBy($request->user())
            ->withProperties(['role' => $role->name, 'permissions' => $validated['permissions'] ?? []])
            ->log('role.permissions_synced');

        return back()->with('status', "Oprávnění role {$role->name} byla uložena.");
    }

    /** Grant or revoke a role on a user. */
    public function assign(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role'   => ['required', 'string', 'exists:roles,name'],
            'action' => ['required', 'in:assign,revoke'],
        ]);

        $isSelf = $request->user()?->id === $user->id;

        if ($validated['action'] === 'revoke' && $validated['role'] === 'admin' && $isSelf) {
            return back()->withErrors(['role' => 'Nemůžete si odebrat vlastní roli admin.']);
        }

        $validated['action'] === 'assign'
            ? $user->assignRole($validated['role'])
            : $user->removeRole($validated['role']);

        activity('system')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['role' => $validated['role'], 'action' => $validated['action']])
            ->log('user.role_' . $validated['action'] . 'ed');

        return back()->with('status', "Role {$validated['role']} byla uživateli " .
            ($validated['action'] === 'assign' ? 'přidělena.' : 'odebrána.'));
    }

    /** Create a permission so it can be attached to roles. */
    public function storePermission(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9\-_\.]+$/', 'unique:permissions,name'],
        ]);

        Permission::create(['name' => $validated['name'], 'guard_name' => 'web']);

        activity('system')
            ->causedBy($request->user())
            ->withProperties(['permission' => $validated['name']])
            ->log('permission.created');

        return back()->with('status', "Oprávnění {$validated['name']} bylo vytvořeno.");
    }
}
