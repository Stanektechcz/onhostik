<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Billing\Commands\ReinstateServiceCommand;
use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\TokenScopes;
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
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceArchiveService;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;

/*
 * TASK-0041 — the P0-16 red team on the stacked wave-two chain (c59e08a). Program keys P0-08 (IF-4, IF-8, IF-9), P0-09 (IF-5)
 * and P0-14 (IF-16) are NOT closed on this chain: their fix is TASK-0039, which is in neither wave 1 nor wave 2. Program §9's
 * carried-forward list named only EXPL-1..3 of them, so P0-16 ("each exploit closed by a named test or filed as open") failed
 * for PA-04, IF-4, SS-4/PA-06, SS-14, SE-3/SS-5 and the staff backup reach.
 *
 * Each test below pins one open hole exactly as it behaves today, the way RiskFloorTest "pins the open items P0-08/IF-9" does.
 * They FAIL ON PURPOSE once TASK-0039 is integrated (checked against its tip 17a4780): delete the pin then, and let the
 * TASK-0039 proof named in it stand in its place — and strike the item from the runbook's open list (the last test here).
 * Phase 0 is not signed off while one of them passes.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake(); // what the request lets through is the subject, not the workflow that would run it
});

/**
 * The items the P0-16 red team found open, with the program key(s) that close them. The runbook's open list must name each id
 * with its key(s) and the owning task, so nobody reads Phase 0 as closed from the runbook alone.
 *
 * @return array<string, list<string>>
 */
function pzoOpenItems(): array
{
    return [
        'PA-04' => ['P0-09', 'IF-5'],           // a token acts outside its own organization
        'IF-4' => ['P0-08', 'P0-15'],           // a staff role's customer keys reach every organization, unlogged
        'EXPL-1' => ['P0-08', 'IF-8'],          // forced purge through a customer route
        'EXPL-2' => ['P0-08', 'IF-8'],          // ONhost's hold lifted through a customer route
        'EXPL-3' => ['P0-08', 'IF-8'],          // past a panel under maintenance
        'SS-1' => ['P0-08', 'IF-8'],            // `is_staff` bypasses customer protections inside a membership
        'SS-14' => ['P0-08', 'IF-8'],           // `is_staff` counted as membership on the console pre-flight
        'SS-5' => ['P0-08', 'IF-9'],            // force-purge not CRITICAL
        'SE-3' => ['P0-08', 'IF-9'],            // purge without archive not CRITICAL
        'backup.delete' => ['P0-08', 'IF-9'],   // staff delete any organization's backups at HIGH, one person
        'archive.restore' => ['P0-08', 'IF-4'], // staff restore an archive over a live site through a global binding
        'SS-4' => ['P0-14', 'IF-16'],           // staff panel SSO without ticket, reason, family or notice
        'PA-06' => ['P0-14', 'IF-16'],
    ];
}

