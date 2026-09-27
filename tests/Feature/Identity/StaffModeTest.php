<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\JitElevation;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Commands\ServicesCommandHandler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/*
 * TASK-0039 — permission program P0-08 (IF-4, IF-8, IF-9): staff act as staff, and only as staff.
 *
 *  IF-4  A global binding (every staff role) and a JIT elevation covered every scope and every permission: a support or backup
 *        role reached a customer's services through the CUSTOMER keys it happens to hold. Such an allow is "staff reach". This
 *        release only writes each one down (security_events `authz.staff_reach`); `onhost.staff_reach_enforced` (default off)
 *        refuses them once the shadow log has stayed empty for 7 days (program §6 P0-08/P0-15).
 *  IF-8  `is_staff` was a shortcut wherever it was read: a member of staff acting in an organization they belong to — on the
 *        customer's own routes — skipped the customer's parameter filter (a forced purge inside the restore window), lifted
 *        ONhost's own holds, walked past a panel under maintenance, paid a restore without the credit gate, and passed every
 *        console pre-flight. StaffActor answers "does this context act as staff" — only in staff mode, which /v1/staff/* sets.
 *  IF-9  A forced purge (and a skipped final archive) is CRITICAL `staff.service.delete`: a second person, or the sole
 *        approver's time lock; a forced purge never goes without the final archive.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake(); // what reaches the workflow is the subject, not the workflow
});

/** A member of staff with a global role who ALSO owns a customer organization (the setting of EXPL-1..3). @return array{0:User,1:Organization} */
function smtStaffOwner(string $role = 'platform_owner'): array
{
    $user = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    $organization = app(OrganizationService::class)->create($user, ['name' => 'Staff s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));

    return [$user, $organization];
}

/** The person on a customer route: what ApiContext builds outside /v1/staff (no staff mode). */
function smtCustomerRoute(User $user, Organization $organization): CommandContext
{
    return new CommandContext('user', $user->id, $organization->id, null, '127.0.0.1', 'pest', 'smt-session', stepUpMethod: 'totp');
}

/** The same person on /v1/staff/*: staff mode. */
function smtStaffRoute(User $user, Organization $organization): CommandContext
{
    return new CommandContext('user', $user->id, $organization->id, null, '127.0.0.1', 'pest', 'smt-session', stepUpMethod: 'totp', staffMode: true);
}

/** A web hosting switched off with its restore window running (a purge would be forced). */
function smtSuspended(Organization $organization, string $executor = 'aapanel'): Service
{
    $service = featureWebService($organization, $executor);
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now()->subDay(), 'suspended_reason' => 'zákazník zrušil', 'terminate_at' => now()->addDays(20)])->save();

    return $service->fresh();
}

/** A service action as the bus hands it to the handler (the bus itself is asked elsewhere). @param array<string,mixed> $params @return array<string,mixed> */
function smtAction(Service $service, string $action, array $params, CommandContext $context, string $key): array
{
    return app(ServicesCommandHandler::class)->handle(new ServiceActionCommand($service->organization_id, $key, ['service_id' => $service->id, 'action' => $action, 'params' => $params]), $context);
}

