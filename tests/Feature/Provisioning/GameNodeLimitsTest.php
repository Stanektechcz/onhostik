<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;

/*
 * Node limits through the panel API (audit §5q follow-up): the operator never opens the panel — memory, disk and
 * over-allocation of a game node are set from the console, the panel's PATCH carries the whole record, the daemon's
 * RAM can be detected, and the scheduler sells the new capacity at once.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('sets node limits on the panel from the console and refreshes the scheduler capacity', function () {
    [$user, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $node = ['id' => 2, 'name' => 'games01', 'description' => 'lab', 'location_id' => 3, 'public' => true, 'fqdn' => 'wings.games01.test', 'scheme' => 'https', 'behind_proxy' => false, 'memory' => 1024, 'memory_overallocate' => 0, 'disk' => 1024, 'disk_overallocate' => 0, 'upload_size' => 100, 'daemon_listen' => 8080, 'daemon_sftp' => 2022, 'maintenance_mode' => false, 'allocated_resources' => ['memory' => 2048, 'disk' => 10000]];
    $patches = [];
    Http::fake(function (Request $request) use (&$node, &$patches) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $m = $request->method();
        if (str_starts_with($request->url(), 'https://wings.games01.test:8080/api/system')) {
            return $request->header('Authorization')[0] === 'Bearer wings-token-1' ? Http::response(['version' => '1.11.13', 'system' => ['architecture' => 'amd64', 'cpu_threads' => 16, 'memory_bytes' => 33566588928, 'os' => 'debian 12']]) : Http::response([], 401);
        }
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }

        return match (true) {
            $path === '/api/application/nodes/2' && $m === 'GET' => Http::response(['object' => 'node', 'attributes' => $node]),
            $path === '/api/application/nodes/2' && $m === 'PATCH' => (function () use ($request, &$node, &$patches) {
                $patches[] = $request->data();
                $node = array_merge($node, array_intersect_key($request->data(), array_flip(['memory', 'memory_overallocate', 'disk', 'disk_overallocate', 'maintenance_mode'])));

                return Http::response(['object' => 'node', 'attributes' => $node]);
            })(),
            $path === '/api/application/nodes/2/configuration' => Http::response(['token' => 'wings-token-1', 'api' => ['port' => 8080, 'ssl' => ['enabled' => true]]]),
            $path === '/api/application/nodes' => Http::response(['object' => 'list', 'data' => [['object' => 'node', 'attributes' => $node]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => "no fake for {$m} {$path}"]]], 404),
        };
    });
    Node::query()->where('provider_instance_id', $instance->id)->update(['capacity' => ['cpu_cores' => 0, 'ram_mb' => 1024, 'disk_gb' => 1], 'usage' => ['cpu_pct' => 0, 'ram_used_mb' => 2048, 'disk_used_gb' => 10], 'remote_id' => '2']);
    $before = app(NodeScheduler::class)->sellableCapacity('game', 'cz1')['ram_mb'];

    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'nl-0')->putJson("/v1/staff/integrations/{$instance->key}/game/nodes/2", [])->assertStatus(422)->assertJsonPath('error', 'node_update_empty');
    $this->withHeader('Idempotency-Key', 'nl-0b')->putJson("/v1/staff/integrations/{$instance->key}/game/nodes/2", ['memory' => 512])->assertStatus(422);

    // explicit limits: the PATCH carries the whole node record with the new numbers, the scheduler node follows
    $row = $this->withHeader('Idempotency-Key', 'nl-1')->putJson("/v1/staff/integrations/{$instance->key}/game/nodes/2", ['memory' => 16384, 'disk' => 200000, 'reason' => 'nový stroj'])->assertOk()->json();
    expect($row)->toMatchArray(['id' => 2, 'memory' => 16384, 'disk' => 200000, 'memory_overallocate' => 0, 'maintenance' => false, 'detected' => null]);
    expect($patches)->toHaveCount(1)->and($patches[0])->toMatchArray(['name' => 'games01', 'fqdn' => 'wings.games01.test', 'scheme' => 'https', 'location_id' => 3, 'memory' => 16384, 'disk' => 200000, 'disk_overallocate' => 0, 'upload_size' => 100, 'daemon_sftp' => 2022, 'daemon_listen' => 8080, 'maintenance_mode' => false]);
    $local = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    expect($local->capacity['ram_mb'])->toBe(16384)->and($local->capacity['disk_gb'])->toBe(195);
    expect(app(NodeScheduler::class)->sellableCapacity('game', 'cz1'))->toMatchArray(['largest_node' => 'games01', 'nodes' => 1])->and($before)->toBe(0); // N+1: a one-node pool sells nothing until the second node; the limit itself is on the node row

    // detection: the daemon's RAM minus the reserve becomes the limit; over-allocation and maintenance travel too
    config()->set('onhost.game.node_reserve_mb', 2048);
    $row = $this->withHeader('Idempotency-Key', 'nl-2')->putJson("/v1/staff/integrations/{$instance->key}/game/nodes/2", ['detect' => true, 'disk_overallocate' => 10])->assertOk()->json();
    expect($row['memory'])->toBe(32011 - 2048)->and($row['disk_overallocate'])->toBe(10)->and($row['detected'])->toMatchArray(['memory_mb' => 32011, 'cpu_threads' => 16, 'os' => 'debian 12', 'version' => '1.11.13']);
    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://wings.games01.test:8080/api/system') && ($r['v'] ?? '') === '2');
    expect(Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail()->capacity['ram_mb'])->toBe(29963);
    $this->withHeader('Idempotency-Key', 'nl-3')->putJson("/v1/staff/integrations/{$instance->key}/game/nodes/2", ['maintenance' => true])->assertOk()->assertJsonPath('maintenance', true);
    expect(Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail()->state)->toBe('maintenance');

    $this->actingAs($user, 'sanctum');
    $this->withHeader('Idempotency-Key', 'nl-4')->putJson("/v1/staff/integrations/{$instance->key}/game/nodes/2", ['memory' => 4096])->assertForbidden();
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js')))->toContain("'Limity uzlu'")->toContain('{ detect: true }');
});