/** A person in two organizations — B joined FIRST, so "the first membership" is B — and a token made for A. @return array{0:User,1:Organization,2:Organization,3:string} */
function pzoTwoOrganizations(): array
{
    $user = User::factory()->create(['email' => 'pzo-dva-uctu@example.cz']);
    $b = app(OrganizationService::class)->create($user, ['name' => 'Beta s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $a = app(OrganizationService::class)->create($user, ['name' => 'Alfa s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $token = $user->createToken('pzo-alfa', TokenScopes::ALL);
    $token->accessToken->forceFill(['organization_id' => $a->id])->save();

    return [$user, $a, $b, $token->plainTextToken];
}

/** A member of staff with a global role who ALSO owns a customer organization (the setting of EXPL-1..3). @return array{0:User,1:Organization} */
function pzoStaffOwner(): array
{
    $user = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => 'platform_owner', 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    $organization = app(OrganizationService::class)->create($user, ['name' => 'Staff s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));

    return [$user, $organization];
}

/** The person on a customer route (/v1/services/…), as ApiContext builds it: no staff anything. */
function pzoCustomerRoute(User $user, Organization $organization): CommandContext
{
    return new CommandContext('user', $user->id, $organization->id, null, '127.0.0.1', 'pest', 'pzo-session', stepUpMethod: 'totp');
}

/** A web hosting switched off with its restore window running (a purge would have to be forced). */
function pzoSuspended(Organization $organization, string $executor = 'aapanel'): Service
{
    $service = featureWebService($organization, $executor);
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now()->subDay(), 'suspended_reason' => 'zákazník zrušil', 'terminate_at' => now()->addDays(20)])->save();

    return $service->fresh();
}

/** @param array<string,mixed> $params @return array<string,mixed> */
function pzoAction(Service $service, string $action, array $params, CommandContext $context, string $key): array
{
    return app(ServicesCommandHandler::class)->handle(new ServiceActionCommand($service->organization_id, $key, ['service_id' => $service->id, 'action' => $action, 'params' => $params]), $context);
}

/** The shadow log P0-08 adds for every allow that only a global binding gave (`authz.staff_reach`); it does not exist yet. */
function pzoShadowRows(): int
{
    return DB::table('security_events')->where('kind', 'authz.staff_reach')->count();
}

it('pins PA-04 (IF-5) open until P0-09/TASK-0039: a token made for organization A acts on B by header, without a header and by id', function () {
    // TASK-0039 proof: TokenPrincipalTest "refuses a token on another organization by header…" and "…the resources of another
    // organization by id…". Nothing reads personal_access_tokens.organization_id: ApiContext::organization() takes any
    // organization the token's PERSON belongs to, and without a header the person's oldest membership.
    [, $a, $b, $plain] = pzoTwoOrganizations();
    $mine = featureWebService($a, 'ispconfig');
    $theirs = featureWebService($b, 'aapanel');

    $byHeader = $this->withToken($plain)->withHeader('X-Organization', $b->id)->getJson('/v1/services')->assertOk()->json('data');
    expect(array_column($byHeader, 'id'))->toContain($theirs->id);
    app('auth')->forgetGuards();
    $this->flushHeaders();

    // no header: the person's first membership (B), not the organization the token was made for
    $noHeader = $this->withToken($plain)->getJson('/v1/services')->assertOk()->json('data');
    expect(array_column($noHeader, 'id'))->toContain($theirs->id)->not->toContain($mine->id);
    app('auth')->forgetGuards();

    // by id: B's service is read and a new run is queued on it with A's token
    $this->withToken($plain)->getJson("/v1/services/{$theirs->id}")->assertOk();
    app('auth')->forgetGuards();
    $this->withToken($plain)->withHeader('Idempotency-Key', 'pzo-pa04-act')->postJson("/v1/services/{$theirs->id}/actions", ['action' => 'backup'])->assertStatus(202);
    $this->flushHeaders();
    expect(Operation::query()->where('service_id', $theirs->id)->count())->toBe(1);
});

it('pins PA-04 open for staff tokens until P0-09/TASK-0039: a token lends the whole global reach of its person\'s staff role', function () {
    // TASK-0039 proof: TokenPrincipalTest "never lends a token the global reach of a staff role".
    [, $customerOrg] = $this->customerWithOrganization();
    $foreign = featureWebService($customerOrg, 'aapanel');
    [$staff, $own] = pzoStaffOwner();
    $token = $staff->createToken('pzo-staff', TokenScopes::ALL);
    $token->accessToken->forceFill(['organization_id' => $own->id])->save();

    $this->withToken($token->plainTextToken)->getJson("/v1/services/{$foreign->id}")->assertOk();
    app('auth')->forgetGuards();
    $listed = $this->withToken($token->plainTextToken)->withHeader('X-Organization', $customerOrg->id)->getJson('/v1/services')->assertOk()->json('data');
    $this->flushHeaders();
    expect(array_column($listed, 'id'))->toContain($foreign->id);
});

it('pins IF-4 open until P0-08 (and P0-15 after it): a staff role enters any organization by X-Organization and its customer keys apply, with nothing written down', function () {
    // TASK-0039 proof: StaffModeTest "counts a staff role on a customer key only as staff reach…" (the shadow row, then the
    // refusal once ONHOST_STAFF_REACH_ENFORCED is on); P0-15 then removes the customer keys from the staff roles.
    [$owner, $org] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum')->postJson('/v1/tickets', ['subject' => 'Nejde mi pošta na doméně', 'body' => 'Od rána nechodí žádné e-maily, prosím o kontrolu.'])->assertCreated();
    app('auth')->forgetGuards();

    // support_l1 holds `staff.customer.read` (enter any organization) and the CUSTOMER key `support.ticket.read`, globally
    $l1 = $this->staff('support_l1');
    $listed = $this->actingAs($l1, 'sanctum')->withHeader('X-Organization', $org->id)->getJson('/v1/tickets')->assertOk()->json('data');
    $this->flushHeaders();
    expect($listed)->toHaveCount(1);

    // backup_dr_admin: the customer keys backup.delete / backup.restore on every organization, by a global binding alone
    $backupAdmin = $this->staff('backup_dr_admin');
    $auth = app(Authorizer::class);
    expect($auth->can($backupAdmin, 'backup.delete', CommandScope::organization($org->id)))->toBeTrue()
        ->and($auth->can($backupAdmin, 'backup.restore', CommandScope::organization($org->id)))->toBeTrue()
        ->and($auth->can($l1, 'support.ticket.read', CommandScope::organization($org->id)))->toBeTrue();
    expect(pzoShadowRows())->toBe(0); // no consent, no ticket and no shadow log
});

it('pins EXPL-1/2/3 and SS-1 (IF-8) open until P0-08/TASK-0039: a member of staff on a customer route forces a purge, lifts ONhost\'s hold and passes maintenance', function () {
    // TASK-0039 proof: StaffModeTest "treats a member of staff on a customer route as the customer they are there…". The
    // shortcuts are `is_staff` reads: ServicesCommandHandler::isStaff (the parameter filter), ServiceService::liftHolds and the
    // maintenance pass — none asks whether the person acts as staff or as the member they also are.
    [$staff, $org] = pzoStaffOwner();
    $asCustomer = pzoCustomerRoute($staff, $org);

    // EXPL-1: `force` survives the customer's parameter filter — a purge inside the restore window of their own service
    $service = pzoSuspended($org);
    $purge = pzoAction($service, 'purge', ['force' => true, 'reason' => 'úklid ve vlastní organizaci'], $asCustomer, 'pzo-purge');
    expect((array) Operation::query()->findOrFail($purge['operation_id'])->desired)->toMatchArray(['action' => 'purge', 'force' => true]);

    // EXPL-2: every hold ONhost put on their own service is lifted with only a reason
    $held = app(ServiceService::class)->imposeHold(pzoSuspended($org, 'ispconfig'), SuspensionHold::ABUSE, 'abuse:AB-2026-0142', CommandContext::system('abuse')->withScope($org->id));
    expect(pzoAction($held, 'resume', ['reason' => 'odblokuji si to sám'], $asCustomer, 'pzo-lift')['operation_id'])->toBeString();
    expect(SuspensionHold::holds($held->fresh()))->toBe([]);

    // EXPL-3: past a panel under maintenance (the same site, back in service)
    Operation::query()->where('service_id', $held->id)->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    $held->fresh()->forceFill(['state' => ServiceStateMachine::ACTIVE, 'suspended_at' => null, 'terminate_at' => null])->save();
    $live = $held->fresh();
    ProviderInstance::query()->whereKey($live->provider_instance_id)->update(['state' => 'maintenance', 'maintenance_until' => now()->addHour(), 'state_reason' => 'upgrade']);
    expect(app(ServiceService::class)->requestAction($live->fresh(), 'backup', $asCustomer, 'pzo-maintenance'))->toBeInstanceOf(Operation::class);
});

it('pins the IF-8 credit gate open until P0-08/TASK-0039: any staff account, of any role, may reinstate for any organization', function () {
    // ServiceReinstatement::actorMay answers `is_staff || can(…)`: a staff account that holds no customer key and is no member
    // (marketing_content: public content only) passes the gate of every organization's restore.
    [, $org] = $this->customerWithOrganization();
    $content = $this->staff('marketing_content');
    $context = pzoCustomerRoute($content, $org);

    $may = fn (string $permission) => (fn () => $this->actorMay($context, $permission, $org->id))->call(app(ServiceReinstatement::class));
    expect($may(ReinstateServiceCommand::PERMISSION))->toBeTrue()->and($may('billing.wallet.read'))->toBeTrue();
});

it('pins SS-14 (IF-8) open until P0-08/TASK-0039: the console pre-flight lets any member of staff through', function () {
    // TASK-0039 proof: StaffModeTest "passes the console pre-flight for whom the token was issued and for a member — not for any
    // member of staff (SS-14)". ConsoleRelayController::check counts `is_staff` as membership.
    Http::fake([PVE.'/nodes/prg1-n2/qemu/1042/vncproxy' => Http::response(['data' => ['port' => 5900, 'ticket' => 'PVEVNC:ticket', 'user' => 'onhost@pve!cp']])]);
    [, $org] = $this->customerWithOrganization();
    $vps = pzoVps($org);
    $issuer = $this->staff('platform_owner');
    $bystander = $this->staff('support_l2');

    $this->actingAs($issuer, 'sanctum');
    $token = (string) $this->postJson("/v1/services/{$vps->id}/console-token")->assertOk()->json('token');
    expect(Cache::get("onhost:console:{$token}"))->toBeArray();

    $this->actingAs($bystander, 'sanctum')->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', true);
});

/** A VPS on the Proxmox lab instance (console-capable). */
function pzoVps(Organization $organization): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $organization->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-pzo.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);

    return $service;
}

