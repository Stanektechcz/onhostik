<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;

/*
 * What a destructive action replaces is kept first (audit §5ag, the owner's standing rule: nothing is deleted,
 * cleared or otherwise disturbed before it has been backed up). A restore writes a backup over the service, a
 * rollback throws away everything since the snapshot, a game reinstall rewrites the server's files — and none of
 * them kept a copy of what they destroyed. The customer who rolled back a day too far had no way back: the panel
 * only told them to take a backup themselves.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** A delivered VPS on the Proxmox lab cluster, with one snapshot to roll back to. */
function keepCopyVps(object $org): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-test.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'snapshots' => 5], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-test'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "safety:{$service->id}", 'adapter_version' => '1.0.0']);

    return $service;
}

it('snapshots the server before it throws away everything since the last one', function () {
    $calls = [];
    Http::fake(function (Request $request) use (&$calls) {
        $url = $request->url();
        if (! str_starts_with($url, 'https://pve.mgmt.test:8006')) {
            return null;
        }
        if (str_contains($url, '/snapshot/vcerejsi/rollback')) {
            $calls[] = 'rollback';

            return Http::response(['data' => 'UPID:prg1-n2:00000001:00000001:66F0AA19:qmrollback:1042:onhost@pve!cp:']);
        }
        if (str_ends_with($url, '/qemu/1042/snapshot') && $request->method() === 'POST') {
            $calls[] = 'snapshot:'.$request['snapname'];

            return Http::response(['data' => 'UPID:prg1-n2:00000002:00000002:66F0AA19:qmsnapshot:1042:onhost@pve!cp:']);
        }
        if (str_contains($url, '/tasks/')) {
            return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]);
        }

        return Http::response(['data' => []]);
    });
    [$user, $org] = $this->customerWithOrganization();
    $service = keepCopyVps($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'rollback_snapshot', $this->contextFor($user, $org, 'webauthn'), 'safety-roll-1', ['name' => 'vcerejsi']));
    expect($operation->state)->toBe('SUCCEEDED', (string) data_get($operation->error, 'message', ''));

    // the order is the whole point: against the old code the only call was the rollback
    expect($calls)->toHaveCount(2)->and($calls[1])->toBe('rollback')->and($calls[0])->toStartWith('snapshot:onhost-pre-rollback-');
    $safety = Backup::query()->where('service_id', $service->id)->where('kind', 'pre_rollback')->sole();
    expect($safety->protected)->toBeTrue()->and($safety->state)->toBe('completed')->and($safety->remote_id)->toStartWith('onhost-pre-rollback-')
        ->and((int) now()->diffInDays($safety->retention_until))->toBeGreaterThanOrEqual(app(DeletionPolicy::class)->retentionDays() - 1);
});

it('throws nothing away when the copy cannot be made', function () {
    $calls = [];
    Http::fake(function (Request $request) use (&$calls) {
        $url = $request->url();
        if (! str_starts_with($url, 'https://pve.mgmt.test:8006')) {
            return null;
        }
        if (str_contains($url, '/snapshot/vcerejsi/rollback')) {
            $calls[] = 'rollback';

            return Http::response(['data' => 'UPID:x']);
        }
        if (str_ends_with($url, '/qemu/1042/snapshot') && $request->method() === 'POST') {
            return Http::response(['errors' => ['snapname' => 'storage is full']], 500); // the cluster refuses the safety snapshot
        }
        if (str_contains($url, '/tasks/')) {
            return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]);
        }

        return Http::response(['data' => []]);
    });
    [$user, $org] = $this->customerWithOrganization();
    $service = keepCopyVps($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'rollback_snapshot', $this->contextFor($user, $org, 'webauthn'), 'safety-roll-2', ['name' => 'vcerejsi']));
    expect($operation->state)->toBe('FAILED')->and($calls)->toBe([]) // nothing was rolled back
        ->and(Backup::query()->where('kind', 'pre_rollback')->where('state', 'completed')->count())->toBe(0);
});

it('puts the safety copy in front of every action that overwrites what is there', function () {
    // the chain is code: whoever adds a destructive action has to pass this
    $workflow = app(ServiceActionWorkflow::class);
    foreach (['restore' => 'pre_restore', 'rollback_snapshot' => 'pre_rollback', 'reinstall' => 'pre_reinstall'] as $action => $kind) {
        $operation = new Operation(['desired' => ['action' => $action], 'service_id' => null]);
        $labels = array_map(fn ($step) => $step->label(), $workflow->steps($operation));
        expect($labels[0])->toBe('Záloha před přepsáním', "{$action} ({$kind}) overwrites what is there and takes no copy first");
    }
});
