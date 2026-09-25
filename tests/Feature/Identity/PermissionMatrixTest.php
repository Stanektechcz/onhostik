<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
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
        'set a service panel password' => 'service.panel_account.manage', // owner decision 15: the organization owner alone
    ];
    $all = array_keys($operations);
    $matrix = [
        'owner' => $all,
        'org_admin' => array_values(array_diff($all, ['close the organization', 'set a service panel password'])),
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

it('gives the password of a service\'s panel account to the organization owner alone: no admin, operator, shared or staff role holds it (owner decision 15)', function () {
    $holders = array_keys(array_filter(RoleCatalog::all(), fn (array $role) => in_array('service.panel_account.manage', $role['permissions'], true)));
    expect($holders)->toBe(['owner'])
        ->and(PermissionCatalog::all()['service.panel_account.manage'])->toMatchArray(['risk' => PermissionCatalog::HIGH, 'audience' => 'customer'])
        ->and(PermissionCatalog::OWNER_ONLY)->toContain('organization.close')->toContain('service.panel_account.manage')
        ->and(ServiceActionCommand::permissionFor('panel.password'))->toBe('service.panel_account.manage');
    // what the organization admin may not do is written down in one place, and it is at least what only the owner may do
    expect(array_intersect(RoleCatalog::all()['org_admin']['permissions'], PermissionCatalog::OWNER_ONLY))->toBe([]);
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

it('lets every role read what it may change: no support role could open a ticket it was allowed to answer', function () {
    // `support.ticket.read` was held by the platform owner alone: the queue and the ticket detail answered 403 to every support
    // role, which could reply to (`manage`) and assign tickets it could not open. The same for finance and a customer's
    // invoice, and for the backup administrator and a backup.
    $catalog = array_keys(PermissionCatalog::all());
    $gaps = [];
    foreach (RoleCatalog::all() as $key => $role) {
        $held = $role['permissions'];
        if (in_array('*', $held, true)) {
            continue;
        }
        foreach ($held as $permission) {
            foreach (['.manage', '.assign', '.decide', '.write', '.update', '.create', '.delete'] as $suffix) {
                $read = str_ends_with($permission, $suffix) ? substr($permission, 0, -strlen($suffix)).'.read' : null;
                if ($read !== null && in_array($read, $catalog, true) && ! in_array($read, $held, true)) {
                    $gaps[] = "{$key}: {$permission} without {$read}";
                }
            }
        }
    }
    expect(array_values(array_unique($gaps)))->toBe([]);
});

it('asks staff endpoints only for permissions some staff role holds besides the platform owner', function () {
    // two operations boards asked for `service.read` — a customer's permission on their own services — at the global scope: only the
    // platform owner could open them, and the owner is who tested them
    $held = [];
    foreach (RoleCatalog::all() as $key => $role) {
        if ($role['staff'] && $key !== 'platform_owner' && ! in_array('*', $role['permissions'], true)) {
            $held = array_merge($held, $role['permissions']);
        }
    }
    $missing = [];
    foreach (glob(app_path('Http/Controllers/Api/V1/Staff/*.php')) ?: [] as $file) {
        preg_match_all('/(?:authorize|can)\(\$request, \'([a-z0-9_.]+)\', CommandScope::global\(\)/', (string) file_get_contents($file), $m);
        foreach (array_unique($m[1]) as $permission) {
            if (! in_array($permission, $held, true)) {
                $missing[] = basename($file, '.php').': '.$permission;
            }
        }
    }
    expect($missing)->toBe([]);
});

it('opens the support queue and the operations boards to the roles that work them', function () {
    $this->actingAs($this->staff('support_l1'), 'sanctum');
    $this->getJson('/v1/staff/tickets')->assertOk(); // it used to be 403 for every support role
    $this->getJson('/v1/staff/provisioning/deletions')->assertOk();
    $this->getJson('/v1/staff/provisioning/ssh-key-revocations')->assertOk();
});
