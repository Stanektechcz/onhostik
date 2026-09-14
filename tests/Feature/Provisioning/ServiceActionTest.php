<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Errors\DomainError;

function activeVps(Organization $org, string $state = ServiceStateMachine::ACTIVE): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-test.cust.onhost.cz', 'state' => $state, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud', 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160]], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'ipv4' => 1], 'sla_class' => 'standard', 'activated_at' => now(),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-test'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);
    app(IpamService::class)->allocate(4, 'cz1', 'vps', $service->id, $org->id);

    return $service;
}

beforeEach(fn () => Http::preventStrayRequests());

it('reboots a VM through an operation and records the actual state', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig()),
        PVE.'/nodes/prg1-n2/qemu/1042/status/reboot' => Http::response(['data' => 'UPID:prg1-n2:000A1B2F:0004E1F8:66F0AA14:qmreboot:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = activeVps($org);
    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'power', $this->contextFor($user, $org), 'act-reboot-1', ['power_action' => 'reboot']));
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->kind)->toBe('service.action');
    expect($service->fresh()->actual_spec['status'])->toBe('running')->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/status/reboot'));
    expect(AuditEvent::query()->where('action', 'service.power')->where('resource_id', $service->id)->exists())->toBeTrue();
    expect(app(ServiceService::class)->requestAction($service->fresh(), 'power', $this->contextFor($user, $org), 'act-reboot-1', ['power_action' => 'reboot'])->id)->toBe($operation->id);
});

it('guards actions by state, step-up, legal hold and concurrency', function () {
    Http::fake();
    [$user, $org] = $this->customerWithOrganization();
    $services = app(ServiceService::class);
    $ctx = $this->contextFor($user, $org);

    $suspended = activeVps($org, ServiceStateMachine::SUSPENDED);
    expect(fn () => $services->requestAction($suspended, 'power', $ctx, 'g-1', ['power_action' => 'start']))->toThrow(DomainError::class, 'not allowed while');
    expect(fn () => $services->requestAction($suspended, 'terminate', $ctx, 'g-2'))->toThrow(DomainError::class, 'step-up');
    $suspended->forceFill(['legal_hold' => true])->save();
    expect(fn () => $services->requestAction($suspended, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'g-3'))->toThrow(DomainError::class, 'legal hold');
    expect(fn () => $services->requestAction($suspended, 'power', $ctx, 'g-4', ['power_action' => 'explode']))->toThrow(DomainError::class);
    Operation::query()->create(['service_id' => $suspended->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class, 'state' => Operation::WAITING, 'step' => 0, 'steps_total' => 1, 'actor_type' => 'system', 'idempotency_key' => 'busy', 'correlation_id' => 'c', 'desired' => [], 'context' => [], 'queue' => 'q', 'queued_at' => now(), 'next_run_at' => now()->addMinute(), 'retry_until' => now()->addHour()]);
    expect(fn () => $services->requestAction($suspended, 'resume', $ctx, 'g-5'))->toThrow(DomainError::class, 'still running');
    Http::assertNothingSent();
});

it('terminates with a final protected backup, destroys the VM, quarantines the IPs and soft-deletes the service', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => fn (Request $r) => $r->method() === 'GET' ? Http::response(pveVmConfig()) : Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/vzdump' => Http::response(['data' => 'UPID:prg1-n2:000A1B30:0004E1F9:66F0AA15:vzdump:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/storage/pbs-cz1/content*' => Http::response(['data' => [['volid' => 'pbs-cz1:backup/vm/1042/2026-09-06T10:00:00Z', 'ctime' => time(), 'size' => 123456789, 'protected' => 1, 'verification' => ['state' => 'ok']]]]),
        PVE.'/nodes/prg1-n2/qemu/1042/status/stop' => Http::response(['data' => 'UPID:prg1-n2:000A1B31:0004E1FA:66F0AA16:qmstop:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/qemu/1042' => Http::response(['data' => 'UPID:prg1-n2:000A1B32:0004E1FB:66F0AA17:qmdestroy:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = activeVps($org);
    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'act-term-1', ['reason' => 'customer request']));

    expect($operation->state)->toBe(Operation::SUCCEEDED);
    $gone = Service::withTrashed()->findOrFail($service->id);
    expect($gone->state)->toBe(ServiceStateMachine::TERMINATED)->and($gone->deleted_at)->not->toBeNull()->and($gone->terminated_at)->not->toBeNull()->and($gone->retention_until)->not->toBeNull();
    $backup = Backup::query()->where('service_id', $service->id)->firstOrFail();
    expect($backup->kind)->toBe('final')->and($backup->state)->toBe('completed')->and($backup->protected)->toBeTrue()->and($backup->remote_id)->toBe('pbs-cz1:backup/vm/1042/2026-09-06T10:00:00Z')->and($backup->size_bytes)->toBe(123456789);
    expect(IpAddress::query()->where('address', '192.0.2.2')->value('state'))->toBe('quarantine');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/vzdump') && $r['mode'] === 'stop' && $r['protected'] === 1);
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/qemu/1042'));
});
