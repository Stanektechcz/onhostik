<?php

declare(strict_types=1);

use Database\Seeders\AuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Compliance\Commands\DataRequestCommand;
use Onhost\Domain\Domains\Commands\DomainCommand;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\IdentityCommandAuthorizer;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\RoleResolver;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\GlobalCommand;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0037 — permission program P0-11, P0-12, P0-18 (IF-13, IF-10, IF-18).
 *
 *  IF-13  A command could declare itself below its own permission: IdentityCommandAuthorizer trusted `riskLevel()`, so
 *         `publish_ds` (a DS record at the registry, `dns.dnssec.manage` HIGH) ran without a step-up, and so did a registrant
 *         contact change and a dozen staff operations under HIGH permissions. Effective risk = max(declared, catalogue).
 *  IF-10  `ONHOST_FOUR_EYES=false` waived the second person for EVERY actor. It now waives only for the one person who could
 *         be the second person (the sole `iam.approval.decide` holder), and their own critical action waits a time lock
 *         (program §10 O4: 24 h, cancellable, notice) instead of running at once.
 *  IF-18  The staff support queue read with the customer key `support.ticket.read` at global scope; staff get their own keys
 *         before P0-15 takes customer keys away from staff roles.
 */

function rftUser(string $role, string $email): User
{
    $user = User::factory()->staff()->create(['email' => $email]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);

    return $user;
}

function rftContext(User $user, ?string $organizationId = null, string $session = 'rft-session'): CommandContext
{
    return new CommandContext('user', $user->id, $organizationId, null, '127.0.0.1', 'pest', $session);
}

function rftDecide(Command $command, CommandContext $context): array
{
    $decision = app(IdentityCommandAuthorizer::class)->authorize($command, $context);

    return [$decision->allowed, $decision->requirement];
}

/**
 * Every class that implements RiskAwareCommand, found on disk — a new one is covered by the sweep below without anybody
 * remembering to add it. @return list<class-string<RiskAwareCommand>>
 */
