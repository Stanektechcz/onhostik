<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleResolver;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Platform\Commands\CommandScope;

/*
 * TASK-0043 (permission program S1-03, principle 11 "existing customers are never changed en masse silently"): splitting
 * `service.manage` into "operate" and "delete data" must not change what anybody may do today. The roles and the action map of
 * the task's base (cc9501c) are frozen in fixtures/before-task-0043.php; every preset and every `svc_*` share stored before the
 * release reaches exactly the service actions it reached then — with the same token scope, the same staff key and the same risk.
 */

/** The two keys `service.manage` was split into; every role that held `service.manage` holds them, no other role does. */
const PUC_SPLIT_KEYS = ['service.data.delete', 'service.operate'];

/** The two presets S1-03 adds (share capabilities `operate`, `data_delete`). */
const PUC_NEW_ROLES = ['svc_data_delete', 'svc_operate'];

/** ServiceActionCommand::STAFF_PERMISSIONS at cc9501c. */
const PUC_STAFF_BEFORE = ['service.manage' => 'staff.service.manage', 'service.delete' => 'staff.service.delete', 'staff.service.delete' => 'staff.service.delete'];

/**
 * B6 role hygiene (audit 2026-10, decision R8), made on this branch on purpose and nowhere else: the only differences from the
 * base besides the split. None of them touches a customer service action (the action test below holds for every role).
 *
 * @var array<string, array{add: list<string>, drop: list<string>}>
 */
const PUC_B6_CHANGES = [
    'security_auditor' => ['add' => [], 'drop' => ['security.settings.manage']], // an auditor reads (no HIGH write)
    'support_manager' => ['add' => ['provisioning.operation.read', 'staff.chargeback.decide'], 'drop' => ['support.customer_impersonate']], // SS-7
    'support_l2' => ['add' => ['staff.chargeback.decide'], 'drop' => []],
    'support_l3' => ['add' => ['staff.chargeback.decide'], 'drop' => []],
    'platform_owner' => ['add' => ['staff.chargeback.decide'], 'drop' => []], // every staff key, the new one too
];

/** @return array{roles: array<string, list<string>>, actions: array<string, string>} the base, with the B6 changes applied */
function pucBefore(): array
{
    $before = require __DIR__.'/fixtures/before-task-0043.php';
    foreach (PUC_B6_CHANGES as $role => $change) {
        $before['roles'][$role] = array_values(array_merge(array_diff($before['roles'][$role], $change['drop']), $change['add']));
    }

    return $before;
}

/** @param list<string> $keys @return list<string> */
function pucSorted(array $keys): array
{
    $keys = array_values(array_unique($keys));
    sort($keys);

    return $keys;
}

it('keeps every preset of the base: its old permissions, plus exactly the two split keys where it held service.manage', function () {
    $before = pucBefore()['roles'];

    foreach ($before as $role => $permissions) {
        $now = RoleResolver::grantable($role);
        expect(pucSorted(array_diff($now, PUC_SPLIT_KEYS)))->toBe(pucSorted($permissions), "{$role} lost or gained a permission")
            ->and(pucSorted(array_intersect($now, PUC_SPLIT_KEYS)))->toBe(in_array('service.manage', $permissions, true) ? PUC_SPLIT_KEYS : [], "{$role}: the split keys follow service.manage");
    }
    // the only new roles are the two presets; nothing else appeared
    expect(pucSorted(array_diff(array_keys(RoleResolver::catalogue()), array_keys($before))))->toBe(PUC_NEW_ROLES);
    // and the database the authorizer reads says the same after the seeder (what a deploy runs)
    foreach (array_keys($before) as $role) {
        $stored = DB::table('role_permissions')->where('role_key', $role)->pluck('permission_key')->map(fn ($p) => (string) $p)->all();
        expect(pucSorted($stored))->toBe(pucSorted(RoleResolver::grantable($role)), "{$role} seeded");
    }
});

it('lets every preset run exactly the service actions it ran before, with the same token scope, staff key and risk', function () {
    ['roles' => $roles, 'actions' => $actions] = pucBefore();
    expect(array_keys(ServiceActionCommand::PERMISSIONS))->toBe(array_keys($actions)); // no action came or went

    foreach ($actions as $action => $old) {
        $new = ServiceActionCommand::permissionFor($action);
        expect(TokenScopes::for($new))->toBe(TokenScopes::for($old), "{$action}: token scope")
            ->and(ServiceActionCommand::STAFF_PERMISSIONS[$new] ?? $new)->toBe(PUC_STAFF_BEFORE[$old] ?? $old, "{$action}: staff key")
            ->and(PermissionCatalog::floor($new))->toBe(PermissionCatalog::floor($old), "{$action}: risk floor")
            ->and(PermissionCatalog::risk($new))->toBe(PermissionCatalog::risk($old), "{$action}: catalogue risk");
        foreach ($roles as $role => $permissions) {
            expect(in_array($new, RoleResolver::grantable($role), true))->toBe(in_array($old, $permissions, true), "{$role} × {$action}");
        }
    }
});

it('resolves shares stored before the release to the same actions through the authorizer', function () {
    ['roles' => $roles, 'actions' => $actions] = pucBefore();
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $scope = CommandScope::resource($service->id, $org->id, $service->project_id);

    foreach (['svc_view', 'svc_manage', 'svc_console', 'svc_backups', 'svc_restore', 'svc_assistant'] as $role) {
        $guest = User::query()->create(['email' => "{$role}@puc.test", 'name' => $role, 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
        OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $guest->id, 'state' => 'active', 'role_key' => 'guest', 'joined_at' => now()]);
        // the rows ServiceAccessService::bind wrote before the release: one resource binding per capability
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'guest', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => $role, 'scope_type' => 'resource', 'scope_id' => $service->id, 'organization_id' => $org->id]);

        foreach ($actions as $action => $old) {
            expect(app(Authorizer::class)->can($guest, ServiceActionCommand::permissionFor($action), $scope))->toBe(in_array($old, $roles[$role], true), "{$role} × {$action}");
        }
    }
    // a stored share names capabilities, not permissions: `manage` still carries what it carried, and more only by the split
    expect(pucSorted(array_diff(GrantPolicy::capabilityPermissions(['view', 'manage']), PUC_SPLIT_KEYS)))->toBe(pucSorted(array_merge($roles['svc_view'], $roles['svc_manage'])));
});