it('pins SE-3/SS-5 and the staff archive restore open until P0-08/IF-9 (TASK-0039): a global backup role restores any organization\'s archive over a live site, unlogged', function () {
    // RiskFloorTest "pins the open items P0-08/IF-9" pins the other half (backup.delete floored to HIGH for staff, the forced
    // purge still `service.delete` HIGH). ServiceArchiveService::assertMayRestore asks the Authorizer, and a global binding
    // covers every organization's `backup.read` / `backup.restore`: one person overwrites a customer's live site from an
    // archive, with no second person, no membership and no shadow row. TASK-0039: StaffModeTest (the shadow row, then refused).
    [, $org] = $this->customerWithOrganization();
    $source = featureWebService($org, 'aapanel');
    $target = featureWebService($org, 'ispconfig');
    $archive = Backup::query()->create([
        'service_id' => $source->id, 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'verified_at' => now()->subDay(), 'verify_status' => 'ok', 'size_bytes' => 1024,
        'retention_until' => now()->addDays(60), 'immutable_until' => now()->addDays(60), 'meta' => ['set' => 'final/pzo', 'family' => 'web', 'parts' => ['service.json'], 'gaps' => []],
    ]);
    $backupAdmin = $this->staff('backup_dr_admin');
    app(StepUpService::class)->grant($backupAdmin, 'totp', 'pzo-session', '127.0.0.1');

    $check = fn () => (fn () => $this->assertMayRestore($archive, $target, pzoCustomerRoute($backupAdmin, $org)))->call(app(ServiceArchiveService::class));
    expect($check)->not->toThrow(Throwable::class);
    expect(pzoShadowRows())->toBe(0);
});

