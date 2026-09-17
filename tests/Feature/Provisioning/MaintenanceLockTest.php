<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\ResourceDrift;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Provisioning\Reconciler;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * A maintenance lock on a panel (Brain cards H322 and H311): automation keeps its hands off the instance, a lock never
 * lifts itself when its time runs out, and a changed panel address is a lock too — until a probe run by staff proves
 * the panel answers there. Reconciliation under a lock only records what it sees.
 */

beforeEach(fn () => Http::preventStrayRequests());

function lockedVps(Organization $org, int $ramMb = 8192): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-lock.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud', 'entitlements' => ['vcpu' => 4, 'ram_mb' => $ramMb, 'nvme_gb' => 160]], 'entitlements' => ['vcpu' => 4, 'ram_mb' => $ramMb, 'nvme_gb' => 160, 'ipv4' => 1], 'sla_class' => 'standard', 'activated_at' => now(),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-lock'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);
    app(IpamService::class)->allocate(4, 'cz1', 'vps', $service->id, $org->id);

    return $service;
}

it('keeps the scheduler and the auto-repair off a locked panel and only records what the reconciler sees (H322)', function () {
    config(['onhost.provisioning.auto_repair' => true]);
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig()), // the VM has 8 GB, the plan says 16 GB: a repairable drift
    ]);
    [, $org] = $this->customerWithOrganization();
    $service = lockedVps($org, 16384);
    $instance = ProviderInstance::query()->where('key', 'proxmox-cz1')->firstOrFail();
    $scheduler = app(NodeScheduler::class);
    expect($scheduler->pick(['role' => 'compute', 'region' => 'cz1', 'ram_mb' => 1024])['node']->name)->toBe('prg1-n2');

    app(ProviderInstanceService::class)->setState($instance, 'maintenance', CommandContext::system('test'), 'firmware of the storage controller', now()->addHours(2));
    expect($instance->fresh()->state_reason)->toBe('firmware of the storage controller');
    expect(fn () => $scheduler->pick(['role' => 'compute', 'region' => 'cz1', 'ram_mb' => 1024]))->toThrow(DomainError::class, 'No schedulable node');

    app(Reconciler::class)->reconcileService($service, CommandContext::system('test'));
    $service->refresh();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and(data_get($service->health, 'observe_only'))->toBeTrue()
        ->and(ResourceDrift::query()->where('service_id', $service->id)->where('state', 'open')->exists())->toBeTrue() // seen and recorded
        ->and(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse(); // never repaired under a lock
});

it('never lifts an expired lock by itself: the panel has to answer a probe first (H322)', function () {
    Http::fake(function (Request $request) {
        static $answers = 0;
        $answers++;
        if (str_ends_with($request->url(), '/api2/json/version')) {
            return $answers <= 1 ? Http::response(['errors' => 'panel restarting'], 503) : Http::response(['data' => ['version' => '8.2.4', 'release' => '8.2']]);
        }
        if (str_ends_with($request->url(), '/api2/json/cluster/status')) {
            return Http::response(['data' => [['type' => 'cluster', 'quorate' => 1]]]);
        }

        return Http::response(['data' => []]);
    });
    pveLab();
    $instance = ProviderInstance::query()->where('key', 'proxmox-cz1')->firstOrFail();
    app(ProviderInstanceService::class)->setState($instance, 'maintenance', CommandContext::system('test'), 'kernel update', now()->subMinutes(5));
    expect($instance->fresh()->maintenanceExpired())->toBeTrue()->and($instance->fresh()->isUsable())->toBeFalse();

    // the window passed but the panel is still down: the lock stays, operations hear about it once
    $probe = app(IntegrationHealthProbe::class);
    expect($probe->probeInstance($instance->fresh())['up'])->toBeFalse();
    expect($instance->fresh()->state)->toBe('maintenance');
    expect(OutboxMessage::query()->where('name', 'integration.maintenance.overdue')->count())->toBe(1);
    $probe->probeInstance($instance->fresh());
    expect(OutboxMessage::query()->where('name', 'integration.maintenance.overdue')->count())->toBe(1);

    // the panel answers: the probe returns the instance to automation and says so
    expect($probe->probeInstance($instance->fresh())['up'])->toBeTrue();
    $fresh = $instance->fresh();
    expect($fresh->state)->toBe('active')->and($fresh->state_reason)->toBeNull()->and($fresh->maintenance_until)->toBeNull()->and($fresh->isUsable())->toBeTrue();
    expect(OutboxMessage::query()->where('name', 'integration.maintenance.lifted')->exists())->toBeTrue();
});

it('locks an instance whose panel address moved until staff confirm the change and a probe finds the panel there (H311)', function () {
    Http::fake([
        'https://pve-new.onhost.internal:8006/api2/json/version' => Http::response(['data' => ['version' => '8.2.4', 'release' => '8.2']]),
        'https://pve-new.onhost.internal:8006/api2/json/cluster/status' => Http::response(['data' => [['type' => 'cluster', 'quorate' => 1]]]),
        'https://pve-new.onhost.internal:8006/*' => Http::response(['data' => []]),
    ]);
    $admin = $this->staff('infrastructure_admin');
    $this->actingAs($admin, 'sanctum');
    app(StepUpService::class)->grant($admin, 'totp', null, '127.0.0.1');
    $base = ['key' => 'proxmox-move', 'provider' => 'proxmox', 'base_url' => 'https://pve-old.onhost.internal:8006', 'options' => ['verify_tls' => false], 'credentials' => ['token_id' => 'onhost@pve!cp', 'token_secret' => 'e1f2a3b4-0000-4000-8000-000000000001']];
    $this->postJson('/v1/staff/integrations', $base)->assertCreated()->assertJsonPath('state', 'active');

    // the same host with another port or path is not a move; another host is, and it needs an explicit acknowledgement
    $this->putJson('/v1/staff/integrations/proxmox-move', array_merge($base, ['base_url' => 'https://pve-old.onhost.internal:8007']))->assertCreated()->assertJsonPath('state', 'active');
    $this->putJson('/v1/staff/integrations/proxmox-move', array_merge($base, ['base_url' => 'https://pve-new.onhost.internal:8006']))->assertStatus(409)->assertJsonPath('error', 'instance_host_change_unconfirmed');
    expect(ProviderInstance::query()->where('key', 'proxmox-move')->value('base_url'))->toBe('https://pve-old.onhost.internal:8007');
    Http::assertNothingSent(); // no credential travelled to the new host

    $moved = $this->putJson('/v1/staff/integrations/proxmox-move', array_merge($base, ['base_url' => 'https://pve-new.onhost.internal:8006', 'confirm_host_change' => true]))->assertCreated();
    expect($moved->json('state'))->toBe('maintenance')->and($moved->json('state_reason'))->toContain('pve-new.onhost.internal');
    expect(ProviderInstance::query()->where('key', 'proxmox-move')->firstOrFail()->isUsable())->toBeFalse();

    // the probe staff run from the console is what activates the new address
    $this->postJson('/v1/staff/integrations/proxmox-move/probe')->assertOk()->assertJsonPath('up', true);
    $instance = ProviderInstance::query()->where('key', 'proxmox-move')->firstOrFail();
    expect($instance->state)->toBe('active')->and($instance->state_reason)->toBeNull();
    expect(OutboxMessage::query()->where('name', 'integration.maintenance.lifted')->exists())->toBeTrue();
});
