<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\DestructivePreview;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\VmReinstall;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

/*
 * Phase C, C9 — a fresh operating system on a customer's own server. There was no way to do it: a broken VPS was a
 * ticket and a hand-made clone. It is a service action now, under every rule a destructive action has: HIGH risk with a
 * fresh step-up, the server's own preview, a protected snapshot first — and only a system the platform allows (a golden
 * template of the instance that the product sells). Proxmox is Http::fake throughout.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** A delivered VPS on the lab cluster whose VM carries the service's own tag. */
function reinstallVps(object $org, array $entitlements = []): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-reinstall.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => array_merge(['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'snapshots' => 3], $entitlements), 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-reinstall'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "reinstall:{$service->id}", 'adapter_version' => '1.0.0']);

    return $service;
}

/**
 * A stateful cluster: VM 1042 (running, its own tag), template 9001, every write recorded in order.
 *
 * @param  array<string,mixed>  $vm  the VM as the test moves it
 * @param  list<string>  $writes
 */
function reinstallCluster(array &$vm, array &$writes, string $importExit = 'OK'): void
{
    Http::fake(function (Request $request) use (&$vm, &$writes, $importExit) {
        if (! str_starts_with($request->url(), 'https://pve.mgmt.test:8006/api2/json')) {
            return null;
        }
        $path = substr((string) parse_url($request->url(), PHP_URL_PATH), strlen('/api2/json'));
        $method = $request->method();
        if ($method !== 'GET') {
            $writes[] = $method.' '.$path;
        }
        $upid = fn (string $what) => Http::response(['data' => "UPID:prg1-n2:000C0001:0004E300:66F0CC01:{$what}:1042:onhost@pve!cp:"]);

        return match (true) {
            $path === '/nodes/prg1-n2/qemu/9001/config' => Http::response(['data' => ['template' => 1, 'name' => 'debian-13-golden', 'scsi0' => 'local-zfs:base-9001-disk-0,size=4G']]),
            $path === '/nodes/prg1-n2/qemu/1042/config' && $method === 'GET' => Http::response(['data' => ['name' => $vm['name'], 'tags' => $vm['tags'], 'cores' => 4, 'sockets' => 1, 'memory' => 8192, 'scsi0' => $vm['scsi0']] + ($vm['unused0'] ?? null ? ['unused0' => $vm['unused0']] : [])]),
            $path === '/nodes/prg1-n2/qemu/1042/config' && $method === 'POST' => (function () use (&$vm, $request, $upid, $importExit) {
                if ($importExit === 'OK') {
                    $vm['unused0'] = explode(',', $vm['scsi0'])[0];
                    $vm['scsi0'] = 'local-zfs:vm-1042-disk-1,size=4G';
                }
                $vm['import'] = $request['scsi0'];

                return $upid('qmconfig');
            })(),
            $path === '/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => $vm['status'], 'uptime' => 100]]),
            $path === '/nodes/prg1-n2/qemu/1042/status/stop' => (function () use (&$vm, $upid) {
                $vm['status'] = 'stopped';

                return $upid('qmstop');
            })(),
            $path === '/nodes/prg1-n2/qemu/1042/status/start' => (function () use (&$vm, $upid) {
                $vm['status'] = 'running';

                return $upid('qmstart');
            })(),
            $path === '/nodes/prg1-n2/qemu/1042/resize' => (function () use (&$vm, $request, $upid) {
                $vm['scsi0'] = 'local-zfs:vm-1042-disk-1,size='.$request['size'];

                return $upid('resize');
            })(),
            $path === '/nodes/prg1-n2/qemu/1042/snapshot' && $method === 'POST' => (function () use (&$vm, $request, $upid) {
                $vm['snapshots'][] = (string) $request['snapname'];

                return $upid('qmsnapshot');
            })(),
            $path === '/nodes/prg1-n2/qemu/1042/snapshot' => Http::response(['data' => array_merge(array_map(fn ($n) => ['name' => $n, 'snaptime' => 1_760_000_000], $vm['snapshots']), [['name' => 'current']])]),
            str_starts_with($path, '/nodes/prg1-n2/tasks/') => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => str_contains($path, 'qmconfig') ? $importExit : 'OK']]),
            default => Http::response(['data' => null], 501),
        };
    });
}