function rftRiskAwareClasses(): array
{
    $classes = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('domains'), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (! str_contains($source, 'RiskAwareCommand') || ! preg_match('/^namespace\s+([^;]+);/m', $source, $ns) || ! preg_match('/^(?:final\s+)?class\s+(\w+)/m', $source, $cls)) {
            continue;
        }
        $class = $ns[1].'\\'.$cls[1];
        if (class_exists($class) && is_subclass_of($class, RiskAwareCommand::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }
    sort($classes);

    return $classes;
}

/**
 * The operations of one risk-aware command, as payloads. A class with an `OPS` list is swept op by op, the service action
 * command action by action; the rest name their operations only in their doc block, so they are listed here.
 *
 * @return list<array<string,mixed>>
 */
function rftPayloadsOf(string $class): array
{
    $named = [
        'ChargebackCommand' => ['request', 'cancel'], 'ChargebackStaffCommand' => ['decide', 'settings'], 'WithdrawalCommand' => ['service', 'order'],
        'ApiTokenCommand' => ['create', 'revoke'], 'LoyaltyCommand' => ['award', 'levels', 'referral.review', 'streak.approve', 'campaign.upsert', 'mission.upsert'],
        'MarketplaceCommand' => ['order', 'accept', 'dispute', 'cancel', 'listing.create', 'listing.update', 'listing.state', 'order.start', 'order.deliver'],
        'MarketplaceStaffCommand' => ['listing.state', 'dispute.resolve'], 'PartnerCommand' => ['approve', 'state', 'payout.approve', 'payout.reject', 'payout.pay', 'tiers.recompute'],
        'CapacityCommand' => ['decide', 'budget'], 'ServiceArchiveCommand' => ['download', 'restore'],
        'ComplianceCommand' => ['cyber.open', 'cyber.transition', 'cyber.evidence', 'timer.submit', 'timer.waive', 'abuse.triage', 'abuse.notify', 'abuse.action', 'abuse.close', 'legal_hold', 'data_request.process'],
    ];
    if ($class === ServiceActionCommand::class) {
        return array_map(fn (string $action) => ['action' => $action, 'params' => []], array_keys(ServiceActionCommand::PERMISSIONS));
    }
    // operations a command answers although its OPS list does not name them, and payloads that change the permission
    $extra = [
        'ProvisioningCommand' => array_map(fn (string $op) => ['op' => $op], ['automation.toggle', 'automation.risk', 'bulk.start', 'node.state', 'game.eggs.map', 'game.eggs.sync', 'game.bootstrap', 'game.allocations.create',
            'game.node.update', 'game.operator_variable.set', 'game.migrate', 'game.evacuate', 'service.migrate', 'service.evacuate', 'rebalance.apply', 'tenant.sandbox', 'instance.prereqs']),
        'IncidentCommand' => [['op' => 'open', 'security' => true], ['op' => 'open', 'visibility' => 'internal'], ['op' => 'update', 'public' => false], ['op' => 'resolve', 'public' => false]],
        'ComplianceCommand' => [['op' => 'abuse.action', 'action' => 'service_suspended']],
    ];
    $ops = defined($class.'::OPS') ? (array) constant($class.'::OPS') : ($named[class_basename($class)] ?? []);
    $payloads = $ops === [] ? [[]] : array_map(fn (string $op) => ['op' => $op, 'kind' => 'deletion'], $ops);

    return [...$payloads, ...($extra[class_basename($class)] ?? [])];
}

// ── IF-13: risk never goes below the catalogue ─────────────────────────────────────────────────────────────────────

it('asks for a fresh step-up before a DS record goes to the registry, although DomainCommand called publish_ds ordinary', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $publish = new DomainCommand($org->id, 'rft-ds', ['op' => 'publish_ds', 'fqdn' => 'shop.cz']);
    expect(PermissionCatalog::risk('dns.dnssec.manage'))->toBe(PermissionCatalog::HIGH)->and($publish->riskLevel())->toBe(PermissionCatalog::NORMAL);

    expect(rftDecide($publish, rftContext($owner, $org->id)))->toBe([false, 'step_up']);
    // the registrant contact is the same kind of registry write (domain.registrant.change, HIGH)
    expect(rftDecide(new DomainCommand($org->id, 'rft-contact', ['op' => 'contact', 'fqdn' => 'shop.cz']), rftContext($owner, $org->id)))->toBe([false, 'step_up']);

    app(StepUpService::class)->grant($owner, 'totp', 'rft-session', '127.0.0.1');
    expect(rftDecide($publish, rftContext($owner, $org->id)))->toBe([true, null]);
    // what stays ordinary under an ordinary permission stays ordinary
    expect(rftDecide(new DomainCommand($org->id, 'rft-renew', ['op' => 'auto_renew', 'fqdn' => 'shop.cz', 'enabled' => true]), rftContext($owner, $org->id, 'rft-other')))->toBe([true, null]);
});

it('does not let a staff command talk a HIGH staff permission down to ordinary', function () {
    $infra = rftUser('infrastructure_admin', 'rft-infra@onhost.test');
    $node = new ProvisioningCommand('rft-node', ['op' => 'node.upsert', 'name' => 'n1']);
    expect(PermissionCatalog::risk($node->permission()))->toBe(PermissionCatalog::HIGH)->and($node->riskLevel())->toBe(PermissionCatalog::NORMAL);

    expect(rftDecide($node, rftContext($infra)))->toBe([false, 'step_up']);
});

it('keeps a customer CRITICAL permission at the step-up the customer can give, never the staff approval queue', function () {
    // program D8: customer approvals are not routed through the staff queue (the customer decider rule is S4-03). The GDPR
    // erasure (organization.close) and deleting a backup generation (backup.delete) stay HIGH for customers: the floor of a
    // customer CRITICAL permission is the step-up.
    [$owner, $org] = $this->customerWithOrganization();
    app(StepUpService::class)->grant($owner, 'totp', 'rft-session', '127.0.0.1');
    expect(PermissionCatalog::floor('organization.close'))->toBe(PermissionCatalog::HIGH)->and(PermissionCatalog::floor('backup.delete'))->toBe(PermissionCatalog::HIGH)
        ->and(PermissionCatalog::floor('compliance.legal_hold.manage'))->toBe(PermissionCatalog::CRITICAL);

    expect(rftDecide(new DataRequestCommand($org->id, 'rft-erase', ['op' => 'request', 'kind' => 'deletion']), rftContext($owner, $org->id)))->toBe([true, null])
        ->and(rftDecide(new ServiceActionCommand($org->id, 'rft-bdel', ['action' => 'backup.delete', 'params' => []]), rftContext($owner, $org->id)))->toBe([true, null]);
});

