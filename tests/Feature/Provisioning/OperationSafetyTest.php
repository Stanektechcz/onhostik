<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Organizations\ProjectService;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\OperationAttempt;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\OperationRunner;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/*
 * Three ways a long provisioning run can do harm without any single call failing (Brain cards H319, H327, H315): a
 * panel that answers 200 with no identifier, a provider task our deadline gave up on but the panel did not, and a
 * person whose permission was taken away while their operation was still walking through its steps.
 */

beforeEach(fn () => Http::preventStrayRequests());

function safetyVps(Organization $org): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-safe.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud', 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160]], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'ipv4' => 1], 'sla_class' => 'standard', 'activated_at' => now(),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-safe'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);
    app(IpamService::class)->allocate(4, 'cz1', 'vps', $service->id, $org->id);

    return $service;
}

it('never binds a service to a resource the panel did not identify (H319)', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = safetyVps($org);
    $operation = Operation::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class, 'state' => Operation::RUNNING, 'step' => 0, 'steps_total' => 1, 'actor_type' => 'system', 'idempotency_key' => 'bind-guard', 'correlation_id' => 'c', 'desired' => [], 'context' => [], 'queue' => 'q', 'queued_at' => now(), 'next_run_at' => now(), 'retry_until' => now()->addHour()]);
    $context = new StepContext($operation, $service, app(ProviderRegistry::class), app(), CommandContext::system('test'));
    $instance = pveLab();
    $before = ProviderBinding::query()->count();

    foreach (['', '  ', '0'] as $nothing) {
        expect(fn () => $context->bind($instance, 'qemu', $nothing, 'prg1-n2'))->toThrow(RuntimeException::class, 'no usable identifier');
    }
    expect(ProviderBinding::query()->count())->toBe($before);
    expect($context->bind($instance, 'qemu', '2077', 'prg1-n2')->remote_id)->toBe('2077'); // a real identifier still binds
});

it('keeps a timed-out provider task on record and refuses to start over until someone has looked at it (H327)', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig()),
        PVE.'/nodes/prg1-n2/qemu/1042/status/reboot' => Http::response(['data' => 'UPID:prg1-n2:000A1B2F:0004E1F8:66F0AA14:qmreboot:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'running']]), // the panel is still working on it
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = safetyVps($org);
    $operation = app(ServiceService::class)->requestAction($service, 'power', $this->contextFor($user, $org), 'safe-reboot-1', ['power_action' => 'reboot']);
    $runner = app(OperationRunner::class);
    expect($runner->tick($operation, 5))->toBe(Operation::WAITING);

    // time passes: the wait is older than the handle allows, the panel still says "running"
    $operation->refresh();
    $operation->forceFill(['external_handle' => array_merge((array) $operation->external_handle, ['timeout' => 1]), 'next_run_at' => now()])->save();
    $operation->forceFill(['started_at' => now()->subMinutes(11)])->save();
    OperationAttempt::query()->where('operation_id', $operation->id)->where('outcome', 'wait')->update(['started_at' => now()->subMinutes(10)]);
    expect($runner->tick($operation, 5))->toBe(Operation::FAILED);

    $failed = $operation->fresh();
    expect(data_get($failed->error, 'detail.vendor_task_unconfirmed'))->toBeTrue()
        ->and($failed->external_handle)->not->toBeNull() // still traceable at the panel
        ->and((string) data_get($failed->error, 'message'))->toContain('not confirmed');

    $operations = app(OperationService::class);
    $staff = CommandContext::system('test');
    expect(fn () => $operations->retry($failed, $staff, 'try again'))->toThrow(DomainError::class, 'timed out without a confirmed result');
    expect(fn () => $operations->retry($failed, $staff, null, true))->toThrow(DomainError::class, 'needs a reason');
    expect($operations->retry($failed, $staff, 'task UPID…qmreboot finished OK at the panel, VM is up', true)->state)->toBe(Operation::PENDING);
});

it('stops a running operation before its next step when the permission it started under is revoked (H315)', function () {
    Http::fake(); // nothing may reach the panel in this test
    [$user, $org] = $this->customerWithOrganization();
    $service = safetyVps($org);
    // queued, not yet picked up by a worker: with the sync test queue requestAction() would run the whole operation on the
    // spot, while the person is still allowed — the case here is the worker reaching the row after the revocation
    $operation = app(OperationService::class)->start(ServiceActionWorkflow::class, 'safe-reboot-2', ['action' => 'power', 'power_action' => 'reboot', 'service_id' => $service->id],
        $this->contextFor($user, $org), $service->id, $org->id, null, $service->provider_instance_id, dispatch: false, authorizedPermission: ServiceActionCommand::permissionFor('power'));
    expect($operation->authorized_permission)->toBe('service.manage')->and($operation->state)->toBe(Operation::PENDING);

    // the worker has met this user before: its authorizer remembers the old bindings
    expect(app(Authorizer::class)->can($user, 'service.manage', CommandScope::resource($service->id, $org->id)))->toBeTrue();
    PolicyBinding::query()->where('principal_id', $user->id)->delete();

    expect(app(OperationRunner::class)->tick($operation, 5))->toBe(Operation::FAILED);
    $stopped = $operation->fresh();
    expect(data_get($stopped->error, 'detail.access_revoked'))->toBeTrue()
        ->and(data_get($stopped->error, 'detail.required_permission'))->toBe('service.manage')
        ->and((string) data_get($stopped->error, 'message'))->toContain('revoked');
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/status/reboot'));

    // a run nobody can be revoked from — the scheduled purge, a reconcile repair — is never stopped this way
    $system = app(ServiceService::class)->requestAction($service->fresh(), 'backup', CommandContext::system('nightly'), 'safe-backup-1');
    expect($system->actor_type)->toBe('system');
});

