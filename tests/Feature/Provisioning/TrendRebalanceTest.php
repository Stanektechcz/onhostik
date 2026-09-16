<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodeSampler;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;

/*
 * Predictive rebalancing from history (audit §5k-7): hourly samples build a 7-day trend per node — the 95th percentile
 * plus the slope projected a week ahead — so a node that is still fine today but climbing is rebalanced before the peak.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('samples the nodes hourly and plans from the projected trend', function () {
    [$user, $org] = $this->customerWithOrganization();
    $services = [featureGameService($org, [], 77, 'e4c1abc0'), featureGameService($org, [], 78, 'e4c1abc1')];
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $hot = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    $hot->forceFill(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 40, 'ram_used_mb' => 45000, 'disk_used_gb' => 100], 'last_seen_at' => now()])->save();
    $cold = Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 4096, 'disk_used_gb' => 10], 'last_seen_at' => now()]);

    // the sampler snapshots what the integrations measured; a node nobody has seen for days is skipped
    $sampler = app(NodeSampler::class);
    expect($sampler->snapshot())->toBe(2);
    $cold->forceFill(['last_seen_at' => now()->subDays(3)])->save();
    expect($sampler->snapshot())->toBe(1);
    $cold->forceFill(['last_seen_at' => now()])->save();
    $this->artisan('onhost:provisioning:sample-nodes')->assertSuccessful();

    // too few samples: the trend basis falls back to the last measurement (45 000 of 65 536 = 69 %, under the mark)
    $rebalancer = app(NodeRebalancer::class);
    expect($rebalancer->trend($hot))->toBeNull();
    $fallback = $rebalancer->plan('game', 'trend');
    expect($fallback['basis'])->toBe('trend')->and(collect($fallback['nodes'])->firstWhere('name', 'games01')['load_before_pct'])->toBe(69)->and($fallback['moves'])->toBe([]);

    // a week of hourly samples climbing from 30 to 45 GB: the projection crosses the high mark although today does not
    NodeUsageSample::query()->where('node_id', $hot->id)->delete();
    for ($h = 7 * 24 - 1; $h >= 0; $h--) { // the newest sample is "now": the oldest one never slips out of the seven-day window while the test runs
        $ram = (int) round(30000 + (7 * 24 - 1 - $h) * (15000 / (7 * 24)));
        NodeUsageSample::query()->create(['node_id' => $hot->id, 'sampled_at' => now()->subHours($h), 'cpu_pct' => 40, 'ram_used_mb' => $ram, 'disk_used_gb' => 100, 'source' => 'snapshot']);
    }
    $trend = $rebalancer->trend($hot);
    expect($trend['samples'])->toBe(168)->and($trend['p95_mb'])->toBeGreaterThan(44000)->and($trend['slope_mb_per_day'])->toBeGreaterThan(1500)->and($trend['projected_mb'])->toBeGreaterThan(55000)->and($trend['projected_mb'])->toBeLessThanOrEqual(65536);
    $plan = $rebalancer->plan('game', 'trend');
    $games01 = collect($plan['nodes'])->firstWhere('name', 'games01');
    expect($games01['load_before_pct'])->toBeGreaterThan(85)->and($plan['moves'])->not->toBe([])->and($plan['moves'][0]['to'])->toBe('games02');
    expect($rebalancer->plan('game', 'usage')['moves'])->toBe([]); // the last measurement alone sees nothing

    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    expect($this->getJson('/v1/staff/provisioning/rebalance?role=game&basis=trend')->assertOk()->json('data.basis'))->toBe('trend');
    $this->artisan('onhost:rebalance:plan --role=game --basis=trend')->assertSuccessful()->expectsOutputToContain('basis trend');
});
