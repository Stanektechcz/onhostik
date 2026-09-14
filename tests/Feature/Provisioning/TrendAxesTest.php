<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;

/*
 * Trend on CPU and disk (audit §5l-7): the same samples carry CPU and disk; a node whose RAM looks fine but whose CPU (or
 * disk) trend sits above the mark is rebalanced, and the estimate of the other axes shrinks with the RAM share moved away.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('rebalances a node that is hot on CPU although its RAM is fine, and reports every axis', function () {
    [$user, $org] = $this->customerWithOrganization();
    $services = [featureGameService($org, [], 77, 'e4c1abc0'), featureGameService($org, [], 78, 'e4c1abc1'), featureGameService($org, [], 79, 'e4c1abc2')];
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $hot = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    $hot->forceFill(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 92, 'ram_used_mb' => 26000, 'disk_used_gb' => 120], 'last_seen_at' => now()])->save();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 2048, 'disk_used_gb' => 10], 'last_seen_at' => now()]);
    for ($h = 48; $h >= 1; $h--) { // two days of hourly samples: RAM flat at 40 % of capacity, CPU pinned at 90–95 %, disk at 12 %
        NodeUsageSample::query()->create(['node_id' => $hot->id, 'sampled_at' => now()->subHours($h), 'cpu_pct' => 90 + ($h % 6), 'ram_used_mb' => 26000, 'disk_used_gb' => 120, 'source' => 'snapshot']);
    }
    $rebalancer = app(NodeRebalancer::class);
    $trend = $rebalancer->trend($hot);
    expect($trend['cpu_p95'])->toBeGreaterThanOrEqual(94)->and($trend['disk_p95_gb'])->toBe(120)->and($trend['projected_mb'])->toBe(26000);

    // RAM alone would leave the node alone (40 %); the CPU axis makes it hot and the plan moves the smallest servers until the estimate is under the target
    $plan = $rebalancer->plan('game', 'trend');
    $games01 = collect($plan['nodes'])->firstWhere('name', 'games01');
    expect($games01['reasons'])->toBe(['cpu'])->and($games01['load_before_pct'])->toBe(40)->and($games01['cpu_p95'])->toBeGreaterThanOrEqual(94)->and($plan['moves'])->not->toBe([]);
    expect(collect($plan['moves'])->pluck('to')->unique()->all())->toBe(['games02']);
    expect($games01['cpu_est'])->toBeLessThanOrEqual(75.0);
    expect($rebalancer->plan('game', 'usage')['moves'])->toBe([]); // the last measurement of RAM sees nothing

    // a disk-hot node: 900 of 1 000 GB in the samples
    NodeUsageSample::query()->where('node_id', $hot->id)->update(['cpu_pct' => 20, 'disk_used_gb' => 900]);
    $disk = collect($rebalancer->plan('game', 'trend')['nodes'])->firstWhere('name', 'games01');
    expect($disk['reasons'])->toBe(['disk'])->and($disk['disk_p95_gb'])->toBe(900);
});