it('counts a staff role on a customer key only as staff reach: written down in the shadow release, refused once enforced (IF-4)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $at = CommandScope::resource($service->id, $org->id, null);
    $staff = $this->staff('platform_owner');
    $auth = app(Authorizer::class);

    // the shadow release: the answer is what it was, and each such allow is written down once per person, key, organization and day
    expect($auth->can($staff, 'service.read', $at))->toBeTrue()->and($auth->can($staff, 'service.read', $at))->toBeTrue();
    $auth->flush();
    expect($auth->can($staff, 'service.read', $at))->toBeTrue(); // a new unit of work on the same day adds nothing either
    $shadow = DB::table('security_events')->where('kind', 'authz.staff_reach')->get();
    expect($shadow)->toHaveCount(1)
        ->and($shadow[0]->user_id)->toBe($staff->id)->and($shadow[0]->organization_id)->toBe($org->id)
        ->and(json_decode((string) $shadow[0]->detail, true))->toMatchArray(['permission' => 'service.read', 'roles' => ['platform_owner'], 'scope_type' => 'resource', 'enforced' => false]);
    // a member's own binding, and a staff key held globally, are nothing to write down
    expect($auth->can($owner, 'service.read', $at))->toBeTrue()
        ->and($auth->can($staff, 'staff.customer.read', CommandScope::organization($org->id)))->toBeTrue()
        ->and(DB::table('security_events')->where('kind', 'authz.staff_reach')->count())->toBe(1);

    // a JIT elevation is the platform's reach too, whatever role it lends
    $l1 = $this->staff('support_l1');
    JitElevation::query()->create(['user_id' => $l1->id, 'role_key' => 'org_admin', 'scope_type' => 'organization', 'scope_id' => $org->id, 'reason' => 'incident', 'ttl_minutes' => 60, 'state' => 'approved', 'approver_id' => $staff->id, 'approved_at' => now(), 'expires_at' => now()->addHour()]);
    expect($auth->can($l1, 'service.manage', $at))->toBeTrue()
        ->and(DB::table('security_events')->where('kind', 'authz.staff_reach')->where('user_id', $l1->id)->count())->toBe(1);

    // enforced: the staff role and the elevation no longer reach the customer key; staff keys stay; members are not touched
    config(['onhost.staff_reach_enforced' => true]);
    $auth->flush();
    expect($auth->can($staff, 'service.read', $at))->toBeFalse()
        ->and($auth->can($l1, 'service.manage', $at))->toBeFalse()
        ->and($auth->can($staff, 'staff.customer.read', CommandScope::organization($org->id)))->toBeTrue()
        ->and($auth->can($owner, 'service.read', $at))->toBeTrue()
        ->and($auth->permissionsAt($staff, $at))->not->toContain('service.read')->toContain('staff.console');
    // a member of staff who is ALSO a member acts on the membership, never on the staff role
    [$staffOwner, $own] = smtStaffOwner();
    expect($auth->can($staffOwner, 'service.read', CommandScope::organization($own->id)))->toBeTrue()
        ->and($auth->can($staffOwner, 'service.read', $at))->toBeFalse();
    expect(DB::table('security_events')->where('kind', 'authz.staff_reach')->count())->toBe(2); // a refusal is not an allow: nothing new in the shadow log
    // the operator's report lists what still leans on customer keys: enforcement is not due
    expect(Artisan::call('operator:authz:staff-reach', ['--days' => 7]))->toBe(0)
        ->and(Artisan::output())->toContain('service.read')->toContain('platform_owner')->toContain('before ONHOST_STAFF_REACH_ENFORCED=true');
});

it('treats a member of staff on a customer route as the customer they are there: no forced purge, no hold lifted, no maintenance pass (IF-8, EXPL-1..3)', function () {
    [$staff, $org] = smtStaffOwner();
    $asCustomer = smtCustomerRoute($staff, $org);

    // EXPL-1: a forced purge inside the restore window of a service in their own organization
    $service = smtSuspended($org);
    expect(fn () => smtAction($service, 'purge', ['force' => true, 'reason' => 'úklid ve vlastní organizaci'], $asCustomer, 'smt-purge'))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('grace_period_active'));

    // EXPL-2: lifting ONhost's quarantine of their own service
    $held = app(ServiceService::class)->imposeHold($service, SuspensionHold::ABUSE, 'abuse:AB-2026-0042', CommandContext::system('abuse')->withScope($org->id));
    expect(fn () => smtAction($held, 'resume', ['reason' => 'odblokuji si to sám'], $asCustomer, 'smt-lift'))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('service_suspension_held'));
    expect(SuspensionHold::holds($held->fresh()))->toBe(['abuse']);
    // …and a suspension they make there is their own pause, not ONhost's hold
    expect(SuspensionHold::kindFor($asCustomer, 'pauza přes víkend'))->toBeNull();

    // EXPL-3: past a panel under maintenance
    $live = featureWebService($org, 'ispconfig');
    ProviderInstance::query()->whereKey($live->provider_instance_id)->update(['state' => 'maintenance', 'maintenance_until' => now()->addHour(), 'state_reason' => 'upgrade']);
    expect(fn () => app(ServiceService::class)->requestAction($live->fresh(), 'backup', $asCustomer, 'smt-maintenance'))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('control_plane_maintenance'));

    expect(Operation::query()->where('organization_id', $org->id)->count())->toBe(0);
});

