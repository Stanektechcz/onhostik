<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\JitElevation;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

it('seeds every catalog role with only known permissions', function () {
    foreach (RoleCatalog::all() as $key => $role) {
        foreach ($role['permissions'] as $permission) {
            expect(PermissionCatalog::exists($permission))->toBeTrue("{$key} references unknown permission {$permission}");
        }
    }
    expect(DB::table('roles')->count())->toBe(count(RoleCatalog::all()))
        ->and(DB::table('permission_definitions')->count())->toBe(count(PermissionCatalog::all()));
});

it('grants the owner organization-scoped capabilities and nothing outside the organization', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$other, $otherOrg] = $this->customerWithOrganization();
    $auth = app(Authorizer::class);

    expect($auth->can($owner, 'service.manage', CommandScope::organization($org->id)))->toBeTrue()
        ->and($auth->can($owner, 'service.manage', CommandScope::organization($otherOrg->id)))->toBeFalse()
        ->and($auth->can($owner, 'service.manage'))->toBeFalse()
        ->and($auth->can($owner, 'staff.customer.read', CommandScope::organization($org->id)))->toBeFalse()
        ->and($auth->can($owner, 'nonexistent.permission', CommandScope::organization($org->id)))->toBeFalse();
});

it('scopes customer roles: viewer cannot manage, billing admin can top up', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $viewer = $this->customer();
    $billing = $this->customer();
    $service = app(OrganizationService::class);
    $service->attachMember($org, $viewer, 'viewer', CommandContext::system());
    $service->attachMember($org, $billing, 'billing_admin', CommandContext::system());
    $auth = app(Authorizer::class);
    $scope = CommandScope::organization($org->id);

    expect($auth->can($viewer, 'service.read', $scope))->toBeTrue()
        ->and($auth->can($viewer, 'service.manage', $scope))->toBeFalse()
        ->and($auth->can($billing, 'billing.wallet.topup', $scope))->toBeTrue()
        ->and($auth->can($billing, 'compute.vm.delete', $scope))->toBeFalse();
});

it('refuses staff roles inside an organization', function () {
    [$owner, $org] = $this->customerWithOrganization();
    app(OrganizationService::class)->attachMember($org, $this->customer(), 'sre', CommandContext::system());
})->throws(DomainError::class);

it('applies global staff bindings everywhere and honours JIT elevation TTL', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $l1 = $this->staff('support_l1');
    $auth = app(Authorizer::class);
    $scope = CommandScope::organization($org->id);

    expect($auth->can($l1, 'staff.customer.read', $scope))->toBeTrue()
        ->and($auth->can($l1, 'staff.service.manage', $scope))->toBeFalse();

    JitElevation::query()->create([
        'user_id' => $l1->id, 'role_key' => 'cloud_vps_admin', 'scope_type' => 'global', 'reason' => 'Ticket 4821',
        'state' => 'approved', 'approver_id' => 'usr_other', 'approved_at' => now(), 'expires_at' => now()->addHour(),
    ]);
    $auth->forget($l1);
    expect($auth->can($l1, 'staff.service.manage', $scope))->toBeTrue();

    JitElevation::query()->update(['expires_at' => now()->subMinute()]);
    $auth->forget($l1);
    expect($auth->can($l1, 'staff.service.manage', $scope))->toBeFalse();
});

it('classifies risk from the catalog', function () {
    expect(PermissionCatalog::requiresStepUp('billing.refund.execute'))->toBeTrue()
        ->and(PermissionCatalog::requiresFourEyes('billing.refund.execute'))->toBeFalse()
        ->and(PermissionCatalog::requiresFourEyes('dns.global.write'))->toBeTrue()
        ->and(PermissionCatalog::requiresStepUp('service.read'))->toBeFalse();
});
