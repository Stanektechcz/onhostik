<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

function pveInstance(): ProviderInstance
{
    $_ENV['PROXMOX_CZ1_TOKEN_ID'] = 'onhost@pve!cp';
    $_ENV['PROXMOX_CZ1_TOKEN_SECRET'] = 'deadbeef-0000';

    return ProviderInstance::query()->firstOrCreate(['key' => 'proxmox-cz1'], [
        'provider' => 'proxmox', 'name' => 'PVE CZ1', 'region_code' => 'cz1', 'base_url' => 'https://pve.mgmt.test:8006',
        'secret_ref' => 'env://PROXMOX_CZ1', 'state' => 'active', 'capabilities' => ['vm.create' => true],
        'options' => ['default_node' => 'prg1-n2', 'storage' => 'local-zfs', 'backup_storage' => 'pbs-cz1', 'templates' => ['debian-13' => 9001], 'template_node' => 'prg1-n2', 'verify_tls' => false],
        'adapter_version' => '1.0.0',
    ]);
}

function pveAdapter(): ProxmoxComputeProvider
{
    $registry = app(ProviderRegistry::class);
    $registry->register('proxmox', ProxmoxComputeProvider::class);

    return $registry->forInstance(pveInstance());
}

it('clones a template, awaits the UPID and reports success only when exitstatus is OK', function () {
    Http::fake([
        'pve.mgmt.test:8006/api2/json/cluster/resources*' => Http::response(['data' => [['type' => 'qemu', 'vmid' => 100, 'node' => 'prg1-n2', 'name' => 'other', 'tags' => 'onhost;srv-x;idem-000000000000', 'status' => 'running']]]),
        'pve.mgmt.test:8006/api2/json/cluster/nextid' => Http::response(['data' => '1042']),
        'pve.mgmt.test:8006/api2/json/nodes/prg1-n2/qemu/9001/clone' => Http::response(['data' => 'UPID:prg1-n2:000A1B2C:0004E1F5:66F0AA11:qmclone:9001:onhost@pve!cp:']),
        'pve.mgmt.test:8006/api2/json/nodes/prg1-n2/tasks/*/status' => Http::sequence()
            ->push(['data' => ['status' => 'running']])
            ->push(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
    $adapter = pveAdapter();
    $spec = new ResourceSpec('srv_01j0test', 'vm', 'ord-1:provision.vps:v1', ['image' => 'debian-13', 'hostname' => 'app-prod', 'vcpu' => 4, 'ram_mb' => 8192], 'prg1-n2', 'cz1');
    $result = $adapter->provision($spec);

    expect($result->completed)->toBeFalse()->and($result->ref?->remoteId)->toBe('1042')->and($result->async?->kind)->toBe('pve_task');
    expect($adapter->awaitStatus($result->async)->state)->toBe(AsyncStatus::RUNNING);
    expect($adapter->awaitStatus($result->async)->state)->toBe(AsyncStatus::SUCCEEDED);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/qemu/9001/clone') && (string) $request['newid'] === '1042' && (string) $request['full'] === '1' && $request->hasHeader('Authorization', 'PVEAPIToken=onhost@pve!cp=deadbeef-0000'));
    expect(DB::table('provider_calls')->where('provider', 'proxmox')->count())->toBeGreaterThanOrEqual(4)
        ->and(DB::table('provider_calls')->where('action', 'qemu.clone')->value('request'))->not->toContain('deadbeef');
});

it('is idempotent: an existing VM tagged with the idempotency key is reused instead of cloned again', function () {
    $tag = ProxmoxComputeProvider::idempotencyTag('ord-1:provision.vps:v1');
    Http::fake([
        'pve.mgmt.test:8006/api2/json/cluster/resources*' => Http::response(['data' => [['type' => 'qemu', 'vmid' => 1042, 'node' => 'prg1-n3', 'name' => 'app-prod', 'tags' => "onhost;srv-01j0test;{$tag}", 'status' => 'running']]]),
    ]);
    $result = pveAdapter()->provision(new ResourceSpec('srv_01j0test', 'vm', 'ord-1:provision.vps:v1', ['image' => 'debian-13']));
    expect($result->completed)->toBeTrue()->and($result->alreadyExisted)->toBeTrue()->and($result->ref->remoteId)->toBe('1042')->and($result->ref->node)->toBe('prg1-n3');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/clone'));
});

it('maps auth failures to AUTH (never retried)', function () {
    Http::fake(['pve.mgmt.test:8006/api2/json/nodes' => Http::response(['data' => null, 'message' => 'authentication failure'], 401)]);
    try {
        pveAdapter()->clusterNodes();
        $this->fail('expected exception');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::AUTH)->and($e->isRetryable())->toBeFalse();
    }
});

it('maps 5xx to TRANSIENT (retried with backoff)', function () {
    Http::fake(['pve.mgmt.test:8006/api2/json/nodes' => Http::response(['data' => null, 'message' => 'internal error'], 500)]);
    try {
        pveAdapter()->clusterNodes();
        $this->fail('expected exception');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::TRANSIENT)->and($e->isRetryable())->toBeTrue();
    }
});

it('reads actual state, detects drift and never treats a customer power change as ONhost drift', function () {
    Http::fake([
        'pve.mgmt.test:8006/api2/json/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'stopped', 'uptime' => 0, 'maxdisk' => 85899345920]]),
        'pve.mgmt.test:8006/api2/json/nodes/prg1-n2/qemu/1042/config' => Http::response(['data' => ['cores' => 2, 'sockets' => 1, 'memory' => 4096, 'scsi0' => 'local-zfs:vm-1042-disk-0,size=80G', 'tags' => 'onhost;srv-01j0test;idem-abc', 'name' => 'app-prod']]),
    ]);
    $adapter = pveAdapter();
    $ref = new ResourceRef('qemu', '1042', 'prg1-n2', [], 'srv_01j0test');
    $actual = $adapter->getActualState($ref);
    expect($actual->exists)->toBeTrue()->and($actual->get('vcpu'))->toBe(2)->and($actual->get('disk_gb'))->toBe(80)->and($actual->status)->toBe('stopped');

    $plan = $adapter->reconcile(new ResourceSpec('srv_01j0test', 'vm', 'k', ['vcpu' => 4, 'ram_mb' => 4096, 'nvme_gb' => 80, 'power_state' => 'running']), $actual);
    $fields = array_column($plan->drifts, 'classification', 'field');
    expect($fields['vcpu'])->toBe('AUTO_REPAIRABLE')->and($fields['power_state'])->toBe('EXPECTED')->and(isset($fields['ram_mb']))->toBeFalse();
});

it('issues a console token that hides the VNC ticket from the browser', function () {
    Http::fake(['pve.mgmt.test:8006/api2/json/nodes/prg1-n2/qemu/1042/vncproxy' => Http::response(['data' => ['port' => '5900', 'ticket' => 'PVEVNC:secret-ticket', 'user' => 'onhost@pve']])]);
    $console = pveAdapter()->consoleAccess(new ResourceRef('qemu', '1042', 'prg1-n2', [], 'srv_01j0test'));
    expect($console['url'])->toContain('/console/ws/con_')->and($console['token'])->toStartWith('con_')->and(json_encode($console))->not->toContain('secret-ticket');
    expect(Cache::get('onhost:console:'.$console['token'])['vncticket'])->toBe('PVEVNC:secret-ticket');
});