it('lets staff force, lift and pass maintenance in staff mode, on the record (IF-8)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $staff = $this->staff('platform_owner');
    $asStaff = smtStaffRoute($staff, $org);

    $service = smtSuspended($org);
    $purge = smtAction($service, 'purge', ['force' => true, 'reason' => 'soudní příkaz k odstranění'], $asStaff, 'smt-staff-purge');
    expect((array) Operation::query()->findOrFail($purge['operation_id'])->desired)->toMatchArray(['action' => 'purge', 'force' => true]);
    // the final archive is not skipped on a forced purge: it is taken first, whatever the request says
    Operation::query()->whereKey($purge['operation_id'])->update(['state' => Operation::FAILED, 'finished_at' => now()]);
    expect(fn () => smtAction($service->fresh(), 'purge', ['force' => true, 'reason' => 'soudní příkaz', 'archive_before_delete' => false], $asStaff, 'smt-staff-purge-2'))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('final_archive_required')->and($e->status)->toBe(422));

    $held = app(ServiceService::class)->imposeHold(smtSuspended($org, 'ispconfig'), SuspensionHold::ABUSE, 'abuse:AB-2026-0043', CommandContext::system('abuse')->withScope($org->id));
    expect(fn () => smtAction($held, 'resume', [], $asStaff, 'smt-staff-lift-0'))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('reason_required'));
    expect(smtAction($held, 'resume', ['reason' => 'obsah odstraněn, případ uzavřen'], $asStaff, 'smt-staff-lift')['operation_id'])->toBeString();
    expect(SuspensionHold::kindFor($asStaff, 'ověřujeme identitu'))->toBe(SuspensionHold::REVIEW);

    // back in service, its panel under maintenance: the people doing the maintenance are not locked out
    Operation::query()->where('service_id', $held->id)->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    $held->fresh()->forceFill(['state' => ServiceStateMachine::ACTIVE, 'suspended_at' => null, 'terminate_at' => null])->save();
    ProviderInstance::query()->whereKey($held->provider_instance_id)->update(['state' => 'maintenance', 'maintenance_until' => now()->addHour()]);
    expect(app(ServiceService::class)->requestAction($held->fresh(), 'backup', $asStaff, 'smt-staff-maintenance'))->toBeInstanceOf(Operation::class);

    // staff mode is a member of staff's: a customer who got a staff-mode context by mistake is still a customer
    expect(SuspensionHold::kindFor(smtStaffRoute($owner, $org), 'pauza'))->toBeNull();
});

it('sets staff mode on /v1/staff only: the hold is lifted there, the same person on the customer route is refused (IF-8)', function () {
    [$staff, $org] = smtStaffOwner();
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $held = app(ServiceService::class)->imposeHold(smtSuspended($org), SuspensionHold::ABUSE, 'abuse:AB-2026-0044', CommandContext::system('abuse')->withScope($org->id));
    $this->actingAs($staff, 'sanctum');

    $this->withHeader('Idempotency-Key', 'smt-http-1')->postJson("/v1/services/{$held->id}/actions", ['action' => 'resume', 'reason' => 'odblokuji si to sám'])
        ->assertStatus(409)->assertJsonPath('error', 'service_suspension_held');
    $this->flushHeaders();
    // P0-16 re-check: in an organization of their own, staff mode takes a second person (the operator's own quarantine is not
    // theirs alone to lift); it was a 202 here
    $approval = (string) $this->withHeader('Idempotency-Key', 'smt-http-2')->postJson("/v1/staff/services/{$held->id}/actions", ['action' => 'resume', 'reason' => 'obsah odstraněn, případ uzavřen'])
        ->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(SuspensionHold::holds($held->fresh()))->toBe(['abuse']);
    $this->flushHeaders();
    $operationId = $this->withHeader('Idempotency-Key', 'smt-http-3')->postJson("/v1/staff/services/{$held->id}/actions", ['action' => 'resume', 'reason' => 'obsah odstraněn, případ uzavřen', 'approval_ids' => [secondPersonApproves($approval)]])
        ->assertStatus(202)->json('operation_id');
    expect(SuspensionHold::holds($held->fresh()))->toBe([]);
    // the record says in which mode: the lift in the audit, the run in its operation
    $lift = DB::table('audit_events')->where('action', 'service.hold.lift')->where('resource_id', $held->id)->first();
    expect($lift)->not->toBeNull()->and(json_decode((string) $lift->detail, true)['staff_mode'] ?? null)->toBeTrue()
        ->and((array) Operation::query()->findOrFail($operationId)->desired)->toMatchArray(['action' => 'resume', 'staff_mode' => true]);
});

