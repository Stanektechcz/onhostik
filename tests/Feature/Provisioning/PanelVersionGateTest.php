<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\VendorVersion;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * A panel is upgraded — by its vendor's updater, by an operator, by an unattended upgrade — and the platform goes on
 * calling it as before (Brain cards H511–H530). Every adapter declares the versions it was verified against
 * (`supportedVendorVersions()`); nothing read that list. The health probe wrote the version the panel reports onto the
 * instance every minute and nobody compared it: a panel on a version nobody had checked kept getting new orders.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/**
 * Proxmox that answers with `$pve['version']`; its backup storage refuses the API user unless `backups_ok`; VM 1042 is
 * called `vm_name` when given.
 *
 * @param  array{version:string, backups_ok:bool, vm_name?:string}  $pve
 */
function versionPve(array &$pve): void
{
    Http::fake(function (Request $r) use (&$pve) {
        $path = substr((string) parse_url($r->url(), PHP_URL_PATH), strlen('/api2/json'));

        return match (true) {
            isset($pve['vm_name']) && $path === '/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running', 'uptime' => 3600]]),
            isset($pve['vm_name']) && $path === '/nodes/prg1-n2/qemu/1042/config' => Http::response(pveVmConfig(['name' => $pve['vm_name']])),
            $path === '/version' => Http::response(['data' => ['version' => $pve['version'], 'release' => substr($pve['version'], 0, 3)]]),
            $path === '/cluster/status' => Http::response(['data' => [['type' => 'cluster', 'name' => 'cz1', 'quorate' => 1]]]),
            $path === '/nodes/prg1-n2/storage/pbs-cz1/content' => $pve['backups_ok'] ? Http::response(['data' => []]) : Http::response(['data' => null, 'errors' => ['storage' => 'Permission check failed (/storage/pbs-cz1, Datastore.Audit)']], 403),
            default => null,
        };
    });
}

/** A customer's VPS on the instance: the platform has been working with this panel already. */
function versionLiveService(ProviderInstance $instance): void
{
    [, $org] = test()->customerWithOrganization();
    Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'provider_instance_id' => $instance->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => [], 'sla_class' => 'standard', 'tags' => []]);
}

it('takes no new orders to a panel upgraded to a version nobody verified, until an operator accepts it on passing checks', function () {
    $instance = pveLab();
    versionLiveService($instance);
    $pve = ['version' => '8.2.4', 'backups_ok' => true];
    versionPve($pve);
    $probe = app(IntegrationHealthProbe::class);

    $probe->probeInstance($instance);
    expect($instance->fresh()->isUsable())->toBeTrue();

    $pve['version'] = '9.4.1'; // the adapter declares 8.2 … 9.2
    $probe->probeInstance($instance->fresh());

    // it used to be: the version was written down and nothing else happened — the next VPS order went to the upgraded cluster
    expect($instance->fresh()->isUsable())->toBeFalse()
        ->and(data_get($instance->fresh()->version_gate, 'state'))->toBe('held')
        ->and(data_get($instance->fresh()->version_gate, 'previous'))->toBe('8.2.4')
        ->and(OutboxMessage::query()->where('name', 'integration.version.held')->exists())->toBeTrue();
    expect($instance->fresh()->state)->toBe('active'); // the services already there keep being managed

    // accepted only with a reason and on checks that pass
    $this->artisan('onhost:integrations:versions', ['--accept' => 'proxmox-cz1', '--reason' => 'ok'])->assertFailed();
    $pve['backups_ok'] = false;
    $this->artisan('onhost:integrations:versions', ['--accept' => 'proxmox-cz1', '--reason' => 'ověřeno na laboratorním clusteru 9.4'])->assertFailed();
    expect($instance->fresh()->isUsable())->toBeFalse();
    $pve['backups_ok'] = true;
    $this->artisan('onhost:integrations:versions', ['--accept' => 'proxmox-cz1', '--reason' => 'ověřeno na laboratorním clusteru 9.4'])->assertSuccessful();

    expect($instance->fresh()->isUsable())->toBeTrue()
        ->and(data_get($instance->fresh()->version_gate, 'state'))->toBe('accepted')
        ->and(data_get($instance->fresh()->version_gate, 'reason'))->toBe('ověřeno na laboratorním clusteru 9.4')
        ->and(AuditEvent::query()->where('action', 'provider.instance.version.accepted')->exists())->toBeTrue();
});

