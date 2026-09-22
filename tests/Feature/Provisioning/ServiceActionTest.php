<?php

declare(strict_types=1);

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response as Psr7Response;
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

it('cancels in two phases: archive and deactivate now, remove the VM only after the restore window (audit §5ab)', function () {
    $dumped = false; // the backup storage holds an older archive of the machine; the final one appears only after this run's vzdump
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => fn (Request $r) => $r->method() === 'GET' ? Http::response(pveVmConfig()) : Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/vzdump' => function () use (&$dumped) {
            $dumped = true;

            return Http::response(['data' => 'UPID:prg1-n2:000A1B30:0004E1F9:66F0AA15:vzdump:1042:onhost@pve!cp:']);
        },
        PVE.'/nodes/prg1-n2/storage/pbs-cz1/content*' => function () use (&$dumped) {
            $older = ['volid' => 'pbs-cz1:backup/vm/1042/2026-08-30T02:00:00Z', 'ctime' => time() + 60, 'size' => 111, 'protected' => 0]; // even with a later clock it is not this run's archive
            $final = ['volid' => 'pbs-cz1:backup/vm/1042/2026-09-06T10:00:00Z', 'ctime' => time(), 'size' => 123456789, 'protected' => 1, 'verification' => ['state' => 'ok']];

            return Http::response(['data' => $dumped ? [$older, $final] : [$older]]);
        },
        PVE.'/nodes/prg1-n2/qemu/1042/status/shutdown' => Http::response(['data' => 'UPID:prg1-n2:000A1B35:0004E1FD:66F0AA19:qmshutdown:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/qemu/1042/status/stop' => Http::response(['data' => 'UPID:prg1-n2:000A1B31:0004E1FA:66F0AA16:qmstop:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/qemu/1042' => Http::response(['data' => 'UPID:prg1-n2:000A1B32:0004E1FB:66F0AA17:qmdestroy:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = activeVps($org);
    $services = app(ServiceService::class);
    // an add-on bought for this VM: it has no resource of its own, so nothing ever stopped it — it stayed ACTIVE and billed on
    $addon = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'backup-hourly', 'family' => 'addon', 'name' => 'Hodinové zálohy', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => $service->region_code,
        'provider_instance_id' => $service->provider_instance_id, 'entitlements' => ['interval_hours' => 1, 'retention_days' => 30, 'offsite' => true], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => ['parent_service_id' => $service->id], 'desired_spec' => []]);

    // phase 1 — the customer cancels: everything is archived, the VM is switched off, nothing is destroyed
    $cancel = driveOperation($services->requestAction($service, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'act-term-1', ['reason' => 'customer request']));
    expect($cancel->state)->toBe(Operation::SUCCEEDED, (string) data_get($cancel->error, 'message', ''));
    $deactivated = Service::query()->findOrFail($service->id);
    expect($deactivated->state)->toBe(ServiceStateMachine::SUSPENDED)->and($deactivated->deleted_at)->toBeNull()
        ->and($deactivated->terminate_at)->not->toBeNull()->and((int) now()->diffInDays($deactivated->terminate_at))->toBeGreaterThanOrEqual(29)
        ->and(data_get($deactivated->tags, 'deletion.grace_days'))->toBe(30);
    expect($addon->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and($addon->fresh()->terminate_at)->not->toBeNull(); // the add-on goes with the service it belonged to
    $backup = Backup::query()->where('service_id', $service->id)->firstOrFail();
    expect($backup->kind)->toBe('final')->and($backup->state)->toBe('completed')->and($backup->protected)->toBeTrue()->and($backup->remote_id)->toBe('pbs-cz1:backup/vm/1042/2026-09-06T10:00:00Z')->and($backup->size_bytes)->toBe(123456789);
    expect(data_get($backup->meta, 'identity.ok'))->toBeTrue()->and(data_get($backup->meta, 'identity.matched'))->toBeGreaterThanOrEqual(5);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/vzdump') && $r['mode'] === 'stop' && $r['protected'] === 1);
    Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/qemu/1042'));
    expect(IpAddress::query()->where('address', '192.0.2.2')->value('state'))->toBe('allocated');

    // the removal is refused while the restore window runs
    expect(fn () => $services->requestAction($deactivated, 'purge', $this->contextFor($user, $org, 'webauthn'), 'act-purge-early'))->toThrow(DomainError::class, 'ochranné lhůtě');

    // phase 2 — the window passed: the VM is destroyed, the IPs quarantined, the service soft-deleted, the archive's 60 days start now
    $deactivated->forceFill(['terminate_at' => now()->subDay()])->save();
    $purge = driveOperation($services->requestAction($deactivated->fresh(), 'purge', $this->contextFor($user, $org, 'webauthn'), 'act-purge-1', ['reason' => 'grace window over']));
    expect($purge->state)->toBe(Operation::SUCCEEDED);
    $gone = Service::withTrashed()->findOrFail($service->id);
    expect($gone->state)->toBe(ServiceStateMachine::TERMINATED)->and($gone->deleted_at)->not->toBeNull()->and($gone->terminated_at)->not->toBeNull()->and($gone->retention_until)->not->toBeNull();
    expect(IpAddress::query()->where('address', '192.0.2.2')->value('state'))->toBe('quarantine');
    expect(Backup::query()->where('service_id', $service->id)->count())->toBe(1); // the archive of the cancellation is reused, not taken again
    expect((int) now()->diffInDays($backup->fresh()->retention_until))->toBeGreaterThanOrEqual(59);
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/qemu/1042'));
});

it('brings a cancelled service back inside the restore window and calls the removal off (audit §5ab)', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'stopped']]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => fn (Request $r) => $r->method() === 'GET' ? Http::response(pveVmConfig()) : Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/qemu/1042/status/start' => Http::response(['data' => 'UPID:prg1-n2:000A1B36:0004E1FE:66F0AA1A:qmstart:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = activeVps($org, ServiceStateMachine::SUSPENDED);
    $service->forceFill(['terminate_at' => now()->addDays(30), 'tags' => ['deletion' => ['grace_until' => now()->addDays(30)->toIso8601String()]]])->save();

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'resume', $this->contextFor($user, $org), 'act-restore-1'));
    expect($operation->state)->toBe(Operation::SUCCEEDED);
    $back = Service::query()->findOrFail($service->id);
    expect($back->state)->toBe(ServiceStateMachine::ACTIVE)->and($back->terminate_at)->toBeNull()
        ->and(data_get($back->tags, 'deletion'))->toBeNull()->and(data_get($back->tags, 'deletion_cancelled.cancelled_at'))->not->toBeNull();
});

it('still finishes a cancellation when the resource is already gone from the panel (audit §5ab)', function () {
    Http::fake([
        // the VM is not there any more: Proxmox has no 404 for a guest, it names the missing configuration file in its status line
        PVE.'/nodes/prg1-n2/qemu/1042/*' => Create::promiseFor(new Psr7Response(500, ['Content-Type' => 'application/json'], '{"data":null}', '1.1', "Configuration file 'nodes/prg1-n2/qemu-server/1042.conf' does not exist")),
        PVE.'/cluster/resources*' => Http::response(['data' => []]), // and it is on no other node of the cluster either
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = activeVps($org);
    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'act-gone-1', ['reason' => 'resource already deleted']));

    expect($operation->state)->toBe(Operation::SUCCEEDED);
    $backup = Backup::query()->where('service_id', $service->id)->firstOrFail();
    expect($backup->state)->toBe('completed')->and(implode(' ', (array) data_get($backup->meta, 'gaps')))->toContain('no longer exists')
        ->and((array) data_get($backup->meta, 'parts'))->toContain('service.json'); // the description of the service is kept even so
    expect(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::SUSPENDED);
    Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
});