it('architecture: the list of operations allowed below the catalogue is empty and every entry would have to name a real permission', function () {
    expect(PermissionCatalog::LOWERED_RISK)->toBe([]);
    foreach (PermissionCatalog::LOWERED_RISK as $command => $permissions) {
        foreach ((array) $permissions as $permission => $reason) {
            expect(PermissionCatalog::exists((string) $permission))->toBeTrue("{$command}: {$permission}")->and(trim((string) $reason))->not->toBe('');
        }
    }
});

it('architecture: no operation of any risk-aware command runs below its permission — every one of them is swept', function () {
    $owner = rftUser('platform_owner', 'rft-sweep@onhost.test'); // holds everything but the owner-only panel account; no step-up
    $authorizer = app(Authorizer::class);
    $classes = rftRiskAwareClasses();
    expect(count($classes))->toBeGreaterThan(25); // the scan works (34 when this was written)

    $checked = 0;
    $below = [];
    foreach ($classes as $class) {
        foreach (rftPayloadsOf($class) as $i => $payload) {
            $command = is_subclass_of($class, GlobalCommand::class) ? new $class("rft-{$i}", $payload) : new $class('org_rft_sweep', "rft-{$i}", $payload);
            $permission = $command->permission();
            if ($permission === null || ! $authorizer->can($owner, $permission, $command->scope())) {
                continue;
            }
            $floor = PermissionCatalog::floor($permission);
            if ($floor === PermissionCatalog::NORMAL) {
                continue;
            }
            $checked++;
            [$allowed, $requirement] = rftDecide($command, rftContext($owner));
            if ($allowed || $requirement !== 'step_up') {
                $below[] = $command->name()." ({$permission} is {$floor})";
            }
        }
    }
    expect($below)->toBe([])->and($checked)->toBeGreaterThan(60);
});

// ── IF-10: the single-operator waiver belongs to the single operator, and waits a time lock ───────────────────────

it('asks everybody but the only person who may approve for a second person, also with ONHOST_FOUR_EYES=false', function () {
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $solo = rftUser('platform_owner', 'rft-solo@onhost.test');        // the one person who may decide approvals
    $legal = rftUser('compliance_legal', 'rft-legal@onhost.test');    // may place a hold, may not decide approvals
    app(StepUpService::class)->grant($legal, 'totp', null, '127.0.0.1');
    $url = "/v1/staff/customers/{$org->id}/legal-hold";
    $body = ['hold' => true, 'reason' => 'Žádost PČR č. j. KRPA-37/2026'];

    $refused = $this->actingAs($legal, 'sanctum')->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect($org->fresh()->settings['legal_hold'] ?? false)->toBeFalse();
    $id = (string) $refused->json('approval_id');
    expect(data_get(Approval::query()->findOrFail($id)->payload, 'time_lock'))->toBeNull(); // an ordinary request for the second person

    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $this->actingAs($solo, 'sanctum')->withHeader('Idempotency-Key', 'rft-approve')->postJson("/v1/staff/approvals/{$id}/decision", ['decision' => 'approved'])->assertOk();
    $this->actingAs($legal, 'sanctum')->postJson($url, $body)->assertOk()->assertJsonPath('legal_hold', true);
    expect(AuditEvent::query()->where('action', 'compliance.legal_hold')->where('result', 'succeeded')->latest('id')->firstOrFail()->approval_ids)->toBe([$id]);
});

