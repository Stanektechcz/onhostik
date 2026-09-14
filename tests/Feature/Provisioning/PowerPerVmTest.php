<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Models\Service;

/*
 * Per-VM power (audit §5m-6): a probe that reads per-VM watts (IPMI/Redfish per-slot sensors, hypervisor power telemetry)
 * sends them with the node reading; the VM is matched to its service through the provider binding and charged what it
 * drew, and the rest of the node splits only what is left.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('charges a VM its own reading and splits the remainder among the others', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $a = featureGameService($org, [], 77, 'e4c1abc0'); // 8 GB sold, remote id 77
    $b = featureGameService($org, [], 78, 'e4c1abc1'); // 8 GB sold, remote id 78
    $node = Node::query()->findOrFail($a->node_id);
    $node->forceFill(['capacity' => ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 500]])->save();
    SlaProbe::query()->create(['key' => 'bmc-cz1', 'component_key' => 'portal', 'kind' => 'http', 'target' => 'https://bmc.mgmt.test/', 'location' => 'dc-cz1', 'interval_seconds' => 60, 'token_hash' => hash('sha256', 'bmc-token'), 'state' => 'active', 'last_seen_at' => now()]);
    $green = app(GreenService::class);

    // the reading: 300 W for the node, 100 W read on VM 77; an unknown VM id is reported back, not stored
    $this->withToken('bmc-token')->postJson('/v1/probes/power', ['readings' => [['node' => 'games01', 'watts' => 300, 'vms' => [['id' => '77', 'watts' => 100], ['id' => '99', 'watts' => 5]]]]])
        ->assertStatus(202)->assertJsonPath('data.accepted', 1)->assertJsonPath('data.unknown.0', 'games01/99');
    $this->withToken('bmc-token')->postJson('/v1/probes/power', ['readings' => [['node' => 'games01', 'watts' => 300, 'vms' => [['id' => '77']]]]])->assertStatus(422);
    $a->refresh();
    expect(data_get($a->tags, 'power.watts'))->toBe(100)->and($green->serviceWatts($a))->toBe(100)->and($green->serviceWatts($b->refresh()))->toBeNull();

    // A is charged its 100 W (73 kWh a month); B takes its RAM share of the remaining 200 W (8/32 → 50 W → 36.5 kWh)
    $rows = collect($green->footprint($org)['rows'])->keyBy('service_id');
    expect($rows[$a->id])->toMatchArray(['basis' => 'measured', 'split' => 'vm', 'kwh' => 73.0])->and($rows[$b->id])->toMatchArray(['basis' => 'measured', 'split' => 'ram', 'kwh' => 36.5]);

    // a stale VM reading falls back to the node split; the node reading itself is still fresh
    Service::query()->whereKey($a->id)->update(['tags' => json_encode(['power' => ['watts' => 100, 'at' => now()->subHours(30)->toIso8601String()]])]);
    expect($green->serviceWatts($a->refresh()))->toBeNull();
    $rows = collect($green->footprint($org)['rows'])->keyBy('service_id');
    expect($rows[$a->id])->toMatchArray(['split' => 'ram', 'kwh' => 54.75])->and($rows[$b->id]['kwh'])->toBe(54.75); // 300 W × 8/32 each again
});
