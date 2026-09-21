<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\RescueMode;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Support\Assistant\AssistantProposals;

/*
 * Booting a server into a rescue system (Brain card H233, audit §5af). A VPS whose own system will not start was
 * unreachable for its owner: the panel offered no way to boot anything else, so every broken server was a ticket and
 * a hand-made change in the hypervisor. The rules the card asks for are the ones that matter here: only an image the
 * operator put on the node, a window that ends by itself, and exactly what was found going back — never a guess at a
 * default boot order.
 */

const PVEAPI = 'https://pve.mgmt.test:8006/api2/json';

beforeEach(fn () => Http::preventStrayRequests());

/** A delivered VPS on the Proxmox lab cluster. */
function rescueVps(object $org): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-test.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-test'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "rescue:{$service->id}", 'adapter_version' => '1.0.0']);

    return $service;
}

/** The cluster answers with two rescue images and a VM that boots from its own disk. @param array{boot:string, ide2?:string} $config */
function rescuePve(array &$config): void
{
    Http::fake(function (Request $request) use (&$config) {
        $url = $request->url();
        if (! str_starts_with($url, PVEAPI)) {
            return null;
        }
        if (str_contains($url, '/storage/local/content')) {
            return Http::response(['data' => [
                ['volid' => 'local:iso/systemrescue-11.iso', 'size' => 900 * 1024 * 1024],
                ['volid' => 'local:iso/debian-13-netinst.iso', 'size' => 700 * 1024 * 1024],
            ]]);
        }
        if (str_ends_with($url, '/qemu/1042/config') && $request->method() === 'GET') {
            return Http::response(['data' => array_merge(['name' => 'vm-test', 'cores' => 4, 'sockets' => 1, 'memory' => 8192, 'scsi0' => 'local-zfs:vm-1042-disk-0,size=160G'], $config)]);
        }
        if (str_ends_with($url, '/qemu/1042/config') && $request->method() === 'PUT') {
            $config = array_merge($config, array_filter(['boot' => $request['boot'] ?? null, 'ide2' => $request['ide2'] ?? null], fn ($v) => $v !== null));

            return Http::response(['data' => null]);
        }
        if (str_contains($url, '/status/reboot')) {
            return Http::response(['data' => 'UPID:prg1-n2:000A1B35:0004E1FD:66F0AA19:qmreboot:1042:onhost@pve!cp:']);
        }
        if (str_contains($url, '/tasks/') || str_contains($url, '/status/current')) {
            return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]);
        }

        return Http::response(['data' => []]);
    });
}

it('boots a server from an image the operator put there, and puts back exactly what it found', function () {
    $config = ['boot' => 'order=scsi0;net0'];
    rescuePve($config);
    [$user, $org] = $this->customerWithOrganization();
    $service = rescueVps($org);
    $services = app(ServiceService::class);

    $started = driveOperation($services->requestAction($service, 'rescue.start', $this->contextFor($user, $org, 'webauthn'), 'resc-1', ['image' => 'local:iso/systemrescue-11.iso']));
    expect($started->state)->toBe('SUCCEEDED', (string) data_get($started->error, 'message', ''));
    // the hypervisor really boots the image first, and the disk stays behind it
    expect($config['ide2'])->toBe('local:iso/systemrescue-11.iso,media=cdrom')->and($config['boot'])->toBe('order=ide2;scsi0;net0');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/status/reboot'));

    $session = RescueMode::session($service->fresh());
    expect($session['iso'])->toBe('local:iso/systemrescue-11.iso')
        ->and($session['previous'])->toMatchArray(['iso' => null, 'boot' => 'order=scsi0;net0']) // what was found, written down
        ->and($session['until'])->not->toBeNull();

    $stopped = driveOperation($services->requestAction($service->fresh(), 'rescue.stop', $this->contextFor($user, $org), 'resc-2'));
    expect($stopped->state)->toBe('SUCCEEDED')
        ->and($config['boot'])->toBe('order=scsi0;net0') // exactly the order it had, not a default
        ->and($config['ide2'])->toBe('none,media=cdrom')
        ->and(RescueMode::session($service->fresh()))->toBeNull();
});