it('holds the only approver\'s own critical action for the time lock, tells about it, and runs it when repeated after the delay', function () {
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $solo = rftUser('platform_owner', 'rft-alone@onhost.test');
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $url = "/v1/staff/customers/{$org->id}/legal-hold";
    $body = ['hold' => true, 'reason' => 'Žádost soudu sp. zn. 37 C 1/2026'];
    $this->actingAs($solo, 'sanctum');

    $refused = $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->assertJsonPath('approval_state', 'pending');
    $id = (string) $refused->json('approval_id');
    $row = Approval::query()->findOrFail($id);
    expect(data_get($row->payload, 'time_lock.hours'))->toBe(24)
        ->and((int) round(now()->diffInHours(Carbon::parse((string) data_get($row->payload, 'time_lock.not_before')))))->toBe(24)
        ->and(OutboxMessage::query()->where('name', 'iam.approval.time_locked')->where('aggregate_id', $id)->exists())->toBeTrue()
        ->and($org->fresh()->settings['legal_hold'] ?? false)->toBeFalse();

    // repeating it at once changes nothing and opens nothing new
    expect($this->postJson($url, $body)->assertForbidden()->json('approval_id'))->toBe($id)->and(Approval::query()->count())->toBe(1);

    $this->travel(24)->hours();
    $this->travel(1)->minutes();
    $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'step_up_required'); // a day later the step-up is fresh again, not left over
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $this->postJson($url, $body)->assertOk()->assertJsonPath('legal_hold', true);
    expect(Approval::query()->findOrFail($id)->state)->toBe('consumed')
        ->and(AuditEvent::query()->where('action', 'compliance.legal_hold')->where('result', 'succeeded')->latest('id')->firstOrFail()->approval_ids)->toBe(['waived:single-operator']);
});

it('lets the only approver cancel their own time-locked action, which then never runs', function () {
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $solo = rftUser('platform_owner', 'rft-cancel@onhost.test');
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $url = "/v1/staff/customers/{$org->id}/legal-hold";
    $body = ['hold' => true, 'reason' => 'Omylem zadané zadržení'];
    $this->actingAs($solo, 'sanctum');
    $id = (string) $this->postJson($url, $body)->assertForbidden()->json('approval_id');

    // their own request may not be approved by them (that would be the silent bypass again), but it may be cancelled
    $this->withHeader('Idempotency-Key', 'rft-self-approve')->postJson("/v1/staff/approvals/{$id}/decision", ['decision' => 'approved'])->assertForbidden()->assertJsonPath('error', 'approval_own_request');
    $this->withHeader('Idempotency-Key', 'rft-self-cancel')->postJson("/v1/staff/approvals/{$id}/decision", ['decision' => 'rejected', 'note' => 'omyl'])->assertOk()->assertJsonPath('state', 'cancelled');

    $this->travel(25)->hours();
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $again = $this->withHeader('Idempotency-Key', 'rft-again')->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect($again->json('approval_id'))->not->toBe($id)->and($org->fresh()->settings['legal_hold'] ?? false)->toBeFalse();
});

// ── IF-18: staff read with staff keys ───────────────────────────────────────────────────────────────────────────

it('gives the staff roles staff keys for tickets, backups and billing next to the customer keys they still hold', function () {
    foreach (['staff.support.ticket.read', 'staff.backup.read', 'staff.billing.read'] as $key) {
        expect(PermissionCatalog::all()[$key]['audience'] ?? null)->toBe('staff', $key)->and(PermissionCatalog::risk($key))->toBe(PermissionCatalog::NORMAL);
    }
    $roles = RoleCatalog::all();
    foreach (['support_l1', 'support_l2', 'support_l3', 'support_manager'] as $role) {
        expect($roles[$role]['permissions'])->toContain('staff.support.ticket.read', 'support.ticket.read'); // expand only: P0-15 removes the customer key after a shadow log
    }
    foreach (['billing_finance_admin', 'billing_operator'] as $role) {
        expect($roles[$role]['permissions'])->toContain('staff.billing.read', 'billing.invoice.read');
    }
    expect($roles['backup_dr_admin']['permissions'])->toContain('staff.backup.read', 'backup.read');
    foreach (RoleCatalog::all() as $key => $role) {
        if (! $role['staff']) {
            expect(array_filter($role['permissions'], fn ($p) => str_starts_with($p, 'staff.')))->toBe([], "customer role {$key} holds no staff key");
        }
    }
});