function reinstallVmState(Service $service): array
{
    return ['name' => 'vm-reinstall', 'tags' => 'onhost;'.ProxmoxComputeProvider::serviceTag($service->id), 'scsi0' => 'local-zfs:vm-1042-disk-0,size=160G', 'status' => 'running', 'snapshots' => []];
}

it('reinstalls a running server: safety snapshot, off, a fresh disk from the template, grown to the plan, on again', function () {
    $vm = [];
    $writes = [];
    reinstallCluster($vm, $writes);
    [$user, $org] = $this->customerWithOrganization();
    $service = reinstallVps($org);
    $vm = reinstallVmState($service);

    $features = app(ServiceFeatures::class)->features($service);
    expect($features['vm_reinstall']['enabled'])->toBeTrue()
        ->and($features['vm_reinstall']['options']['images'])->toBe(['debian-13']);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'reinstall', $this->contextFor($user, $org, 'webauthn'), 'reinst-1', ['confirm' => true, 'image' => 'debian-13']));
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''));

    // exactly this order, and nothing deleted on the way
    $path = '/nodes/prg1-n2/qemu/1042';
    expect($writes)->toBe(["POST {$path}/snapshot", "POST {$path}/status/stop", "POST {$path}/config", "PUT {$path}/resize", "POST {$path}/status/start"]);
    expect($vm['import'])->toBe('local-zfs:0,import-from=local-zfs:base-9001-disk-0')
        ->and($vm['unused0'])->toBe('local-zfs:vm-1042-disk-0') // the old disk is kept, detached — the safety snapshot lives on it
        ->and($vm['scsi0'])->toBe('local-zfs:vm-1042-disk-1,size=160G')
        ->and($vm['status'])->toBe('running');
    $copy = Backup::query()->where('service_id', $service->id)->where('kind', 'pre_reinstall')->first();
    expect($copy)->not->toBeNull()->and($copy->protected)->toBeTrue()->and($copy->state)->toBe('completed')->and($copy->operation_id)->toBe($operation->id);
    expect(data_get($operation->context, 'replaced_volume'))->toBe('local-zfs:vm-1042-disk-0')->and(data_get($operation->context, 'image'))->toBe('debian-13');
});

it('leaves a server the owner had switched off switched off', function () {
    $vm = [];
    $writes = [];
    reinstallCluster($vm, $writes);
    [$user, $org] = $this->customerWithOrganization();
    $service = reinstallVps($org);
    $vm = ['status' => 'stopped'] + reinstallVmState($service);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'reinstall', $this->contextFor($user, $org, 'webauthn'), 'reinst-off', ['confirm' => true, 'image' => 'debian-13']));
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and($writes)->not->toContain('POST /nodes/prg1-n2/qemu/1042/status/stop')
        ->and($writes)->not->toContain('POST /nodes/prg1-n2/qemu/1042/status/start')
        ->and($vm['status'])->toBe('stopped');
});

it('offers only a system the platform allows, and refuses any other before anything runs', function () {
    $vm = [];
    $writes = [];
    reinstallCluster($vm, $writes);
    [$user, $org] = $this->customerWithOrganization();
    $service = reinstallVps($org);
    $services = app(ServiceService::class);

    foreach ([['image' => 'ubuntu-24.04'], ['image' => 'local:iso/evil.iso'], []] as $i => $params) {
        try {
            $services->requestAction($service, 'reinstall', $this->contextFor($user, $org, 'webauthn'), "reinst-bad-{$i}", ['confirm' => true] + $params);
            $this->fail('expected a refusal');
        } catch (DomainError $e) {
            expect($e->error)->toBe('action_param_invalid')->and($e->extra['field'])->toBe('image')->and($e->extra['allowed'])->toBe(['debian-13']);
        }
    }
    expect($writes)->toBe([])->and(Operation::query()->where('service_id', $service->id)->count())->toBe(0);

    // the product narrows what the instance has: a template the catalogue does not sell is not offered
    Product::query()->create(['key' => 'vps', 'family' => 'cloud', 'executor' => 'proxmox', 'billing_model' => 'hourly', 'name' => ['cs' => 'VPS', 'en' => 'VPS'], 'description' => ['cs' => '', 'en' => ''], 'sort' => 30, 'state' => 'active', 'meta' => ['images' => ['ubuntu-24.04', 'rocky-9']]]);
    expect(app(VmReinstall::class)->images($service))->toBe([])
        ->and(app(ServiceFeatures::class)->features($service)['vm_reinstall']['enabled'])->toBeFalse();
});

