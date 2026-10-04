<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

/*
 * Phase C, C9 — the Proxmox side of an OS reinstall, against Http::fake (never a live cluster). The system disk is
 * replaced with a full copy of a golden template's disk (`import-from`); the old volume is detached, not deleted, so the
 * safety snapshot on it stays. Nothing is written for an image without a template, a guest that is not a template,
 * a VM that does not carry this service's tag or name, or a VM that is still running.
 */

beforeEach(fn () => Http::preventStrayRequests());

function reinstallPveAdapter(): ProxmoxComputeProvider
{
    $registry = app(ProviderRegistry::class);
    $registry->register('proxmox', ProxmoxComputeProvider::class);

    return $registry->forInstance(pveLab());
}

function reinstallPveRef(): ResourceRef
{
    return new ResourceRef('qemu', '1042', 'prg1-n2', ['name' => 'vm-test'], 'srv_01j0reinstall');
}

/**
 * The cluster: template 9001 and VM 1042, every call recorded.
 *
 * @param  array{vm?:array<string,mixed>, template?:array<string,mixed>, status?:string, task?:string}  $o
 * @param  list<array{method:string, path:string, body:array<string,mixed>}>  $calls
 */
function reinstallPveFake(array $o, array &$calls): void
{
    Http::fake(function (Request $request) use ($o, &$calls) {
        $path = substr((string) parse_url($request->url(), PHP_URL_PATH), strlen('/api2/json'));
        $calls[] = ['method' => $request->method(), 'path' => $path, 'body' => $request->method() === 'GET' ? [] : $request->data()];

        return match (true) {
            $path === '/nodes/prg1-n2/qemu/9001/config' => Http::response(['data' => $o['template'] ?? ['template' => 1, 'name' => 'debian-13-golden', 'scsi0' => 'local-zfs:base-9001-disk-0,size=4G']]),
            $path === '/nodes/prg1-n2/qemu/1042/config' && $request->method() === 'GET' => Http::response(['data' => $o['vm'] ?? ['name' => 'vm-test', 'tags' => 'onhost;'.ProxmoxComputeProvider::serviceTag('srv_01j0reinstall'), 'scsi0' => 'local-zfs:vm-1042-disk-0,size=20G', 'boot' => 'order=scsi0;net0']]),
            $path === '/nodes/prg1-n2/qemu/1042/config' && $request->method() === 'POST' => Http::response(['data' => 'UPID:prg1-n2:000B0001:0004E200:66F0BB01:qmconfig:1042:onhost@pve!cp:']),
            $path === '/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => $o['status'] ?? 'stopped', 'maxdisk' => 20 * 1024 ** 3]]),
            $path === '/nodes/prg1-n2/qemu/1042/resize' => Http::response(['data' => 'UPID:prg1-n2:000B0002:0004E201:66F0BB02:resize:1042:onhost@pve!cp:']),
            $path === '/nodes/prg1-n2/qemu/1042/vncproxy' => Http::response(['data' => ['port' => '5901', 'ticket' => 'PVEVNC:relay-only-ticket', 'password' => 'one-time-vnc', 'user' => 'onhost@pve!cp']]),
            str_starts_with($path, '/nodes/prg1-n2/tasks/') => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => $o['task'] ?? 'OK']]),
            default => Http::response(['data' => null], 500, ['X-Unexpected' => $path]),
        };
    });
}

