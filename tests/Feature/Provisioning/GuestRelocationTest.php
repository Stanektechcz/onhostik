<?php

declare(strict_types=1);

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ResourceDrift;
use Onhost\Domain\Provisioning\Reconciler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\VirtualMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

/*
 * A VM that HA moved to another node after a node failure — or that somebody moved by hand — answers from a node its
 * binding does not name. Every later action went to the old node and was refused there: a restart, a backup, and the
 * cancellation (the identity check will not delete on a binding that names another node). The reconciler, which reads
 * every service every few minutes anyway, lets the binding follow the VM — once the VM proves to be this service's.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** A running VPS whose binding still names prg1-n2. */
function relocatedVps(Organization $org): Service
{
    $instance = pveLab();
    Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => 'prg1-n3'], ['region_code' => 'cz1', 'role' => 'compute', 'state' => 'active', 'capacity' => ['cpu_cores' => 64, 'ram_mb' => 262144, 'disk_gb' => 4000], 'usage' => [], 'failure_domain' => 'rack-b']);
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-test.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => Node::query()->where('name', 'prg1-n2')->value('id'), 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 20], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [], 'health' => []]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1043', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-test'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "relocate:{$service->id}", 'adapter_version' => '1.0.0']);
    VirtualMachine::query()->create(['service_id' => $service->id, 'vmid' => 1043, 'node' => 'prg1-n2', 'cores' => 4, 'memory_mb' => 8192, 'disk_gb' => 20, 'state' => 'running']);

    return $service;
}

/** The cluster after the move: prg1-n2 no longer holds 1043, prg1-n3 does, with the config given. @param array<string,mixed> $config */
function relocatedCluster(array $config): void
{
    Http::fake(function (Request $r) use ($config) {
        $path = substr((string) parse_url($r->url(), PHP_URL_PATH), strlen('/api2/json'));

        return match (true) {
            str_starts_with($path, '/nodes/prg1-n2/qemu/1043/') => Create::promiseFor(new Psr7Response(500, ['Content-Type' => 'application/json'], '{"data":null}', '1.1', "Configuration file 'nodes/prg1-n2/qemu-server/1043.conf' does not exist")),
            $path === '/cluster/resources' => Http::response(['data' => [['type' => 'qemu', 'vmid' => 1043, 'node' => 'prg1-n3', 'name' => (string) ($config['name'] ?? ''), 'status' => 'running']]]),
            $path === '/nodes/prg1-n3/qemu/1043/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 420, 'ha' => ['managed' => 1]]]),
            $path === '/nodes/prg1-n3/qemu/1043/config' => Http::response(pveVmConfig($config)),
            default => null,
        };
    });
}

it('lets the binding follow a VM that HA moved to another node', function () {
    [, $org] = $this->customerWithOrganization();
    $service = relocatedVps($org);
    relocatedCluster(['name' => 'vm-test', 'tags' => 'onhost;'.ProxmoxComputeProvider::serviceTag($service->id), 'cores' => 4, 'memory' => 8192]);

    app(Reconciler::class)->reconcileService($service, CommandContext::system('reconcile test'));

    // it used to be: the binding kept prg1-n2 — every action, and the cancellation, was refused there until somebody fixed it by hand
    expect(ProviderBinding::query()->where('service_id', $service->id)->value('remote_node'))->toBe('prg1-n3')
        ->and(VirtualMachine::query()->where('service_id', $service->id)->value('node'))->toBe('prg1-n3')
        ->and($service->fresh()->node_id)->toBe(Node::query()->where('name', 'prg1-n3')->value('id')) // capacity is counted where it runs
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
    expect(AuditEvent::query()->where('action', 'provisioning.guest.relocated')->where('resource_id', $service->id)->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('name', 'service.relocated')->where('aggregate_id', $service->id)->value('payload'))->toMatchArray(['from' => 'prg1-n2', 'to' => 'prg1-n3', 'proof' => 'service_tag']);
});

it('does not follow a machine under the same number that is not provably this service\'s', function () {
    [, $org] = $this->customerWithOrganization();
    $service = relocatedVps($org);
    relocatedCluster(['name' => 'cizi-stroj', 'tags' => 'jiny-projekt']); // no service tag, another name

    app(Reconciler::class)->reconcileService($service, CommandContext::system('reconcile test'));

    expect(ProviderBinding::query()->where('service_id', $service->id)->value('remote_node'))->toBe('prg1-n2') // nothing moved
        ->and(ResourceDrift::query()->where('service_id', $service->id)->where('field', 'node')->value('classification'))->toBe('SECURITY_SUSPICIOUS')
        ->and(OutboxMessage::query()->where('name', 'service.relocated')->exists())->toBeFalse();
});
