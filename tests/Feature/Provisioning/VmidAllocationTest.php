<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\VmidReservations;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceIdentityCheck;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

/*
 * The number of a new VM (Brain cards H488, H505). Proxmox's `/cluster/nextid` hands out the LOWEST free number, so the
 * number of a VPS that was just cancelled and purged went straight to the next order:
 *  - the cancelled service's binding still holds that number (bindings are history and are never deleted, and the
 *    triple instance/type/number is unique), so the new clone was made, its binding failed, the order failed and the
 *    new VM stayed on the node bound to nothing;
 *  - and had it been bound, its backups would have landed in the same group `vm/<number>` of the backup server as the
 *    predecessor's protected final archive.
 */

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    pveLab();
    Http::preventStrayRequests();
});

/**
 * A Proxmox cluster that behaves like one where numbers are concerned: `/cluster/nextid` gives the LOWEST free number at or
 * above the cluster's floor (or confirms / refuses the number it is asked about), a clone takes its number the moment it
 * is accepted, the backup storage lists what it holds, and a refusal says why in the HTTP status line — as Proxmox does.
 *
 * @param  array{guests: array<int, array<string,mixed>>, backups: list<int>, lower: int, clones: list<int>, taken_at_clone?: list<int>, lose_answer?: list<int>}  $pve
 */
function vmidPve(array &$pve): void
{
    Http::fake(function (Request $r) use (&$pve) {
        $path = substr(rawurldecode((string) parse_url($r->url(), PHP_URL_PATH)), strlen('/api2/json'));
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);
        $method = $r->method();

        if ($path === '/cluster/resources') {
            $rows = [];
            foreach ($pve['guests'] as $id => $guest) { // a clone still copying its disk has a temporary config: no name yet
                $rows[] = ['type' => $guest['type'] ?? 'qemu', 'vmid' => $id, 'node' => 'prg1-n2', 'status' => $guest['status'] ?? 'stopped', 'template' => $guest['template'] ?? 0]
                    + (($guest['pending'] ?? false) ? [] : ['name' => $guest['name'] ?? "vm-{$id}", 'tags' => $guest['tags'] ?? '']);
            }

            return Http::response(['data' => $rows]);
        }
        if ($path === '/cluster/nextid') {
            if (isset($query['vmid'])) {
                $id = (int) $query['vmid'];

                return isset($pve['guests'][$id]) ? Http::response(['errors' => ['vmid' => "VM {$id} already exists"], 'data' => null], 400) : Http::response(['data' => (string) $id]);
            }
            for ($id = $pve['lower']; isset($pve['guests'][$id]); $id++);

            return Http::response(['data' => (string) $id]);
        }
        if ($path === '/nodes/prg1-n2/storage/pbs-cz1/content') {
            return Http::response(['data' => array_map(fn (int $id) => ['volid' => "pbs-cz1:backup/vm/{$id}/2026-07-01T02:00:00Z", 'vmid' => $id, 'format' => 'pbs-vm', 'content' => 'backup', 'size' => 21474836480, 'protected' => 1], $pve['backups'])]);
        }
        if ($path === '/nodes/prg1-n2/qemu/9001/clone' && $method === 'POST') {
            $id = (int) $r['newid'];
            $pve['clones'][] = $id;
            if (isset($pve['guests'][$id]) || in_array($id, $pve['taken_at_clone'] ?? [], true)) {
                $pve['guests'][$id] ??= ['name' => 'rucne-zalozena', 'description' => 'made by hand']; // somebody took the number a moment before

                return Create::promiseFor(new Psr7Response(500, ['Content-Type' => 'application/json'], '{"data":null}', '1.1', "unable to create VM {$id}: config file already exists"));
            }
            $lost = in_array($id, $pve['lose_answer'] ?? [], true);
            $pve['guests'][$id] = ['name' => (string) $r['name'], 'description' => (string) $r['description'], 'status' => 'stopped', 'pending' => $lost];
            if ($lost) { // the clone was accepted — and the answer never came back
                throw new ConnectionException('cURL error 28: Operation timed out after 15001 milliseconds');
            }

            return Http::response(['data' => 'UPID:prg1-n2:000A1B2C:0004E1F5:66F0AA11:qmclone:9001:onhost@pve!cp:']);
        }
        if (str_contains($path, '/tasks/')) {
            return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]);
        }
        if (preg_match('~^/nodes/prg1-n2/qemu/(\d+)/(config|resize|cloudinit|firewall/rules|firewall/options|status/current|status/start)$~', $path, $m) === 1) {
            $id = (int) $m[1];
            if (! isset($pve['guests'][$id])) {
                return Create::promiseFor(new Psr7Response(500, ['Content-Type' => 'application/json'], '{"data":null}', '1.1', "Configuration file 'nodes/prg1-n2/qemu-server/{$id}.conf' does not exist"));
            }
            $guest = $pve['guests'][$id];
            if ($m[2] === 'config' && $method === 'GET' && ($guest['pending'] ?? false)) {
                $pve['guests'][$id]['pending'] = false; // the disk is copied by the next look

                return Http::response(['data' => ['lock' => 'clone', 'digest' => 'e3b0c44298fc']]);
            }
            if ($m[2] === 'config' && $method === 'PUT' && isset($r->data()['tags'])) {
                $pve['guests'][$id]['tags'] = (string) $r->data()['tags'];
            }
            if ($m[2] === 'status/start') {
                $pve['guests'][$id]['status'] = 'running';
            }

            return match ($m[2]) {
                'config' => Http::response($method === 'GET' ? pveVmConfig(['name' => $guest['name'], 'description' => $guest['description'] ?? '', 'tags' => $guest['tags'] ?? '']) : ['data' => null]),
                'resize' => Http::response(['data' => "UPID:prg1-n2:000A1B2D:0004E1F6:66F0AA12:resize:{$id}:onhost@pve!cp:"]),
                'status/start' => Http::response(['data' => "UPID:prg1-n2:000A1B2E:0004E1F7:66F0AA13:qmstart:{$id}:onhost@pve!cp:"]),
                'status/current' => Http::response(['data' => ['status' => $pve['guests'][$id]['status'] ?? 'stopped', 'uptime' => 7]]),
                'firewall/rules' => Http::response(['data' => $method === 'GET' ? [] : null]),
                default => Http::response(['data' => null]),
            };
        }

        return null;
    });
}