it('lets somebody whose right comes from the project, or from the one service shared with them, finish what they started (H315)', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig()),
        PVE.'/nodes/prg1-n2/qemu/1042/status/reboot' => Http::response(['data' => 'UPID:prg1-n2:000A1B2F:0004E1F8:66F0AA14:qmreboot:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
    [$owner, $org] = $this->customerWithOrganization();
    $service = safetyVps($org);
    $project = Project::query()->where('organization_id', $org->id)->firstOrFail();
    $service->forceFill(['project_id' => $project->id])->save();
    $organizations = app(OrganizationService::class);

    // a viewer of the organization who is a developer of THIS project: the re-check before each step asked at the bare
    // service, without its project, so the project role did not cover it and the run died as "permission revoked"
    $developer = $this->customer(['email' => 'projekt@example.cz']);
    $organizations->attachMember($org, $developer, 'viewer', CommandContext::system('test'), true);
    app(ProjectService::class)->addMember($org, $project, $developer, 'developer', CommandContext::system('test'));
    // and a guest who was handed this one service
    $guest = $this->customer(['email' => 'host@example.cz']);
    $organizations->attachMember($org, $guest, 'guest', CommandContext::system('test'), true);
    app(ServiceAccessService::class)->share($org, $service, 'host@example.cz', ['manage'], $this->contextFor($owner, $org, 'totp'));

    foreach ([[$developer, 'proj-reboot'], [$guest, 'guest-reboot']] as [$person, $key]) {
        $operation = app(OperationService::class)->start(ServiceActionWorkflow::class, $key, ['action' => 'power', 'power_action' => 'reboot', 'service_id' => $service->id],
            $this->contextFor($person, $org), $service->id, $org->id, null, $service->provider_instance_id, dispatch: false, authorizedPermission: ServiceActionCommand::permissionFor('power'));
        $state = app(OperationRunner::class)->tick($operation, 5);
        expect(data_get($operation->fresh()->error, 'detail.access_revoked'))->toBeNull("{$person->email}: ".json_encode($operation->fresh()->error))->and($state)->not->toBe(Operation::FAILED);
        Operation::query()->whereKey($operation->id)->delete();
        $service->forceFill(['state' => ServiceStateMachine::ACTIVE])->save();
    }
});

it('asks a staff run for its permission at the scope the bus checked: global, never the customer resource (H315)', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig()),
        PVE.'/nodes/prg1-n2/qemu/1042/status/reboot' => Http::response(['data' => 'UPID:prg1-n2:000A1B2F:0004E1F8:66F0AA14:qmreboot:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'running']]),
    ]);
    [, $org] = $this->customerWithOrganization();
    $service = safetyVps($org);
    $staff = $this->staff('shared_hosting_admin');
    $start = fn (string $key) => app(OperationService::class)->start(ServiceActionWorkflow::class, $key, ['action' => 'power', 'power_action' => 'reboot', 'service_id' => $service->id],
        $this->contextFor($staff), $service->id, $org->id, null, $service->provider_instance_id, dispatch: false, authorizedPermission: 'staff.service.manage', authorizedScope: 'global');

    // staff hold no role on the customer's resource, only globally: the run must still be allowed to reach the panel
    $allowed = $start('staff-reboot-1');
    expect($allowed->authorized_scope)->toBe('global');
    expect(app(OperationRunner::class)->tick($allowed, 5))->toBe(Operation::WAITING);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/status/reboot'));

    // the role is taken away before the next run is picked up: it stops before its first step
    Operation::query()->whereKey($allowed->id)->update(['state' => Operation::CANCELLED]); // one operation per service at a time
    $blocked = $start('staff-reboot-2');
    PolicyBinding::query()->where('principal_id', $staff->id)->delete();
    expect(app(OperationRunner::class)->tick($blocked, 5))->toBe(Operation::FAILED);
    expect(data_get($blocked->fresh()->error, 'detail.access_revoked'))->toBeTrue()->and(data_get($blocked->fresh()->error, 'detail.required_permission'))->toBe('staff.service.manage');
});
