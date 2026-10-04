<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Errors\DomainError;

/*
 * Phase C, C9 — the snapshots a VPS plan sells ("N snapshotů") are kept. The panel showed the number and nothing
 * compared it with what the hypervisor held (PlanPromises KNOWN_GAPS 'snapshots'), so a server could fill its storage
 * with snapshots. Now the request is refused at the plan's number, the step counts once more before it takes one, and
 * the platform's own safety snapshots (before a rollback or a reinstall) never count against the customer.
 */

beforeEach(fn () => Http::preventStrayRequests());

function snapLimitVps(object $org, int $limit): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 2', 'hostname' => 'vm-snap.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 80, 'snapshots' => $limit], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-snap'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "snap:{$service->id}", 'adapter_version' => '1.0.0']);

    return $service;
}

/** The hypervisor's snapshot list, moved by the test by reference; `taken` records every snapshot it was asked to take. @param list<string> $names @param list<string> $taken */
function snapLimitPve(array &$names, array &$taken): void
{
    Http::fake(function (Request $request) use (&$names, &$taken) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        if (str_ends_with($path, '/qemu/1042/snapshot') && $request->method() === 'POST') {
            $taken[] = (string) $request['snapname'];
            $names[] = (string) $request['snapname'];

            return Http::response(['data' => 'UPID:prg1-n2:000D0001:0004E400:66F0DD01:qmsnapshot:1042:onhost@pve!cp:']);
        }
        if (str_ends_with($path, '/qemu/1042/snapshot')) {
            return Http::response(['data' => array_merge(array_map(fn (string $n) => ['name' => $n, 'snaptime' => 1_760_000_000], $names), [['name' => 'current']])]);
        }
        if (str_contains($path, '/tasks/')) {
            return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]);
        }

        return Http::response(['data' => null], 501);
    });
}

it('refuses a snapshot once the server holds as many as its plan sells', function () {
    $names = ['before-upgrade', 'weekly'];
    $taken = [];
    snapLimitPve($names, $taken);
    [$user, $org] = $this->customerWithOrganization();
    $service = snapLimitVps($org, 2);

    try {
        app(ServiceService::class)->requestAction($service, 'snapshot', $this->contextFor($user, $org), 'snap-full', ['name' => 'third']);
        $this->fail('expected the plan limit');
    } catch (DomainError $e) {
        expect($e->error)->toBe('feature_limit_reached')->and($e->status)->toBe(422)->and($e->extra)->toMatchArray(['limit' => 2, 'used' => 2]);
    }
    expect($taken)->toBe([])->and(Operation::query()->where('service_id', $service->id)->count())->toBe(0);

    // the same through the API, with the reason the panel shows
    $this->actingAs($user, 'sanctum');
    $this->postJson("/v1/services/{$service->id}/snapshot", ['name' => 'third'], ['X-Organization' => $org->id, 'Idempotency-Key' => 'snap-http-full'])
        ->assertStatus(422)->assertJsonPath('error', 'feature_limit_reached');

    // one deleted → room for one again
    $names = ['weekly'];
    $operation = driveOperation(app(ServiceService::class)->requestAction($service->fresh(), 'snapshot', $this->contextFor($user, $org), 'snap-room', ['name' => 'third']));
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))->and($taken)->toBe(['third']);
});

it('does not count the platform\'s own safety snapshots against the plan, and the listing says which they are', function () {
    $names = ['weekly', 'onhost-pre-rollback-261003120000'];
    $taken = [];
    snapLimitPve($names, $taken);
    [$user, $org] = $this->customerWithOrganization();
    $service = snapLimitVps($org, 2);
    Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => 'pre_rollback', 'state' => 'completed', 'protected' => true, 'remote_id' => 'onhost-pre-rollback-261003120000', 'started_at' => now(), 'finished_at' => now(), 'meta' => []]);

    $listing = app(ServiceFeatures::class)->resources($service, 'snapshots', true);
    expect(collect($listing)->pluck('counts', 'name')->all())->toBe(['weekly' => true, 'onhost-pre-rollback-261003120000' => false]);
    expect(app(ServiceFeatures::class)->features($service)['snapshots']['limit'])->toBe(2);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'snapshot', $this->contextFor($user, $org), 'snap-safety', ['name' => 'mine']));
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))->and($taken)->toBe(['mine']);
});

it('counts once more in the step, so a snapshot taken meanwhile cannot slip under the limit', function () {
    $taken = [];
    $lists = 0;
    // the request sees one snapshot; by the time the step asks, another one was made elsewhere (the panel, a second tab)
    Http::fake(function (Request $request) use (&$taken, &$lists) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        if (str_ends_with($path, '/qemu/1042/snapshot') && $request->method() === 'POST') {
            $taken[] = (string) $request['snapname'];

            return Http::response(['data' => 'UPID:prg1-n2:000D0002:0004E401:66F0DD02:qmsnapshot:1042:onhost@pve!cp:']);
        }
        if (str_ends_with($path, '/qemu/1042/snapshot')) {
            $lists++;

            return Http::response(['data' => array_merge([['name' => 'weekly']], $lists > 1 ? [['name' => 'made-elsewhere']] : [], [['name' => 'current']])]);
        }

        return Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]);
    });
    [$user, $org] = $this->customerWithOrganization();
    $service = snapLimitVps($org, 2);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'snapshot', $this->contextFor($user, $org), 'snap-race', ['name' => 'second']));
    expect($operation->state)->toBe(Operation::FAILED)
        ->and((string) data_get($operation->error, 'message', ''))->toContain('2 snapshotů')
        ->and($taken)->toBe([]);
});

it('keeps the snapshots promise for VPS plans, so it left the known gaps', function () {
    expect(MetricRegistry::isKept('snapshots', 'cloud'))->toBeTrue()
        ->and(array_key_exists('snapshots', PlanPromises::KNOWN_GAPS))->toBeFalse();
});