it('boots nothing the node does not offer, and asks for a fresh step-up before it boots anything', function () {
    $config = ['boot' => 'order=scsi0'];
    rescuePve($config);
    [$user, $org] = $this->customerWithOrganization();
    $service = rescueVps($org);
    $services = app(ServiceService::class);

    // an image of the customer's own choosing is not an image (H233)
    $refused = driveOperation($services->requestAction($service, 'rescue.start', $this->contextFor($user, $org, 'webauthn'), 'resc-bad-1', ['image' => 'local:iso/../../etc/passwd']));
    expect($refused->state)->toBe('FAILED')->and((string) data_get($refused->error, 'message', ''))->toContain('záchranný obraz');
    expect($config)->not->toHaveKey('ide2')->and(RescueMode::session($service->fresh()))->toBeNull();

    // booting a rescue system is root on the customer's disks: the command bus asks for a fresh step-up before it runs,
    // and ending one never does — getting out has to be possible even when nobody can reach a second factor
    expect((new ServiceActionCommand($org->id, 'k', ['action' => 'rescue.start']))->requiresStepUp())->toBeTrue()
        ->and((new ServiceActionCommand($org->id, 'k', ['action' => 'rescue.start']))->riskLevel())->toBe(PermissionCatalog::HIGH)
        ->and((new ServiceActionCommand($org->id, 'k', ['action' => 'rescue.stop']))->requiresStepUp())->toBeFalse();
    $this->actingAs($user, 'sanctum');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'rescue.start', 'params' => []], ['X-Organization' => $org->id, 'Idempotency-Key' => 'resc-http-1'])
        ->assertStatus(403)->assertJsonPath('error', 'step_up_required');
});

it('ends a session that outlived its window and puts the server back without anybody asking', function () {
    $config = ['boot' => 'order=scsi0'];
    rescuePve($config);
    [$user, $org] = $this->customerWithOrganization();
    $service = rescueVps($org);

    app(RescueMode::class)->start($service, $this->contextFor($user, $org, 'webauthn'), null, 2); // the first image, two hours
    expect($config['ide2'])->toBe('local:iso/debian-13-netinst.iso,media=cdrom'); // sorted by name: the list is the node's, not a guess

    $this->travel(3)->hours();
    Artisan::call('onhost:services:rescue-expire');
    expect(Artisan::output())->toContain('put back: 1')
        ->and($config['boot'])->toBe('order=scsi0')->and($config['ide2'])->toBe('none,media=cdrom')
        ->and(RescueMode::session($service->fresh()))->toBeNull();
});

it('keeps the boot order sane whatever it finds, and tells the panel what a session is', function () {
    expect(RescueMode::bootFirst('order=scsi0;net0'))->toBe('order=ide2;scsi0;net0')
        ->and(RescueMode::bootFirst('order=ide2;scsi0'))->toBe('order=ide2;scsi0') // already first: not twice
        ->and(RescueMode::bootFirst(''))->toBe('order=ide2;scsi0')                 // a VM with no order at all
        ->and(RescueMode::hours())->toBeGreaterThan(0);

    $config = ['boot' => 'order=scsi0'];
    rescuePve($config);
    [, $org] = $this->customerWithOrganization();
    $service = rescueVps($org);
    $feature = app(ServiceFeatures::class)->features($service)['vm_rescue'] ?? [];
    expect($feature['enabled'] ?? false)->toBeTrue()->and(array_key_exists('session', (array) ($feature['options'] ?? [])))->toBeTrue()->and($feature['options']['session'])->toBeNull()
        ->and($feature['options']['hours'] ?? 0)->toBe(RescueMode::hours());

    // the assistant never proposes it: what asks for a fresh step-up is outside its list
    expect(AssistantProposals::ACTIONS)->not->toHaveKey('rescue.start');
});