/** Two guests of other customers, the golden template, and the cluster's own floor at 1040. */
function vmidCluster(array $extra = []): array
{
    return array_replace(['guests' => [1040 => ['name' => 'jiny-zakaznik-1', 'status' => 'running'], 1041 => ['name' => 'jiny-zakaznik-2', 'status' => 'running'], 9001 => ['name' => 'debian-13-golden', 'template' => 1]],
        'backups' => [], 'lower' => 1040, 'clones' => []], $extra);
}

/** A VPS ordered and driven to the end. @return array{0:Service,1:Operation} */
function vmidOrder(Organization $org, CommandContext $ctx): array
{
    $product = Product::query()->where('key', 'vps')->firstOrFail();
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'compute-4'))->firstOrFail();
    $service = app(ServiceService::class)->create($org, $product, $version, ['ssh_keys' => ['ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExample test@onhost'], 'options' => ['ipv4' => true]], $ctx);

    return [$service, driveOperation(Operation::query()->where('service_id', $service->id)->firstOrFail())];
}

function vmidAdapter(): ProxmoxComputeProvider
{
    $adapter = app(ProviderRegistry::class)->forInstance(pveLab());
    assert($adapter instanceof ProxmoxComputeProvider);

    return $adapter;
}

it('does not give the number of a cancelled VPS to the next order', function () {
    [$user, $org] = $this->customerWithOrganization();
    // a VPS of this platform, cancelled and purged: the VM is gone, its binding stays as history, its final archive on the backup server
    $gone = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::TERMINATED,
        'region_code' => 'cz1', 'provider_instance_id' => pveLab()->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => [], 'sla_class' => 'standard', 'tags' => []]);
    ProviderBinding::query()->create(['service_id' => $gone->id, 'provider_instance_id' => pveLab()->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => [], 'idempotency_key' => "provision:{$gone->id}:qemu", 'adapter_version' => '1.0.0']);
    $pve = vmidCluster(['backups' => [1042]]);
    vmidPve($pve);

    [$service, $operation] = vmidOrder($org, $this->contextFor($user, $org));

    // it used to be: Proxmox said 1042, the clone into 1042 was made, the binding hit the cancelled service's one, the order failed — and VM 1042 stayed on the node, bound to nothing
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
    expect(ProviderBinding::query()->where('service_id', $service->id)->value('remote_id'))->toBe('1043')
        ->and($pve['clones'])->toBe([1043])
        ->and(ProviderBinding::query()->where('service_id', $gone->id)->value('remote_id'))->toBe('1042'); // history stays what it was
});

it('skips a number whose backup group still holds backups, even one this platform never gave', function () {
    // 1042 and 1043 were machines made by hand and deleted since; their backups are still on the backup server
    $pve = vmidCluster(['backups' => [1042, 1043]]);
    vmidPve($pve);

    expect(vmidAdapter()->reserveVmid())->toBe(1044) // Proxmox itself would have said 1042
        ->and(vmidAdapter()->reserveVmid(2000))->toBe(2000);
});

it('never gives two orders the same number, even while Proxmox still calls it free', function () {
    $instance = pveLab();
    $pve = vmidCluster();
    vmidPve($pve);
    $numbers = app(VmidReservations::class);

    // neither order has cloned yet, so Proxmox calls 1042 free both times — a parallel order used to clone into the same number and fail
    $first = $numbers->hold($instance, vmidAdapter(), 'svc_first', 'op_first');
    $second = $numbers->hold($instance, vmidAdapter(), 'svc_second', 'op_second');

    expect($first)->toBe(1042)->and($second)->toBe(1043)
        ->and($numbers->hold($instance, vmidAdapter(), 'svc_first', 'op_first'))->toBe(1042); // asked again, an order keeps the number it holds
});