it('passes the console pre-flight for whom the token was issued and for a member — not for any member of staff (SS-14)', function () {
    Http::fake([PVE.'/nodes/prg1-n2/qemu/1042/vncproxy' => Http::response(['data' => ['port' => 5900, 'ticket' => 'PVEVNC:ticket', 'user' => 'onhost@pve!cp']])]);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = smtVps($org);
    $issuer = $this->staff('platform_owner');
    $bystander = $this->staff('support_l2');

    $this->actingAs($issuer, 'sanctum');
    $token = (string) $this->postJson("/v1/services/{$vps->id}/console-token")->assertOk()->json('token');
    expect(Cache::get("onhost:console:{$token}")['issued_to'] ?? null)->toBe($issuer->id);

    $this->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', true);
    $this->actingAs($bystander, 'sanctum')->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', false);
    $this->actingAs($owner, 'sanctum')->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', true);
});

/** A VPS on the Proxmox lab instance (console-capable). */
function smtVps(Organization $organization): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $organization->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-smt.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);

    return $service;
}

it('makes a forced purge and a skipped archive CRITICAL staff.service.delete, and refuses the flags to a customer instead of dropping them (IF-9)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = smtSuspended($org);
    $command = fn (string $action, array $params) => new ServiceActionCommand($org->id, 'smt-k', ['service_id' => $service->id, 'action' => $action, 'params' => $params]);

    expect($command('purge', ['force' => true, 'reason' => 'x'])->permission())->toBe('staff.service.delete')
        ->and($command('purge', ['force' => true, 'reason' => 'x'])->riskLevel())->toBe(PermissionCatalog::CRITICAL)
        ->and($command('terminate', ['archive_before_delete' => false])->permission())->toBe('staff.service.delete')
        ->and($command('terminate', ['archive_before_delete' => false])->riskLevel())->toBe(PermissionCatalog::CRITICAL)
        ->and($command('purge', ['reason' => 'x'])->permission())->toBe('service.delete')
        ->and($command('terminate', ['force' => false, 'archive_before_delete' => true])->permission())->toBe('service.delete');

    // the staff route: a second person first, then the purge with its final archive
    $staff = $this->steppedUpStaff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $approval = (string) $this->withHeader('Idempotency-Key', 'smt-sp-1')->postJson("/v1/staff/provisioning/services/{$service->id}/purge", ['reason' => 'soudní příkaz k okamžitému odstranění'])
        ->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse();
    $this->flushHeaders();
    $operationId = $this->withHeader('Idempotency-Key', 'smt-sp-2')->postJson("/v1/staff/provisioning/services/{$service->id}/purge", ['reason' => 'soudní příkaz k okamžitému odstranění', 'approval_ids' => [secondPersonApproves($approval)]])
        ->assertStatus(202)->json('operation_id');
    expect((array) Operation::query()->findOrFail($operationId)->desired)->toMatchArray(['action' => 'purge', 'force' => true])->not->toHaveKey('archive_before_delete');

    // a customer asking for the staff flags is refused before anything runs — the flags were silently dropped before
    $this->flushHeaders();
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $mine = smtSuspended($org, 'ispconfig');
    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', 'smt-cust-1')
        ->postJson("/v1/services/{$mine->id}/actions", ['action' => 'purge', 'params' => ['force' => true], 'reason' => 'chci to hned'])
        ->assertForbidden()->assertJsonPath('message', 'Missing permission staff.service.delete');
    expect(Operation::query()->where('service_id', $mine->id)->exists())->toBeFalse();
});

