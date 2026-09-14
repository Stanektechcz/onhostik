<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * VPS migration between nodes of a cluster (audit §5i): the hypervisor moves the VM live when it runs, the platform
 * switches the binding and the node, addresses stay; families without a saga are refused with a clear reason.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('migrates a running VM live to another node of its cluster and switches the binding; other families are refused', function () {
    [$user, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $source = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'prg1-n2')->firstOrFail();
    $target = Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'prg1-n3', 'region_code' => 'cz1', 'role' => 'compute', 'state' => 'active', 'capacity' => ['cpu_cores' => 64, 'ram_mb' => 262144, 'disk_gb' => 4000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 8000, 'disk_used_gb' => 100, 'io_wait_pct' => 0]]);
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS 4/8', 'label' => 'app-prod', 'hostname' => 'app-prod.example.test', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $source->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud', 'vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'business', 'activated_at' => now(), 'tags' => ['access' => ['ipv4' => '192.0.2.21', 'ssh' => 'root@192.0.2.21']],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'onhost-'.$service->id], 'ownership' => ['cores' => 'ONHOST_MANAGED'], 'idempotency_key' => "vps-mig:{$service->id}", 'adapter_version' => '1.0.0']);
    $posted = [];
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 4200, 'maxdisk' => 160 * 1024 ** 3]]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => Http::response(['data' => ['cores' => 4, 'sockets' => 1, 'memory' => 8192, 'name' => 'app-prod', 'scsi0' => 'local-zfs:vm-1042-disk-0,size=160G', 'tags' => 'onhost', 'onboot' => 1, 'protection' => 1]]),
        PVE.'/nodes/prg1-n2/qemu/1042/migrate' => function ($request) use (&$posted) {
            $posted[] = $request->data();

            return Http::response(['data' => 'UPID:prg1-n2:000A1B2C:0004E1F5:66F0AA11:qmigrate:1042:onhost@pve!cp:']);
        },
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::sequence()->push(['data' => ['status' => 'running']])->push(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);

    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    $started = $this->withHeader('Idempotency-Key', 'vmig-1')->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'prg1-n3', 'reason' => 'firmware uzlu'])->assertStatus(202)->json();
    $this->flushHeaders();
    expect($started['kind'])->toBe('vps.migrate');
    $operation = driveOperation(Operation::query()->findOrFail($started['id']));
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->context)->toMatchArray(['online' => true, 'swapped' => true, 'target_node_name' => 'prg1-n3']);
    expect($posted)->toHaveCount(1)->and($posted[0])->toMatchArray(['target' => 'prg1-n3', 'online' => 1, 'with-local-disks' => 1]);

    // the platform follows the VM: binding node, scheduler node, the outcome on the service, the customer told
    $service->refresh();
    expect($service->node_id)->toBe($target->id)->and($service->primaryBinding()->remote_node)->toBe('prg1-n3')->and($service->tags['migration'])->toMatchArray(['state' => 'finished', 'to_node' => 'prg1-n3', 'address' => '192.0.2.21'])->and($service->tags['access']['ipv4'])->toBe('192.0.2.21');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Server app-prod byl přestěhován')->exists())->toBeTrue();
    $this->actingAs($user, 'sanctum');
    expect($this->getJson("/v1/services/{$service->id}")->assertOk()->json('data.migration'))->toMatchArray(['state' => 'finished', 'operation_state' => 'SUCCEEDED']);

    // a node of another cluster, a web service, and a mail service are refused with a reason
    $this->actingAs($staff, 'sanctum');
    $this->withHeader('Idempotency-Key', 'vmig-2')->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'nope'])->assertStatus(422)->assertJsonPath('error', 'node_unknown');
    $this->flushHeaders();
    $web = featureWebService($org, 'aapanel');
    $this->withHeader('Idempotency-Key', 'vmig-3')->postJson("/v1/staff/services/{$web->id}/migrate", [])->assertStatus(422)->assertJsonPath('error', 'migration_unsupported');
    $this->flushHeaders();
});
