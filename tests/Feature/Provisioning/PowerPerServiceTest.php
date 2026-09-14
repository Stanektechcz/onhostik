<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Models\Service;

/*
 * Power per service (audit §5l-6): a measured node's watts split by the memory the hypervisor reports per service when
 * there is any, by the sold RAM otherwise — so a busy small server and an idle big one are charged what they draw.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('splits the measured node power by hypervisor telemetry and falls back to sold RAM without it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $a = featureGameService($org, [], 77, 'e4c1abc0'); // 8 GB sold
    $b = featureGameService($org, [], 78, 'e4c1abc1'); // 8 GB sold
    $node = Node::query()->findOrFail($a->node_id);
    $node->forceFill(['capacity' => ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 500], 'usage' => array_merge((array) $node->usage, ['power_w' => 300, 'power_sampled_at' => now()->toIso8601String()])])->save();
    $green = app(GreenService::class);

    // no telemetry: both weigh their sold RAM against the node's 32 GB → 300 W × 730 h = 219 kWh, 8/32 each; the idle rest is charged to nobody
    $rows = collect($green->footprint($org)['rows'])->keyBy('service_id');
    expect($rows[$a->id])->toMatchArray(['basis' => 'measured', 'split' => 'ram', 'kwh' => 54.75])->and($rows[$b->id]['kwh'])->toBe(54.75);

    // the usage watch measured A at 6 GB and B at 2 GB: the split follows the telemetry (6/32 and 2/32 of the node)
    Service::query()->whereKey($a->id)->update(['tags' => json_encode(['usage' => ['memory' => ['used' => 6 * 1073741824, 'limit' => 8 * 1073741824, 'pct' => 75]]])]);
    Service::query()->whereKey($b->id)->update(['tags' => json_encode(['usage' => ['memory' => ['used' => 2 * 1073741824, 'limit' => 8 * 1073741824, 'pct' => 25]]])]);
    $rows = collect($green->footprint($org)['rows'])->keyBy('service_id');
    expect($rows[$a->id])->toMatchArray(['split' => 'telemetry', 'kwh' => 41.06])->and($rows[$b->id])->toMatchArray(['split' => 'telemetry', 'kwh' => 13.69]);
    expect($green->footprint($org)['kwh'])->toBe(54.75);

    // one service without telemetry weighs its sold RAM next to the measured one
    Service::query()->whereKey($b->id)->update(['tags' => json_encode([])]);
    $rows = collect($green->footprint($org)['rows'])->keyBy('service_id');
    expect($rows[$a->id])->toMatchArray(['split' => 'telemetry', 'kwh' => 41.06])->and($rows[$b->id])->toMatchArray(['split' => 'ram', 'kwh' => 54.75]); // 6 GB measured vs 8 GB sold
});
