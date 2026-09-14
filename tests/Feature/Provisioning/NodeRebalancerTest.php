<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;
use Onhost\Domain\Services\Models\Service;

/*
 * Node rebalancing (audit §5i): a node above the high mark hands its smallest services to the least loaded node of
 * the same role and region until both sit under the target; the plan is a proposal, applying it starts one
 * migration per move, inside a window the customers may move.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('plans moves from a hot node to a cold one and applies them as windowed migrations', function () {
    [$user, $org] = $this->customerWithOrganization();
    $services = [];
    for ($i = 0; $i < 4; $i++) {
        $services[] = featureGameService($org, [], 77 + $i, 'e4c1abc'.$i);
    }
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $hot = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    $hot->forceFill(['capacity' => ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 500]])->save(); // 4 × 8 GB sold on 32 GB → 100 %
    $cold = Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 500], 'usage' => [], 'remote_id' => '3']);
    Service::query()->whereKey($services[3]->id)->update(['entitlements' => json_encode(['ram_mb' => 4096, 'nvme_gb' => 30, 'backups' => 5, 'allocations' => 2, 'databases' => 2, 'subusers' => 3])]); // the smallest one moves first

    $plan = app(NodeRebalancer::class)->plan('game');
    $byName = collect($plan['nodes'])->keyBy('name');
    expect($plan['thresholds'])->toBe(['high' => 0.85, 'low' => 0.6, 'target' => 0.75])->and($byName->get('games01')['services'])->toBe(4);
    // 28 GB sold on games01 (3 × 8 + 4): 87.5 % → move the 4 GB server first (75 %, under the target) and stop
    expect($plan['moves'])->toHaveCount(1)->and($plan['moves'][0])->toMatchArray(['service_id' => $services[3]->id, 'ram_mb' => 4096, 'from' => 'games01', 'to' => 'games02'])
        ->and($byName->get('games01')['load_pct'])->toBe(75)->and($byName->get('games02')['load_pct'])->toBe(13);

    // the console reads the plan and applies it inside a window: one migration per move, waiting for the customer's start
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    expect($this->getJson('/v1/staff/provisioning/rebalance?role=game')->assertOk()->json('data.moves'))->toHaveCount(1);
    $from = now()->addDay()->startOfMinute();
    $applied = $this->withHeader('Idempotency-Key', 'rb-1')->postJson('/v1/staff/provisioning/rebalance', ['window_from' => $from->toIso8601String(), 'window_to' => $from->copy()->addDays(3)->toIso8601String(), 'reason' => 'vyrovnání zátěže'])->assertStatus(202)->json();
    $this->flushHeaders();
    expect($applied['started'])->toHaveCount(1)->and($applied['skipped'])->toBe([])->and($applied['started'][0])->toMatchArray(['kind' => 'game.migrate', 'from' => 'games01', 'to' => 'games02']);
    $operation = Operation::query()->findOrFail($applied['started'][0]['id']);
    expect($operation->state)->toBe(Operation::PENDING)->and($operation->next_run_at->toIso8601String())->toBe($from->toIso8601String())->and(Service::query()->findOrFail($services[3]->id)->tags['migration'])->toMatchArray(['state' => 'scheduled', 'target' => 'games02']);
    // the plan now knows the move is under way: nothing more to propose while it is pending
    expect(app(NodeRebalancer::class)->plan('game')['moves'])->toHaveCount(1); // the service still sits on games01 until the saga runs
    $this->postJson('/v1/staff/provisioning/rebalance', ['service_ids' => [$services[3]->id]])->assertStatus(202)->assertJsonPath('skipped.0.error', 'operation_in_progress');

    // no hot node: an empty plan
    $hot->forceFill(['capacity' => ['cpu_cores' => 64, 'ram_mb' => 262144, 'disk_gb' => 4000]])->save();
    expect(app(NodeRebalancer::class)->plan('game')['moves'])->toBe([]);
    $this->actingAs($user, 'sanctum')->getJson('/v1/staff/provisioning/rebalance')->assertForbidden();
});