it('takes the next number when somebody took its own a moment before the clone', function () {
    [$user, $org] = $this->customerWithOrganization();
    $pve = vmidCluster(['taken_at_clone' => [1042]]); // free when asked, made by hand before our clone arrived
    vmidPve($pve);

    [$service, $operation] = vmidOrder($org, $this->contextFor($user, $org));

    // Proxmox names the reason in its status line; read, "already exists" says the number is somebody else's — the order moves on to the next one
    expect($operation->state)->toBe(Operation::SUCCEEDED)
        ->and($pve['clones'])->toBe([1042, 1043])
        ->and(ProviderBinding::query()->where('service_id', $service->id)->value('remote_id'))->toBe('1043')
        ->and($pve['guests'][1042]['name'])->toBe('rucne-zalozena'); // somebody else's machine, untouched
    expect(DB::table('vmid_reservations')->where('vmid', 1042)->value('burned_at'))->not->toBeNull(); // never tried again
});

it('makes no second VM when the answer to a clone is lost while its disk is still being copied', function () {
    [$user, $org] = $this->customerWithOrganization();
    $pve = vmidCluster(['lose_answer' => [1042]]);
    vmidPve($pve);

    [$service, $operation] = vmidOrder($org, $this->contextFor($user, $org));

    // it used to be: the clone into 1042 had no name yet, so the retry did not recognise it and cloned a second VM into 1043
    expect($pve['clones'])->toBe([1042])
        ->and($operation->state)->toBe(Operation::SUCCEEDED)
        ->and(ProviderBinding::query()->where('service_id', $service->id)->value('remote_id'))->toBe('1042');
});

it('tells a VM that is gone from one that HA moved to another node', function () {
    // Proxmox has no 404 for a guest: asked on a node that does not hold it, it says that its configuration file does not exist
    Http::fake(function (Request $r) {
        $path = substr((string) parse_url($r->url(), PHP_URL_PATH), strlen('/api2/json'));

        return match (true) {
            $path === '/cluster/resources' => Http::response(['data' => [['type' => 'qemu', 'vmid' => 1043, 'node' => 'prg1-n3', 'name' => 'moved-vm', 'status' => 'running']]]),
            str_starts_with($path, '/nodes/prg1-n2/qemu/') => Create::promiseFor(new Psr7Response(500, ['Content-Type' => 'application/json'], '{"data":null}', '1.1', "Configuration file 'nodes/prg1-n2/qemu-server/".explode('/', $path)[4].".conf' does not exist")),
            $path === '/nodes/prg1-n3/qemu/1043/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 99]]),
            $path === '/nodes/prg1-n3/qemu/1043/config' => Http::response(pveVmConfig(['name' => 'moved-vm'])),
            default => null,
        };
    });
    $adapter = vmidAdapter();

    $gone = $adapter->getActualState(new ResourceRef('qemu', '1042', 'prg1-n2'));
    $moved = $adapter->getActualState(new ResourceRef('qemu', '1043', 'prg1-n2'));

    // it used to be "server error" for both: the cancellation of a VM deleted by hand retried until it gave up
    expect($gone->exists)->toBeFalse()
        ->and($moved->exists)->toBeTrue()->and($moved->status)->toBe('running')->and($moved->get('node'))->toBe('prg1-n3');

    // and a moved VM is not deleted on the strength of a binding that still names its old node
    [, $org] = $this->customerWithOrganization();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'moved-vm', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => pveLab()->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => [], 'sla_class' => 'standard', 'tags' => []]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => pveLab()->id, 'remote_type' => 'qemu', 'remote_id' => '1043', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'moved-vm'], 'ownership' => [], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);
    $report = app(ServiceIdentityCheck::class)->verify($service, $adapter);
    expect($report['ok'])->toBeFalse()->and($report['missing'])->toBeFalse()->and($report['failed'])->toContain('node');
});

it('reads why Proxmox refused, and waits out a lock instead of failing', function () {
    Http::fake(fn (Request $r) => str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/status/start')
        ? Create::promiseFor(new Psr7Response(500, ['Content-Type' => 'application/json'], '{"data":null}', '1.1', 'VM 1042 is locked (backup)'))
        : null);

    try {
        vmidAdapter()->power(new ResourceRef('qemu', '1042', 'prg1-n2'), 'start');
        $this->fail('a refused start must throw');
    } catch (ProviderException $e) {
        // it used to be "server error" — Proxmox says why in the status line, not in the body
        expect($e->getMessage())->toContain('VM 1042 is locked (backup)')
            ->and($e->errorCode)->toBe(ProviderErrorCode::TRANSIENT); // a backup ends; the action is tried again, not failed for good
    }
});