it('reads is_staff only where the allow-list says: every other decision asks StaffActor (IF-8, program objection 32)', function () {
    // file => how many reads of `is_staff` it may hold. What is here is a presentation, a sign-in rule (MFA for staff, the
    // staff surface), a staff-only tool or its creation — none decides what somebody may do to a customer's data. The list
    // only shrinks: a new read is either StaffActor or a reviewed line here.
    $allowed = [
        'app/Console/Commands/Doctor.php' => 1,                      // counts staff accounts
        'app/Console/Commands/ForensicLookback.php' => 2,            // describes the old shortcut it looks for (text)
        'app/Console/Commands/Forensics/MembershipHistory.php' => 1, // read-only look-back
        'app/Console/Commands/StaffTotp.php' => 1,                   // CLI for staff MFA
        'app/Http/Controllers/Api/V1/AuthController.php' => 5,       // staff MFA at sign-in, the surface a person lands on, registration creates a non-staff user
        'app/Http/Controllers/Api/V1/CheckoutController.php' => 1,   // a guest checkout creates a non-staff user
        'app/Http/Controllers/Api/V1/MeController.php' => 1,         // staff MFA requirement
        'app/Http/Controllers/Api/V1/NotificationController.php' => 1, // which inbox a person reads by default
        'app/Http/Controllers/Api/V1/Staff/ApprovalController.php' => 1, // only staff decide approvals (staff route)
        'app/Http/Controllers/Web/SurfaceController.php' => 2,       // which surface a person sees
        'app/Http/Controllers/Web/SystemSettingsController.php' => 6, // staff-only settings pages
        'app/Http/Presenters/Presenters.php' => 2,                   // shows the flag
        'domains/Compliance/ComplianceService.php' => 1,             // an erasure keeps a staff account
        'domains/Identity/Authorization/ApprovalService.php' => 1,   // approvers are staff
        'domains/Identity/Authorization/Models/Role.php' => 1,       // the roles table's own column
        'domains/Identity/Authorization/StaffActor.php' => 1,        // the one decision
        'domains/Identity/Commands/ApprovalDecisionCommandHandler.php' => 1, // only staff decide approvals
        'domains/Identity/Commands/StaffAccountCommandHandler.php' => 1, // creates a staff account (the CLI through the bus since TASK-0037)
        'domains/Identity/Models/User.php' => 1,                     // the column's cast
        'domains/Identity/StepUp/StepUpService.php' => 1,            // staff step up with a second factor
        'domains/Incidents/OnCallRota.php' => 3,                     // only staff go on call
        'routes/console.php' => 1,                                   // the first staff account for a CLI default
    ];
    $found = [];
    foreach (['app', 'domains', 'platform', 'providers', 'routes'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $count = 0; // code only: a comment that names the old shortcut decides nothing
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $count += substr_count($token[1], 'is_staff');
                }
            }
            if ($count > 0) {
                $found[str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1))] = $count;
            }
        }
    }
    ksort($found);
    ksort($allowed);
    expect($found)->toBe($allowed);
});

it('writes one shadow entry per person, key, organization and day', function () {
    [, $org] = $this->customerWithOrganization();
    $staff = $this->staff('backup_dr_admin');
    $auth = app(Authorizer::class);
    expect($auth->can($staff, 'backup.delete', CommandScope::organization($org->id)))->toBeTrue();
    $this->travel(1)->days();
    $auth->flush();
    expect($auth->can($staff, 'backup.delete', CommandScope::organization($org->id)))->toBeTrue()
        ->and($auth->can($staff, 'backup.delete', CommandScope::organization($org->id)))->toBeTrue();
    expect(DB::table('security_events')->where('kind', 'authz.staff_reach')->where('user_id', $staff->id)->count())->toBe(2);
});