it('holds a declared version whose checks fail after the upgrade, and lets it go once they pass', function () {
    $instance = pveLab();
    versionLiveService($instance);
    $pve = ['version' => '8.2.4', 'backups_ok' => true];
    versionPve($pve);
    $probe = app(IntegrationHealthProbe::class);
    $probe->probeInstance($instance);

    $pve = ['version' => '8.3.2', 'backups_ok' => false]; // declared — but the upgrade reset the API user's rights on the backup storage
    $probe->probeInstance($instance->fresh());
    expect($instance->fresh()->isUsable())->toBeFalse()
        ->and((string) data_get($instance->fresh()->version_gate, 'why'))->toContain('backup_storage');

    $pve['backups_ok'] = true;
    $probe->probeInstance($instance->fresh());
    expect($instance->fresh()->isUsable())->toBeFalse(); // not re-checked on every probe of the minute

    $this->travel(16)->minutes();
    $probe->probeInstance($instance->fresh());
    expect($instance->fresh()->isUsable())->toBeTrue()
        ->and(data_get($instance->fresh()->version_gate, 'state'))->toBe('verified')
        ->and(OutboxMessage::query()->where('name', 'integration.version.verified')->exists())->toBeTrue();
});

it('does not take an upgraded panel\'s resources for the same ones by their numbers alone', function () {
    $instance = pveLab();
    [, $org] = $this->customerWithOrganization();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-test', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'provider_instance_id' => $instance->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => [], 'sla_class' => 'standard', 'tags' => []]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-test'], 'ownership' => [], 'idempotency_key' => "identity:{$service->id}", 'adapter_version' => '1.0.0']);
    $pve = ['version' => '8.2.4', 'backups_ok' => true, 'vm_name' => 'vm-test'];
    versionPve($pve);
    $probe = app(IntegrationHealthProbe::class);
    $probe->probeInstance($instance);

    $pve['version'] = '8.3.2';        // declared, and every probe answers …
    $pve['vm_name'] = 'jiny-stroj';   // … but VM 1042 is another machine after the upgrade
    $probe->probeInstance($instance->fresh());

    // it used to be: declared + checks passing = verified, and the next action on "1042" went to a stranger's machine
    expect($instance->fresh()->isUsable())->toBeFalse()
        ->and((string) data_get($instance->fresh()->version_gate, 'why'))->toContain("identity of {$service->id}: name");

    $pve['vm_name'] = 'vm-test';
    $this->travel(16)->minutes();
    $probe->probeInstance($instance->fresh());
    expect(data_get($instance->fresh()->version_gate, 'state'))->toBe('verified');
});

it('does not put a new panel on an undeclared version into the offer, but takes a working one as its baseline', function () {
    // a new instance with nothing on it yet: an undeclared version is not an offer
    $instance = pveLab();
    $pve = ['version' => '9.4.1', 'backups_ok' => true];
    versionPve($pve);
    app(IntegrationHealthProbe::class)->probeInstance($instance);
    expect($instance->fresh()->isUsable())->toBeFalse();

    // the first look at a panel that already carries customers is its baseline: nothing stops, the doctor says what it is
    $instance->forceFill(['version_gate' => null])->save();
    versionLiveService($instance);
    app(IntegrationHealthProbe::class)->probeInstance($instance->fresh());
    expect($instance->fresh()->isUsable())->toBeTrue()
        ->and(data_get($instance->fresh()->version_gate, 'state'))->toBe('baseline');
    $this->artisan('onhost:doctor')->expectsOutputToContain('proxmox-cz1 9.4.1');
});