it('reads the staff ticket queue and the withdrawals list with the staff keys, not with the customer keys', function () {
    // a global role that holds only the customer keys — what a staff role will look like to these pages once they stop
    // accepting customer keys — no longer opens them
    DB::table('roles')->insert(['key' => 'rft_legacy', 'name' => 'Legacy', 'description' => 'customer keys only', 'scope_type' => 'global', 'is_staff' => true, 'assignable' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('role_permissions')->insert([['role_key' => 'rft_legacy', 'permission_key' => 'support.ticket.read'], ['role_key' => 'rft_legacy', 'permission_key' => 'billing.invoice.read']]);
    $legacy = rftUser('rft_legacy', 'rft-legacy@onhost.test');
    $this->actingAs($legacy, 'sanctum')->getJson('/v1/staff/tickets')->assertForbidden();
    $this->getJson('/v1/staff/withdrawals')->assertForbidden();

    $this->actingAs(rftUser('support_l1', 'rft-l1@onhost.test'), 'sanctum')->getJson('/v1/staff/tickets')->assertOk();
    $this->actingAs(rftUser('billing_operator', 'rft-billing@onhost.test'), 'sanctum')->getJson('/v1/staff/withdrawals')->assertOk();
});

it('architecture: every permission a staff controller asks for is a staff permission', function () {
    $customerKeys = [];
    foreach (glob(app_path('Http/Controllers/Api/V1/Staff/*.php')) ?: [] as $file) {
        preg_match_all('/->(?:authorize|authorizeAction|can)\(\$request,\s*\'([a-z0-9_.]+)\'/', (string) file_get_contents($file), $m);
        foreach ($m[1] as $key) {
            expect(PermissionCatalog::exists($key))->toBeTrue(basename($file).": {$key}");
            if ((PermissionCatalog::all()[$key]['audience'] ?? null) !== 'staff') {
                $customerKeys[] = basename($file).": {$key}";
            }
        }
    }
    expect($customerKeys)->toBe([]);
});

// ── P0-11: one role → permission resolver, a seeder that never empties a role ────────────────────────────────────

it('resolves a catalogue role to its permissions and an unknown role to nothing to grant and everything to take away', function () {
    expect(RoleResolver::exists('viewer'))->toBeTrue()->and(RoleResolver::exists('no_such_role'))->toBeFalse()
        ->and(RoleResolver::grantable('viewer'))->toBe(RoleCatalog::all()['viewer']['permissions'])
        ->and(RoleResolver::grantable('no_such_role'))->toBe([])
        ->and(RoleResolver::coveredForRevoke('viewer'))->toBe(RoleCatalog::all()['viewer']['permissions'])
        ->and(RoleResolver::coveredForRevoke('no_such_role'))->toBe(PermissionCatalog::keys());
});

it('architecture: role definitions are read through RoleResolver, not straight from the catalogue, outside the files that still do', function () {
    // the files that read RoleCatalog::all() when TASK-0037 introduced RoleResolver; the list only shrinks (program D6, P0-11)
    $allowed = [
        'app/Console/Commands/Doctor.php', 'app/Console/Commands/StaffCreate.php', 'database/seeders/AuthorizationSeeder.php',
        'domains/Identity/Authorization/RoleCatalog.php', 'domains/Identity/Authorization/RoleResolver.php',
        'domains/Organizations/Commands/OrganizationsCommandHandler.php', 'domains/Organizations/OrganizationService.php', 'domains/Organizations/ProjectService.php',
        'domains/Services/Access/ServiceAccessService.php', 'domains/Services/Listeners/RevokeDelegatedAccess.php',
    ];
    $readers = [];
    foreach (['app', 'domains', 'platform', 'providers', 'database/seeders'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' && str_contains((string) file_get_contents($file->getPathname()), 'RoleCatalog::all()')) {
                $readers[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }
    }
    expect(array_values(array_diff($readers, $allowed)))->toBe([]);
});

it('syncs the catalogue roles without ever leaving a role with no permissions, and removes what the catalogue dropped', function () {
    DB::table('role_permissions')->where('role_key', 'viewer')->where('permission_key', 'audit.read')->delete();
    DB::table('role_permissions')->insert(['role_key' => 'viewer', 'permission_key' => 'service.manage']); // drift: a permission the catalogue never gave

    $wholeRoleDeletes = [];
    DB::listen(function ($query) use (&$wholeRoleDeletes) {
        $sql = strtolower($query->sql);
        if (str_starts_with($sql, 'delete') && str_contains($sql, 'role_permissions') && ! str_contains($sql, 'permission_key')) {
            $wholeRoleDeletes[] = $query->sql;
        }
    });
    (new AuthorizationSeeder)->run();

    expect($wholeRoleDeletes)->toBe([]);
    foreach (RoleCatalog::all() as $key => $role) {
        $stored = DB::table('role_permissions')->where('role_key', $key)->pluck('permission_key')->sort()->values()->all();
        $expected = $role['permissions'];
        sort($expected);
        expect($stored)->toBe($expected, $key);
    }
});

// ── the dry-run before the floor goes live: which tokens used an operation that now needs a step-up ────────────────

it('lists, read-only, the API tokens that ran an operation below its catalogue risk in the last 30 days', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $audit = app(AuditRecorder::class);
    $record = fn (string $session, string $action, string $permission, ?string $stepUp = null) => $audit->record(
        new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', $session, stepUpMethod: $stepUp), $action, 'succeeded', ['permission' => $permission]);

    $this->travel(-40)->days();
    $record('token:9001', 'domain.contact', 'domain.registrant.change');   // too old
    $this->travelBack();
    $record('token:9002', 'domain.publish_ds', 'dns.dnssec.manage');        // a token, below the floor: listed
    $record('token:9002', 'domain.publish_ds', 'dns.dnssec.manage');
    $record('token:9003', 'domain.auto_renew', 'domain.manage');             // an ordinary permission: not listed
    $record('portal-session', 'domain.publish_ds', 'dns.dnssec.manage');     // a person in the portal: counted, will see the step-up dialog
    $record('portal-session', 'domain.contact', 'domain.registrant.change', 'totp'); // already stepped up: nothing changes for it
    $before = [AuditEvent::query()->count(), OutboxMessage::query()->count(), Approval::query()->count()];

    $this->artisan('onhost:iam:risk-floor-report')
        ->expectsOutputToContain('token:9002')
        ->doesntExpectOutputToContain('token:9001')
        ->doesntExpectOutputToContain('token:9003')
        ->expectsOutputToContain('domain.publish_ds')
        ->assertSuccessful();

    expect([AuditEvent::query()->count(), OutboxMessage::query()->count(), Approval::query()->count()])->toBe($before); // it writes nothing
});

// ── review round 1 (TASK-0037) ────────────────────────────────────────────────────────────────────────────────────

it('spends a due time lock once: a repeat that read it as due too, but lost the write, runs nothing (qa HIGH, IF-10)', function () {
    // releaseTimeLock is what stands in for the second person when one operator runs the platform: two repeats that both
    // read the lock as due must not both run the action. The harness puts the other request's write between our read and
    // our update — exactly the window a plain read-then-save would lose.
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $solo = rftUser('platform_owner', 'rft-race@onhost.test');
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $this->actingAs($solo, 'sanctum');
    $id = (string) $this->postJson("/v1/staff/customers/{$org->id}/legal-hold", ['hold' => true, 'reason' => 'Souběh dvou opakování'])->assertForbidden()->json('approval_id');
    $row = Approval::query()->findOrFail($id);
    $this->travel(24 * 60 + 1)->minutes();

    $raced = false;
    DB::listen(function ($query) use (&$raced, $id) {
        if (! $raced && str_starts_with(strtolower(ltrim($query->sql)), 'select') && str_contains($query->sql, 'approvals')) {
            $raced = true; // the other repeat wins between our read and our write
            DB::table('approvals')->where('id', $id)->where('state', 'pending')->update(['state' => 'consumed', 'consumed_at' => now()]);
        }
    });
    expect(ApprovalService::releaseTimeLock($row->action, $row->payload_hash, $solo->id))->toBeNull()->and($raced)->toBeTrue();
});

it('runs a time-locked action once when it is repeated twice back to back after the delay (qa HIGH, IF-10)', function () {
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $solo = rftUser('platform_owner', 'rft-twice@onhost.test');
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $url = "/v1/staff/customers/{$org->id}/legal-hold";
    $body = ['hold' => true, 'reason' => 'Dvojí opakování po lhůtě'];
    $this->actingAs($solo, 'sanctum');
    $id = (string) $this->postJson($url, $body)->assertForbidden()->json('approval_id');
    $row = Approval::query()->findOrFail($id);
    $this->travel(24 * 60 + 1)->minutes();
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');

    // the lock itself: the first release spends it, the second finds nothing to spend
    expect(ApprovalService::releaseTimeLock($row->action, $row->payload_hash, $solo->id))->toBe($id)
        ->and(ApprovalService::releaseTimeLock($row->action, $row->payload_hash, $solo->id))->toBeNull();

    // and through the endpoint: with the lock spent, neither repeat runs; with a fresh lock due, only the first does
    $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required');
    $this->travel(24 * 60 + 1)->minutes();
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $this->postJson($url, $body)->assertOk();
    $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect(AuditEvent::query()->where('action', 'compliance.legal_hold')->where('result', 'succeeded')->count())->toBe(1)
        ->and(Approval::query()->where('state', 'consumed')->count())->toBe(2)                 // the one spent by hand above and the one that ran
        ->and(Approval::query()->where('state', 'pending')->count())->toBe(1);                 // the second repeat only opened a new lock
});

it('turns a request left pending when the other approvers went into the sole approver\'s time lock, instead of leaving it stuck (qa MEDIUM)', function () {
    // opened while two people could decide: an ordinary request for a second person. The other approver leaves before deciding
    // it — nobody is left who may decide it, and it used to be handed back as it was on every repeat until it expired.
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $requester = rftUser('platform_owner', 'rft-left-alone@onhost.test');
    $other = rftUser('iam_admin', 'rft-leaves@onhost.test');
    app(StepUpService::class)->grant($requester, 'totp', null, '127.0.0.1');
    $url = "/v1/staff/customers/{$org->id}/legal-hold";
    $body = ['hold' => true, 'reason' => 'Druhý schvalovatel odešel'];
    $this->actingAs($requester, 'sanctum');
    $ordinary = (string) $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(data_get(Approval::query()->findOrFail($ordinary)->payload, 'time_lock'))->toBeNull();

    PolicyBinding::query()->where('principal_id', $other->id)->delete();
    app(Authorizer::class)->flush();

    $locked = (string) $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect($locked)->not->toBe($ordinary)
        ->and(data_get(Approval::query()->findOrFail($locked)->payload, 'time_lock.hours'))->toBe(24);

    $this->travel(24 * 60 + 1)->minutes();
    app(StepUpService::class)->grant($requester, 'totp', null, '127.0.0.1');
    $this->postJson($url, $body)->assertOk()->assertJsonPath('legal_hold', true);
    expect(Approval::query()->findOrFail($locked)->state)->toBe('consumed');
});

it('keeps the staff ticket queue and the withdrawals list closed to an API token of a member of staff (qa MEDIUM, IF-18)', function () {
    [, $org] = $this->customerWithOrganization();
    foreach (['support_l1' => '/v1/staff/tickets', 'billing_operator' => '/v1/staff/withdrawals'] as $role => $url) {
        $member = rftUser($role, "rft-token-{$role}@onhost.test");
        $token = $member->createToken('rft-staff', TokenScopes::ALL);
        $token->accessToken->forceFill(['organization_id' => $org->id])->save();
        app('auth')->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson($url)->assertForbidden();
        $this->flushHeaders();
        app('auth')->forgetGuards();
        $this->actingAs($member, 'sanctum')->getJson($url)->assertOk(); // the same person in the console reads it
        app('auth')->forgetGuards();
    }
});

it('runs every critical operation of the sole approver through the time lock — whatever is made critical later inherits it (security MEDIUM, P0-12 → IF-9)', function () {
    // P0-12's criterion "the solo approver's own force-purge is time-locked with cancel" is met by the mechanism, not by the
    // force purge itself: the waiver path of IdentityCommandAuthorizer takes every operation that ends up CRITICAL. Swept over
    // every risk-aware operation, so the day IF-9/P0-08 makes a forced purge CRITICAL (staff.service.delete) it is covered.
    config(['onhost.identity.four_eyes' => false]);
    $solo = rftUser('platform_owner', 'rft-solo-sweep@onhost.test');
    app(StepUpService::class)->grant($solo, 'totp', 'rft-session', '127.0.0.1');
    expect(ApprovalService::waivesFor($solo->id))->toBeTrue();
    $authorizer = app(Authorizer::class);

    $critical = 0;
    $ranAtOnce = [];
    foreach (rftRiskAwareClasses() as $class) {
        foreach (rftPayloadsOf($class) as $i => $payload) {
            $command = is_subclass_of($class, GlobalCommand::class) ? new $class("rft-s{$i}", $payload) : new $class('org_rft_sweep', "rft-s{$i}", $payload);
            $permission = $command->permission();
            if ($permission === null || ! $authorizer->can($solo, $permission, $command->scope())) {
                continue;
            }
            $risk = PermissionCatalog::effectiveRisk($permission, $command->riskLevel(), $command->name());
            if ($risk !== PermissionCatalog::CRITICAL && ! $command->requiresApproval()) {
                continue;
            }
            $critical++;
            if (rftDecide($command, rftContext($solo)) !== [false, 'approval']) {
                $ranAtOnce[] = $command->name();
            }
        }
    }
    expect($ranAtOnce)->toBe([])->and($critical)->toBeGreaterThan(0);
});

it('pins the open items P0-08/IF-9: staff global reach on a customer CRITICAL key and a forced purge still run at HIGH (security MEDIUM)', function () {
    // PermissionCatalog::floor lowers a customer CRITICAL key to HIGH whoever holds it — also a member of staff whose reach is
    // a GLOBAL binding (backup_dr_admin deleting any organization's backups, platform_owner filing another organization's
    // erasure), and a forced purge is still `service.delete` HIGH. Telling the customer's reach from the staff's is the
    // mode-aware Authorizer of P0-08; the forced purge becomes CRITICAL `staff.service.delete` in IF-9. When either lands this
    // test fails on purpose: turn it into the proof that staff reach is CRITICAL and that the forced purge waits the time lock.
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $solo = rftUser('platform_owner', 'rft-open-items@onhost.test');
    $backupAdmin = rftUser('backup_dr_admin', 'rft-backup-admin@onhost.test');
    app(StepUpService::class)->grant($solo, 'totp', 'rft-session', '127.0.0.1');
    app(StepUpService::class)->grant($backupAdmin, 'totp', 'rft-session', '127.0.0.1');
    $deleteBackup = new ServiceActionCommand($org->id, 'rft-open-bdel', ['action' => 'backup.delete', 'params' => []]);

    expect(PermissionCatalog::risk('backup.delete'))->toBe(PermissionCatalog::CRITICAL)->and(PermissionCatalog::floor('backup.delete'))->toBe(PermissionCatalog::HIGH)
        ->and(PermissionCatalog::risk('service.delete'))->toBe(PermissionCatalog::HIGH);
    expect(rftDecide($deleteBackup, rftContext($backupAdmin)))->toBe([true, null])
        ->and(rftDecide($deleteBackup, rftContext($solo)))->toBe([true, null])
        ->and(rftDecide(new DataRequestCommand($org->id, 'rft-open-erase', ['op' => 'request', 'kind' => 'deletion']), rftContext($solo)))->toBe([true, null])
        ->and(rftDecide(new ServiceActionCommand($org->id, 'rft-open-purge', ['action' => 'purge', 'params' => ['force' => true, 'reason' => 'podvodná objednávka']]), rftContext($solo)))->toBe([true, null]);
});