it('is HIGH risk with a fresh step-up, and the preview names the system and the safety copy', function () {
    $vm = [];
    $writes = [];
    reinstallCluster($vm, $writes);
    [$user, $org] = $this->customerWithOrganization();
    $service = reinstallVps($org);
    $vm = reinstallVmState($service);

    $command = new ServiceActionCommand($org->id, 'k', ['action' => 'reinstall', 'service_id' => $service->id, 'params' => ['image' => 'debian-13']]);
    expect($command->requiresStepUp())->toBeTrue()->and($command->riskLevel())->toBe(PermissionCatalog::HIGH)->and($command->permission())->toBe('service.manage');

    $this->actingAs($user, 'sanctum');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'reinstall', 'params' => ['confirm' => true, 'image' => 'debian-13']], ['X-Organization' => $org->id, 'Idempotency-Key' => 'reinst-http-1'])
        ->assertStatus(403)->assertJsonPath('error', 'step_up_required');

    $preview = $this->getJson("/v1/services/{$service->id}/actions/reinstall/preview?params%5Bimage%5D=debian-13", ['X-Organization' => $org->id])->assertOk();
    expect(implode(' ', $preview->json('data.what') ?? $preview->json('what')))->toContain('debian-13')->toContain('Systémový disk');
    expect($preview->json('data.recovery.kind') ?? $preview->json('recovery.kind'))->toBe('safety_copy');
    // another system than the one previewed is another action: its confirmation does not fit
    $other = app(DestructivePreview::class)->of($service, 'reinstall', ['image' => 'rocky-9'])['fingerprint'];
    expect($other)->not->toBe($preview->json('data.fingerprint') ?? $preview->json('fingerprint'));
    expect($writes)->toBe([]);
});

it('starts a server that was running again when the import fails, and destroys nothing', function () {
    $vm = [];
    $writes = [];
    reinstallCluster($vm, $writes, 'import failed: storage full');
    [$user, $org] = $this->customerWithOrganization();
    $service = reinstallVps($org);
    $vm = reinstallVmState($service);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'reinstall', $this->contextFor($user, $org, 'webauthn'), 'reinst-fail', ['confirm' => true, 'image' => 'debian-13']));
    expect($operation->state)->toBe(Operation::FAILED)
        ->and($vm['scsi0'])->toBe('local-zfs:vm-1042-disk-0,size=160G') // the old system disk is still the one it boots
        ->and($vm['status'])->toBe('running'); // put back on by the compensation
    expect(collect($writes)->contains(fn (string $w) => str_starts_with($w, 'DELETE') || str_contains($w, '/rollback')))->toBeFalse();
    expect(Backup::query()->where('service_id', $service->id)->where('kind', 'pre_reinstall')->value('protected'))->toBeTrue();
});

it('never replaces the disk of a guest that does not carry the service tag or name', function () {
    $vm = [];
    $writes = [];
    reinstallCluster($vm, $writes);
    [$user, $org] = $this->customerWithOrganization();
    $service = reinstallVps($org);
    $vm = ['name' => 'somebody-else', 'tags' => 'onhost;srv-someone'] + reinstallVmState($service);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'reinstall', $this->contextFor($user, $org, 'webauthn'), 'reinst-stranger', ['confirm' => true, 'image' => 'debian-13']));
    expect($operation->state)->toBe(Operation::FAILED)
        ->and(collect($writes)->contains(fn (string $w) => str_ends_with($w, '/config')))->toBeFalse()
        ->and($vm['scsi0'])->toBe('local-zfs:vm-1042-disk-0,size=160G');
});