// ── TASK-0039 review round 2 ──
it('keeps the shadow entry when the work that asked is rolled back, and marks the day logged only once it is written (review round 2)', function () {
    [, $org] = $this->customerWithOrganization();
    $staff = $this->staff('backup_dr_admin');
    $auth = app(Authorizer::class);
    $at = CommandScope::organization($org->id);
    $shadowRows = fn () => DB::table('security_events')->where('kind', 'authz.staff_reach')->where('user_id', $staff->id)->count();

    // the bus or a handler asks inside its transaction, and a later refusal rolls the transaction back: the allow still happened
    expect(fn () => DB::transaction(function () use ($auth, $staff, $at) {
        expect($auth->can($staff, 'backup.delete', $at))->toBeTrue();
        throw DomainError::conflict('smt_refused_later', 'The handler refused after the Authorizer allowed.');
    }))->toThrow(DomainError::class);
    expect($shadowRows())->toBe(1);

    // a nested unit rolled back while the outer one goes on: written once the outer one ends, whichever way
    $this->travel(1)->days();
    $auth->flush();
    expect(fn () => DB::transaction(function () use ($auth, $staff, $at) {
        try {
            DB::transaction(function () use ($auth, $staff, $at) {
                expect($auth->can($staff, 'backup.delete', $at))->toBeTrue();
                throw DomainError::conflict('smt_inner_refused', 'The inner step refused.');
            });
        } catch (DomainError) {
            // the outer unit carries on and fails later
        }
        throw DomainError::conflict('smt_outer_refused', 'The outer unit refused as well.');
    }))->toThrow(DomainError::class);
    expect($shadowRows())->toBe(2);

    // … and the day is marked in the cache only by a written row: nothing more is written the same day
    $auth->flush();
    expect($auth->can($staff, 'backup.delete', $at))->toBeTrue()->and($shadowRows())->toBe(2);

    // a unit that commits writes it too, once it has committed
    $this->travel(1)->days();
    $auth->flush();
    DB::transaction(fn () => expect($auth->can($staff, 'backup.delete', $at))->toBeTrue());
    expect($shadowRows())->toBe(3);
});
// ── end TASK-0039 review round 2 ──

// ── TASK-0039 P0-16 re-check (staff mode asks staff keys) ──
/*
 * The re-check of the final Phase-0 chain: /v1/staff/services/{id}/actions and …/reinstate reused the customer controllers and
 * the customer keys (`service.manage`, `billing.wallet.topup`), and StaffActor asked only for staff mode, `is_staff` and an
 * active account. So any staff account — an auditor, an IAM admin — who is a member or a share guest of a service reached
 * the staff powers there through that membership: EXPL-1..3 and SS-1 had moved to another URL. Staff mode now asks staff keys
 * (`staff.service.manage`, the staff billing key `billing.dunning.manage`), StaffActor::may asks the key of a staff role, and a
 * member of staff acting in staff mode in an organization of their own takes a second person or the time lock.
 */

