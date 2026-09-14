<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Predictive rebalancing (audit §5j-4): the plan may read the measured RAM of a node instead of the RAM sold on it,
 * so a node that is hot in practice is found even when its sales look fine; the nightly dry run reaches operations.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('plans from the measured load when asked and mails the dry run to operations', function () {
    [$user, $org] = $this->customerWithOrganization();
    $services = [];
    for ($i = 0; $i < 2; $i++) {
        $services[] = featureGameService($org, [], 77 + $i, 'e4c1abc'.$i);
    }
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $node = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    // 16 GB sold on 64 GB (25 %) but 60 GB measured (94 %): only the usage basis sees a hot node
    $node->forceFill(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 70, 'ram_used_mb' => 61440, 'disk_used_gb' => 100]])->save();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 2048, 'disk_used_gb' => 10]]);

    $rebalancer = app(NodeRebalancer::class);
    $sold = $rebalancer->plan('game');
    expect($sold['basis'])->toBe('sold')->and($sold['moves'])->toBe([])->and(collect($sold['nodes'])->firstWhere('name', 'games01')['load_pct'])->toBe(25);
    $usage = $rebalancer->plan('game', 'usage');
    expect($usage['basis'])->toBe('usage')->and(collect($usage['nodes'])->firstWhere('name', 'games01')['load_before_pct'])->toBe(94)->and(collect($usage['nodes'])->firstWhere('name', 'games01')['load_pct'])->toBe(69)->and($usage['moves'])->toHaveCount(2)->and($usage['moves'][0]['to'])->toBe('games02');

    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    expect($this->getJson('/v1/staff/provisioning/rebalance?role=game&basis=usage')->assertOk()->json('data.moves'))->toHaveCount(2);
    expect($this->getJson('/v1/staff/provisioning/rebalance?role=game')->assertOk()->json('data.moves'))->toBe([]);

    $this->artisan('onhost:rebalance:plan --role=game --basis=usage --mail')->assertSuccessful()->expectsOutputToContain('moves proposed: 2');
    app(OutboxPublisher::class)->relayPending();
    $note = Notification::query()->where('audience', 'internal')->where('event', 'rebalance.plan')->firstOrFail();
    expect($note->title)->toContain('2 přesunů')->and($note->body)->toContain('games01 94 %');
});
