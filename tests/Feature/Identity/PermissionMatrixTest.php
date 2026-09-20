<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;

/*
 * The permission matrix (Brain card H11): what each customer role may and may not do, asked of the real authorizer with
 * the roles as they are seeded into the database — and the same questions about another organization, where every
 * answer is no. A change of the role catalog that moves a line of this table has to be made here on purpose.
 */

it('allows and denies the operations of every customer role as the matrix says, and nothing in another organization', function () {
    [, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $organizations = app(OrganizationService::class);
    $authorizer = app(Authorizer::class);

    // operation => permission; the columns of the matrix
    $operations = [
        'see services' => 'service.read', 'manage a service' => 'service.manage', 'open a console' => 'service.console', 'delete a service' => 'service.delete',
        'download a backup' => 'backup.download', 'restore a backup' => 'backup.restore',
        'manage members' => 'organization.members.manage', 'edit the organization' => 'organization.manage', 'close the organization' => 'organization.close',
        'top up the wallet' => 'billing.wallet.topup', 'place an order' => 'catalog.order.create', 'manage domains' => 'domain.manage', 'write DNS' => 'dns.zone.write', 'write a ticket' => 'support.ticket.write',
    ];
    $all = array_keys($operations);
    $matrix = [
        'owner' => $all,
        'org_admin' => array_values(array_diff($all, ['close the organization'])),
        'billing_admin' => ['see services', 'top up the wallet', 'place an order'],
        'domain_manager' => ['see services', 'manage domains', 'write DNS'],
        'dns_manager' => ['see services', 'write DNS'],
        'developer' => ['see services', 'manage a service', 'open a console', 'download a backup', 'write DNS', 'write a ticket'],
        'cloud_operator' => ['see services', 'manage a service', 'open a console', 'download a backup', 'restore a backup', 'write a ticket'],
        'game_operator' => ['see services', 'manage a service', 'open a console', 'download a backup', 'restore a backup', 'write a ticket'],
        'mail_manager' => ['see services', 'download a backup', 'write DNS', 'write a ticket'],
        'security_auditor' => ['see services'],
        'support_contact' => ['see services', 'write a ticket'],
        'viewer' => ['see services'],
    ];

    $here = CommandScope::organization($org->id);
    $elsewhere = CommandScope::organization($other->id);
    $wrong = [];
    foreach ($matrix as $role => $allowed) {
        $member = $this->customer();
        $role === 'owner'
            ? $organizations->transferOwnership($org, tap($member, fn ($m) => $organizations->attachMember($org, $m, 'org_admin', CommandContext::system('test'), true)), CommandContext::system('test'))
            : $organizations->attachMember($org, $member, $role, CommandContext::system('test'), true);
        $authorizer->forget($member);
        foreach ($operations as $operation => $permission) {
            $expected = in_array($operation, $allowed, true);
            if ($authorizer->can($member, $permission, $here) !== $expected) {
                $wrong[] = "{$role}: {$operation} should be ".($expected ? 'allowed' : 'denied');
            }
            if ($authorizer->can($member, $permission, $elsewhere)) {
                $wrong[] = "{$role}: {$operation} is allowed in ANOTHER organization";
            }
        }
    }
    expect($wrong)->toBe([]);
});

it('leaves no console to the break-glass account alone: every staff permission is held by a named role, except the three that ARE break-glass', function () {
    $held = [];
    foreach (RoleCatalog::all() as $key => $role) {
        if ($key !== 'platform_owner') {
            $held = array_merge($held, $role['permissions']);
        }
    }
    $orphans = array_values(array_diff(array_keys(array_filter(PermissionCatalog::all(), fn (array $p) => $p['audience'] === 'staff')), $held));
    sort($orphans);
    // pricing, notification templates, feature flags, loyalty and sandbox credit were checked by their consoles and held by nobody:
    // usable only as PlatformOwner — "never a daily account" — so the daily account it became
    expect($orphans)->toBe(['iam.break_glass', 'provider.secret.view', 'secret.rotate']);
});
