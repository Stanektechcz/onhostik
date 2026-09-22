<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\OperationsBoard;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/*
 * The automatic drain takes a node that only fails out of placement and puts it back once it works again. It counted
 * the operations of the whole PANEL INSTANCE for every node of it: on a Proxmox cluster or a game panel with several
 * nodes, one node that was down was never drained — the other nodes' successes outweighed its failures, so new
 * servers kept landing on it — and in a quiet window every node of the instance was drained at once. And a node drained
 * in a quiet window was put back once the PANEL answered two probes, whether the node itself was up or not.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** A three-node Proxmox cluster; `$online` says which nodes answer. @param array<string,bool> $online */
function drainCluster(array &$online): array
{
    $instance = pveLab();
    $nodes = [];
    foreach (['prg1-n1', 'prg1-n2', 'prg1-n3'] as $name) {
        $nodes[$name] = Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => $name], ['region_code' => 'cz1', 'role' => 'compute', 'state' => 'active', 'capacity' => ['cpu_cores' => 64, 'ram_mb' => 262144, 'disk_gb' => 4000], 'usage' => ['cpu_pct' => 10, 'ram_used_mb' => 20000, 'disk_used_gb' => 100], 'failure_domain' => 'rack-'.$name]);
    }
    Http::fake(function (Request $r) use (&$online) {
        $path = substr((string) parse_url($r->url(), PHP_URL_PATH), strlen('/api2/json'));

        return match ($path) {
            '/version' => Http::response(['data' => ['version' => '8.2.4', 'release' => '8.2']]),
            '/cluster/status' => Http::response(['data' => [['type' => 'cluster', 'name' => 'cz1', 'quorate' => 1]]]),
            '/nodes' => Http::response(['data' => array_map(fn (string $name, bool $up) => ['node' => $name, 'status' => $up ? 'online' : 'offline'], array_keys($online), $online)]),
            default => null,
        };
    });

    return [$instance, $nodes];
}

/** A running VPS on `$node` with operations that succeeded or keep failing on a transient error. */
function drainOperations(Organization $org, ProviderInstance $instance, Node $node, int $failing, int $succeeded): void
{
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => [], 'sla_class' => 'standard', 'tags' => []]);
    foreach (range(1, $failing + $succeeded) as $i) {
        $fails = $i <= $failing;
        Operation::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'provider_instance_id' => $instance->id, 'kind' => 'service.action', 'workflow' => 'service.action', 'queue' => 'provider-proxmox',
            'idempotency_key' => "drain-{$node->name}-{$i}", 'attempts' => 2, 'queued_at' => now()->subMinutes(4), 'state' => $fails ? Operation::WAITING : Operation::SUCCEEDED,
            'finished_at' => $fails ? null : now()->subMinute(), 'next_run_at' => $fails ? now()->addMinute() : null, 'error' => $fails ? ['message' => '[proxmox:TRANSIENT] No route to host', 'retryable' => true] : null]);
    }
}

it('drains the node that fails, not the ones beside it that work', function () {
    [, $org] = $this->customerWithOrganization();
    $online = ['prg1-n1' => true, 'prg1-n2' => false, 'prg1-n3' => true];
    [$instance, $nodes] = drainCluster($online);
    drainOperations($org, $instance, $nodes['prg1-n2'], failing: 3, succeeded: 0); // prg1-n2 is down
    drainOperations($org, $instance, $nodes['prg1-n1'], failing: 0, succeeded: 2); // its neighbour works

    app(OperationsBoard::class)->autoDrain();

    // it used to be: the neighbour's successes outweighed prg1-n2's failures — nothing drained, new VMs kept landing on the dead node
    expect($nodes['prg1-n2']->fresh()->state)->toBe('draining')
        ->and($nodes['prg1-n1']->fresh()->state)->toBe('active')
        ->and($nodes['prg1-n3']->fresh()->state)->toBe('active');
});

it('does not drain a whole cluster because one node failed in a quiet window', function () {
    [, $org] = $this->customerWithOrganization();
    $online = ['prg1-n1' => true, 'prg1-n2' => false, 'prg1-n3' => true];
    [$instance, $nodes] = drainCluster($online);
    drainOperations($org, $instance, $nodes['prg1-n2'], failing: 3, succeeded: 0); // nothing else happened in the window

    expect(app(OperationsBoard::class)->autoDrain())->toMatchArray(['drained' => 1]);

    // it used to be: all three drained — the cluster took no new VPS at all
    expect(Node::query()->where('provider_instance_id', $instance->id)->where('state', 'draining')->pluck('name')->all())->toBe(['prg1-n2']);
});

it('asks a game node\'s own daemon before it puts the node back', function () {
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $node = Node::query()->where('name', 'games01')->firstOrFail();
    $node->forceFill(['state' => 'draining', 'tags' => ['auto_drain' => ['at' => now()->subHour()->toIso8601String(), 'reason' => 'automatic: 3 transient failures in 15 min, no success']]])->save();
    $wingsUp = false;
    Http::fake(function (Request $r) use (&$wingsUp) {
        if (str_starts_with($r->url(), 'https://wings2.test:8080/')) {
            if (! $wingsUp) {
                throw new ConnectionException('cURL error 7: Failed to connect to wings2.test port 8080: Connection refused');
            }

            return Http::response(['version' => '1.11.13', 'system' => ['memory_bytes' => 68719476736, 'cpu_threads' => 16]]);
        }

        return match ((string) parse_url($r->url(), PHP_URL_PATH)) {
            '/api/application/nodes' => Http::response(['data' => [['attributes' => ['id' => 2, 'name' => 'games01', 'memory' => 65536, 'disk' => 2000000, 'maintenance_mode' => false]]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            '/api/application/nodes/2' => Http::response(['attributes' => ['id' => 2, 'name' => 'games01', 'fqdn' => 'wings2.test', 'scheme' => 'https', 'daemon_listen' => 8080]]),
            '/api/application/nodes/2/configuration' => Http::response(['token' => 'wings-node-token', 'api' => ['port' => 8080, 'ssl' => ['enabled' => true]]]),
            default => null,
        };
    });
    $board = app(OperationsBoard::class);

    $board->autoDrain();
    $board->autoDrain();
    // the panel answered both probes, the node's daemon did not: it used to come back all the same
    expect($node->fresh()->state)->toBe('draining');

    $wingsUp = true;
    $board->autoDrain();
    $board->autoDrain();
    expect($node->fresh()->state)->toBe('active');
});

it('puts a quiet drained node back only when that node answers, not when the panel does', function () {
    [, $org] = $this->customerWithOrganization();
    $online = ['prg1-n1' => true, 'prg1-n2' => false, 'prg1-n3' => true];
    [$instance, $nodes] = drainCluster($online);
    drainOperations($org, $instance, $nodes['prg1-n2'], failing: 3, succeeded: 0);
    $board = app(OperationsBoard::class);
    $board->autoDrain();
    Operation::query()->where('idempotency_key', 'like', 'drain-prg1-n2-%')->update(['state' => Operation::CANCELLED, 'error' => null, 'finished_at' => now()->subHour(), 'queued_at' => now()->subHour()]); // moved elsewhere: quiet

    $board->autoDrain();
    $board->autoDrain();
    // it used to be: the cluster answered two probes, so the node came back — still offline
    expect($nodes['prg1-n2']->fresh()->state)->toBe('draining');

    $online['prg1-n2'] = true;
    $board->autoDrain();
    $board->autoDrain();
    expect($nodes['prg1-n2']->fresh()->state)->toBe('active');
});