it('names the tasks still running at the panel before it goes into maintenance for an upgrade', function () {
    $instance = pveLab();
    [, $org] = $this->customerWithOrganization();
    $clone = Operation::query()->create(['organization_id' => $org->id, 'provider_instance_id' => $instance->id, 'kind' => 'provision.vps', 'workflow' => 'provision.vps', 'queue' => 'provider-proxmox', 'idempotency_key' => 'upgrade-inflight',
        'state' => Operation::WAITING, 'attempts' => 1, 'queued_at' => now()->subMinutes(3), 'started_at' => now()->subMinutes(3), 'step_label' => 'Klon šablony',
        'external_handle' => ['kind' => 'pve_task', 'handle' => 'UPID:prg1-n2:000A1B2C:0004E1F5:66F0AA11:qmclone:9001:onhost@pve!cp:', 'node' => 'prg1-n2', 'meta' => [], 'poll_interval_seconds' => 5, 'timeout_seconds' => 1800]]);
    $staff = $this->staff('infrastructure_admin');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1'); // taking a panel down is a high-risk action
    $this->actingAs($staff, 'sanctum');
    $maintenance = ['state' => 'maintenance', 'reason' => 'upgrade to 8.3', 'maintenance_until' => now()->addHour()->toIso8601String()];

    // it used to be: the instance went into maintenance and the clone at the panel was cut off unseen
    $this->postJson('/v1/staff/integrations/proxmox-cz1/state', $maintenance)->assertStatus(409)
        ->assertJsonPath('error', 'instance_tasks_running')->assertJsonPath('tasks.0.id', $clone->id)->assertJsonPath('tasks.0.step', 'Klon šablony');
    expect($instance->fresh()->state)->toBe('active');

    $this->postJson('/v1/staff/integrations/proxmox-cz1/state', $maintenance + ['acknowledge_running' => true])->assertOk();
    expect($instance->fresh()->state)->toBe('maintenance')
        ->and(AuditEvent::query()->where('action', 'provider.instance.state')->value('detail'))->toMatchArray(['tasks_left_running' => [$clone->id]]); // who left what running stays on record
});

it('reads the version ISPConfig runs', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    Http::fake([
        ISP.'/remote/json.php?login' => Http::response(['code' => 'ok', 'message' => '', 'response' => 'sess-version']),
        ISP.'/remote/json.php?sites_web_domain_get' => Http::response(['code' => 'ok', 'message' => '', 'response' => []]),
        ISP.'/remote/json.php?monitor_jobqueue_count' => Http::response(['code' => 'ok', 'message' => '', 'response' => 0]),
        ISP.'/remote/json.php?server_get' => Http::response(['code' => 'ok', 'message' => '', 'response' => ['hostname' => 'shared01']]),
        ISP.'/remote/json.php?server_get_app_version' => Http::response(['code' => 'ok', 'message' => '', 'response' => ['ispc_app_version' => '3.2.11p2', 'ispc_db_version' => 'dev']]),
    ]);

    // it used to be null: an ISPConfig upgrade could not even be noticed
    expect(app(ProviderRegistry::class)->forInstance(ProviderInstance::query()->findOrFail($service->provider_instance_id))->health()->version)->toBe('3.2.11p2');
});

it('matches a reported version against what an adapter declares', function (string $reported, array $declared, bool $expected) {
    expect(VendorVersion::declared($reported, $declared))->toBe($expected);
})->with([
    'Proxmox patch release' => ['8.2.4', ['8.2', '8.3'], true],
    'Proxmox 8.20 is not 8.2' => ['8.20.1', ['8.2'], false],
    'aaPanel wildcard' => ['7.0.3', ['7.0.x', '7.1.x'], true],
    'aaPanel 7.10 is not 7.1.x' => ['7.10.2', ['7.1.x'], false],
    'ISPConfig patch release' => ['3.2.11p2', ['3.2.11', '3.2.12'], true],
    'ISPConfig 3.2.1 is not 3.2.11' => ['3.2.1', ['3.2.11'], false],
    'RKE2 distribution suffix' => ['v1.33.5+rke2r1', ['v1.33+rke2'], true],
    'k3s is not RKE2' => ['v1.33.5+k3s1', ['v1.33+rke2'], false],
    'exact' => ['4.8', ['4.8'], true],
]);