it('refuses the staff route to a member of staff without the staff key, however they reach the service (P0-16 re-check)', function () {
    // an auditor who owns an organization: the ownership is no staff power
    [$auditor, $own] = smtStaffOwner('auditor_read_only');
    app(StepUpService::class)->grant($auditor, 'totp', null, '127.0.0.1');
    $held = app(ServiceService::class)->imposeHold(smtSuspended($own), SuspensionHold::ABUSE, 'abuse:AB-2026-0051', CommandContext::system('abuse')->withScope($own->id));
    $this->actingAs($auditor, 'sanctum');
    $this->withHeader('Idempotency-Key', 'smt-p16-1')->postJson("/v1/staff/services/{$held->id}/actions", ['action' => 'resume', 'reason' => 'odblokuji si to sám'])
        ->assertForbidden()->assertJsonPath('message', 'Missing permission staff.service.manage');
    $this->flushHeaders();
    $this->withHeader('Idempotency-Key', 'smt-p16-2')->postJson("/v1/staff/services/{$held->id}/reinstate")
        ->assertForbidden()->assertJsonPath('message', 'Missing permission billing.dunning.manage');
    expect(SuspensionHold::holds($held->fresh()))->toBe(['abuse']);

    // an IAM admin who was given one service to manage (a share, BASIS_MEMBER): the share is no staff power either
    [, $org] = $this->customerWithOrganization();
    $guest = $this->steppedUpStaff('iam_admin');
    $shared = app(ServiceService::class)->imposeHold(smtSuspended($org, 'ispconfig'), SuspensionHold::ABUSE, 'abuse:AB-2026-0052', CommandContext::system('abuse')->withScope($org->id));
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'svc_manage', 'scope_type' => 'resource', 'scope_id' => $shared->id, 'organization_id' => $org->id]);
    $this->flushHeaders();
    $this->actingAs($guest, 'sanctum')->withHeader('Idempotency-Key', 'smt-p16-3')->postJson("/v1/staff/services/{$shared->id}/actions", ['action' => 'resume', 'reason' => 'host to potřebuje'])
        ->assertForbidden()->assertJsonPath('message', 'Missing permission staff.service.manage');
    expect(SuspensionHold::holds($shared->fresh()))->toBe(['abuse'])
        ->and(Operation::query()->whereIn('service_id', [$held->id, $shared->id])->count())->toBe(0);
});

it('lets a staff role with the staff key act on the staff route, and its run asks that key again (P0-16 re-check)', function () {
    [, $org] = $this->customerWithOrganization();
    $l2 = $this->steppedUpStaff('support_l2'); // staff.service.manage, and no customer key at all
    $held = app(ServiceService::class)->imposeHold(smtSuspended($org), SuspensionHold::ABUSE, 'abuse:AB-2026-0053', CommandContext::system('abuse')->withScope($org->id));
    $this->actingAs($l2, 'sanctum');

    $operationId = $this->withHeader('Idempotency-Key', 'smt-p16-4')->postJson("/v1/staff/services/{$held->id}/actions", ['action' => 'resume', 'reason' => 'obsah odstraněn, případ uzavřen'])
        ->assertStatus(202)->json('operation_id');
    expect(SuspensionHold::holds($held->fresh()))->toBe([])
        ->and(Operation::query()->findOrFail($operationId)->authorized_permission)->toBe('staff.service.manage');
});

it('takes a second person in any organization of their own, a share included; a stranger organization takes none (P0-16 re-check)', function () {
    [, $org] = $this->customerWithOrganization();
    $l2 = $this->steppedUpStaff('support_l2');
    $mine = app(ServiceService::class)->imposeHold(smtSuspended($org), SuspensionHold::ABUSE, 'abuse:AB-2026-0054', CommandContext::system('abuse')->withScope($org->id));
    $theirs = app(ServiceService::class)->imposeHold(smtSuspended($org, 'ispconfig'), SuspensionHold::ABUSE, 'abuse:AB-2026-0055', CommandContext::system('abuse')->withScope($org->id));
    // a share of ONE service makes the organization theirs too: its other services are not a stranger's
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $l2->id, 'role_key' => 'svc_view', 'scope_type' => 'resource', 'scope_id' => $mine->id, 'organization_id' => $org->id]);
    $this->actingAs($l2, 'sanctum');

    foreach ([$mine, $theirs] as $i => $service) {
        $this->flushHeaders();
        $this->withHeader('Idempotency-Key', "smt-p16-own-{$i}")->postJson("/v1/staff/services/{$service->id}/actions", ['action' => 'resume', 'reason' => 'obsah odstraněn, případ uzavřen'])
            ->assertForbidden()->assertJsonPath('error', 'approval_required');
        expect(SuspensionHold::holds($service->fresh()))->toBe(['abuse']);
    }
});
// ── end TASK-0039 P0-16 re-check ──