it('pins SS-4/PA-06 (IF-16) open until P0-14/TASK-0039: staff sign on to a customer\'s panel with no ticket, no reason and no notice', function () {
    // TASK-0039 proof: StaffPanelLoginTest (PanelLoginCommand: an open ticket about THIS service that a current member opened in
    // the portal or API — never `ticket_ref` from the request, never a ticket staff opened — a reason, the family, a second
    // person without the customer's consent, and the customer told at once). Today WebToolsController::panelLogin is a GET
    // that asks `staff.console` globally plus a step-up; support_l2/l3 hold `staff.console`, the reason is optional, and a
    // `ticket_ref` is whatever the request says (ApiContext::context).
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    Http::fake(function (Request $request) {
        $ok = fn ($response) => Http::response(['code' => 'ok', 'message' => '', 'response' => $response]);

        return match ((string) parse_url($request->url(), PHP_URL_QUERY)) {
            'login' => $ok('pzo-session'),
            'client_get' => $ok(['client_id' => 3, 'username' => 'client3']),
            'client_login_get' => $ok('https://isp.test:8080/login/?otp=PZO-ONE-TIME'),
            default => $ok([]),
        };
    });
    $l2 = $this->steppedUpStaff('support_l2');

    $this->actingAs($l2, 'sanctum')->getJson("/v1/staff/services/{$service->id}/panel-login?ticket_ref=TK-0000-NEEXISTUJE")->assertOk()->assertJsonPath('data.expires_in_seconds', 60);
    $audit = DB::table('audit_events')->where('action', 'staff.panel_login')->where('resource_id', $service->id)->sole();
    expect(json_decode((string) $audit->detail, true)['reason'] ?? null)->toBe('');
    expect(DB::table('notifications')->where('user_id', $owner->id)->count())->toBe(0); // nobody tells the customer
});

it('records every item the P0-16 red team found open in the breach register, with the program key that closes it and TASK-0039', function () {
    // The runbook opened by saying Phase 0 CLOSED PA-04 and SS-1/SS-5 with EXPL-1..3; on this chain it did not (TASK-0039 is not
    // integrated). The open list is where the operator and the next red team read what is still exploitable today.
    $runbook = (string) file_get_contents(base_path('docs/runbooks/breach-register.md'));
    expect(str_contains($runbook, '## Still open after Phase 0 wave 2'))->toBeTrue('breach-register.md has no "Still open after Phase 0 wave 2" list');
    expect(str_contains($runbook, 'Phase 0 of the permission program closed nine holes'))->toBeFalse('breach-register.md still calls PA-04 and SS-1/SS-5 closed');
    $section = explode('## ', explode('## Still open after Phase 0 wave 2', $runbook, 2)[1] ?? '', 2)[0];

    foreach (pzoOpenItems() as $id => $keys) {
        $lines = array_values(array_filter(explode("\n", $section), fn (string $line) => str_contains($line, "`{$id}`")));
        expect($lines)->not->toBeEmpty("{$id} is not in the open list");
        foreach ([...$keys, 'TASK-0039'] as $needed) {
            expect(implode("\n", $lines))->toContain($needed);
        }
    }
});