it('replaces the system disk with a full copy of the template and keeps the old volume detached', function () {
    $calls = [];
    reinstallPveFake([], $calls);
    $adapter = reinstallPveAdapter();

    expect($adapter->reinstallImages())->toBe(['debian-13']); // the instance's templates, nothing the caller names

    $result = $adapter->reinstall(reinstallPveRef(), 'debian-13');
    expect($result->completed)->toBeFalse()
        ->and($result->async?->kind)->toBe('pve_task')
        ->and($result->data['replaced_volume'])->toBe('local-zfs:vm-1042-disk-0')
        ->and($result->data['template'])->toBe(9001);
    // one write: the drive set to an import of the template's disk, on the storage the VM's disk is on
    $writes = array_values(array_filter($calls, fn (array $c) => $c['method'] !== 'GET'));
    expect($writes)->toHaveCount(1)
        ->and($writes[0]['path'])->toBe('/nodes/prg1-n2/qemu/1042/config')
        ->and($writes[0]['body'])->toBe(['scsi0' => 'local-zfs:0,import-from=local-zfs:base-9001-disk-0']);
    // nothing deleted, nothing detached by hand: Proxmox moves the replaced volume to unusedN itself
    expect(collect($calls)->contains(fn (array $c) => $c['method'] === 'DELETE' || isset($c['body']['delete'])))->toBeFalse();
    expect($adapter->awaitStatus($result->async)->state)->toBe(AsyncStatus::SUCCEEDED);
});

it('grows the new disk to the plan size and never shrinks it', function () {
    $calls = [];
    reinstallPveFake([], $calls);
    $adapter = reinstallPveAdapter();

    $grown = $adapter->growSystemDisk(reinstallPveRef(), 160);
    expect($grown->async?->kind)->toBe('pve_task');
    $resize = collect($calls)->first(fn (array $c) => str_ends_with($c['path'], '/resize'));
    expect($resize['method'])->toBe('PUT')->and($resize['body'])->toBe(['disk' => 'scsi0', 'size' => '160G']);

    $calls = [];
    $same = $adapter->growSystemDisk(reinstallPveRef(), 20);
    expect($same->completed)->toBeTrue()->and($same->data['grown'])->toBeFalse()
        ->and(collect($calls)->contains(fn (array $c) => str_ends_with($c['path'], '/resize')))->toBeFalse();
});

it('writes nothing for an image without a template, a guest that is no template, a VM that is not this service\'s, or a running VM', function (array $fake, string $image, ProviderErrorCode $code) {
    $calls = [];
    reinstallPveFake($fake, $calls);
    try {
        reinstallPveAdapter()->reinstall(reinstallPveRef(), $image);
        $this->fail('expected a refusal');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe($code);
    }
    expect(collect($calls)->contains(fn (array $c) => $c['method'] !== 'GET'))->toBeFalse();
})->with([
    'image the operator keeps no template for' => [[], 'ubuntu-24.04', ProviderErrorCode::VALIDATION],
    'a path instead of an image name' => [[], '../../etc/passwd', ProviderErrorCode::VALIDATION],
    'the template number is a running machine' => [['template' => ['name' => 'somebody', 'scsi0' => 'local-zfs:vm-9001-disk-0,size=40G']], 'debian-13', ProviderErrorCode::VALIDATION],
    'a guest without the service tag or name' => [['vm' => ['name' => 'stranger', 'tags' => 'onhost;srv-other', 'scsi0' => 'local-zfs:vm-1042-disk-0,size=20G']], 'debian-13', ProviderErrorCode::VALIDATION],
    'a guest locked by a backup' => [['vm' => ['name' => 'vm-test', 'lock' => 'backup', 'scsi0' => 'local-zfs:vm-1042-disk-0,size=20G']], 'debian-13', ProviderErrorCode::TRANSIENT],
    'a VM that is still running' => [['status' => 'running'], 'debian-13', ProviderErrorCode::TRANSIENT],
]);

it('asks Proxmox for a one-time VNC password so the browser never needs the ticket', function () {
    $calls = [];
    reinstallPveFake([], $calls);
    $console = reinstallPveAdapter()->consoleAccess(reinstallPveRef());

    $proxy = collect($calls)->first(fn (array $c) => str_ends_with($c['path'], '/vncproxy'));
    expect((string) $proxy['body']['generate-password'])->toBe('1')->and((string) $proxy['body']['websocket'])->toBe('1');
    expect($console['password'])->toBe('one-time-vnc')
        ->and(json_encode($console))->not->toContain('relay-only-ticket');
    // the ticket stays with the descriptor the relay resolves server-side
    expect(Cache::get('onhost:console:'.$console['token'])['vncticket'])->toBe('PVEVNC:relay-only-ticket');
});
