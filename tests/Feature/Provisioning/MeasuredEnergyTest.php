<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;

/*
 * Measured energy (audit §5k-6): a PDU/IPMI probe reports the node's watts; the footprint takes the measurement (the
 * service's share by RAM) instead of the model while the reading is fresh; the fleet profile counts measured nodes.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('ingests probe readings and replaces the model with the measured share while the reading is fresh', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org); // 8 GB RAM on games01
    $node = Node::query()->findOrFail($service->node_id);
    $node->forceFill(['capacity' => ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 500]])->save();
    SlaProbe::query()->create(['key' => 'pdu-cz1', 'component_key' => 'portal', 'kind' => 'http', 'target' => 'https://pdu.mgmt.test/', 'location' => 'dc-cz1', 'interval_seconds' => 60, 'token_hash' => hash('sha256', 'pdu-token'), 'state' => 'active', 'last_seen_at' => now()]);
    $green = app(GreenService::class);
    expect($green->footprint($org)['rows'][0]['basis'])->toBe('model');

    $this->postJson('/v1/probes/power', ['readings' => [['node' => 'games01', 'watts' => 400]]])->assertUnauthorized();
    $this->withToken('pdu-token')->postJson('/v1/probes/power', ['readings' => [['node' => 'games01', 'watts' => 400], ['node' => 'ghost', 'watts' => 1]]])->assertStatus(202)->assertJsonPath('data.accepted', 1)->assertJsonPath('data.unknown.0', 'ghost');
    expect($node->refresh()->usage['power_w'])->toBe(400)->and(NodeUsageSample::query()->where('node_id', $node->id)->where('source', 'probe')->count())->toBe(1);

    // 400 W × 730 h = 292 kWh for the node; the service holds 8 of 32 GB → 73 kWh, 100 % renewable in cz1
    $measured = $green->footprint($org);
    expect($measured['rows'][0])->toMatchArray(['basis' => 'measured', 'kwh' => 73.0])->and($measured['gco2'])->toBe(1460.0)->and($green->platform()['measured'])->toMatchArray(['nodes' => 1, 'watts' => 400]);

    // a stale reading falls back to the model
    $node->forceFill(['usage' => array_merge((array) $node->usage, ['power_sampled_at' => now()->subDays(2)->toIso8601String()])])->save();
    expect($green->footprint($org)['rows'][0]['basis'])->toBe('model')->and($green->platform()['measured']['nodes'])->toBe(0);
});
