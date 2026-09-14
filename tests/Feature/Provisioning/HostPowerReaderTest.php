<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Domain\Provisioning\HostPowerReader;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\NodeSampler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Redfish\RedfishPowerProvider;

/*
 * Per-VM power without a probe (audit §5n-6): the hourly usage watch reads a host's wall power from its management
 * controller over Redfish (credentials from the secret store, never logged) before it snapshots; a VM whose hypervisor
 * reports its own draw in the usage telemetry is charged that directly.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('reads the host wattage from the BMC during the usage watch and charges a VM its hypervisor-reported draw', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $a = featureGameService($org, [], 77, 'e4c1abc0');
    $b = featureGameService($org, [], 78, 'e4c1abc1');
    $node = Node::query()->findOrFail($a->node_id);
    $node->forceFill(['capacity' => ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 500], 'usage' => ['cpu_pct' => 30, 'ram_used_mb' => 16000, 'disk_used_gb' => 120], 'last_seen_at' => now(), 'tags' => ['bmc' => ['driver' => 'redfish', 'url' => 'https://bmc-games01.mgmt.test', 'chassis' => 'System.Embedded.1', 'secret_ref' => 'env://BMC_GAMES01']]])->save();
    app(SecretStore::class)->write(SecretRef::parse('env://BMC_GAMES01'), ['username' => 'onhost-ro', 'password' => 'not-logged']);
    Http::fake([
        'https://bmc-games01.mgmt.test/redfish/v1/Chassis/System.Embedded.1/EnvironmentMetrics' => Http::response(['error' => 'not here'], 404),
        'https://bmc-games01.mgmt.test/redfish/v1/Chassis/System.Embedded.1/Power' => Http::response(['PowerControl' => [['PowerConsumedWatts' => 312.4]]], 200),
    ]);

    // the watch reads the controller, the node carries the measured watts and the hourly sample records them
    expect(app(HostPowerReader::class)->sweep())->toBe(['read' => ['games01'], 'failed' => []]);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/Power') && $r->hasHeader('Authorization') && str_starts_with((string) $r->header('Authorization')[0], 'Basic '));
    $node->refresh();
    expect(data_get($node->usage, 'power_w'))->toBe(312)->and(data_get($node->usage, 'power_source'))->toBe('redfish');
    expect(app(NodeSampler::class)->snapshot())->toBe(1);
    expect(NodeUsageSample::query()->where('node_id', $node->id)->latest('sampled_at')->value('power_w'))->toBe(312);
    $green = app(GreenService::class);
    $rows = collect($green->footprint($org)['rows'])->keyBy('service_id');
    expect($rows[$a->id])->toMatchArray(['basis' => 'measured', 'split' => 'ram', 'kwh' => 56.94]); // 312 W × 8/32 × 730 h

    // the newer EnvironmentMetrics reading wins when the controller has it; a controller that fails answers nothing (fakes accumulate, so each phase has its own chassis path)
    Http::fake(['https://bmc-games01.mgmt.test/redfish/v1/Chassis/Chassis-2/EnvironmentMetrics' => Http::response(['PowerWatts' => ['Reading' => 288]], 200), 'https://bmc-games01.mgmt.test/*' => Http::response('boom', 500)]);
    $node->forceFill(['tags' => ['bmc' => ['driver' => 'redfish', 'url' => 'https://bmc-games01.mgmt.test', 'chassis' => 'Chassis-2', 'secret_ref' => 'env://BMC_GAMES01']]])->save();
    expect(app(HostPowerReader::class)->read($node->refresh()))->toMatchArray(['watts' => 288, 'source' => 'redfish']);
    $node->forceFill(['tags' => ['bmc' => ['driver' => 'redfish', 'url' => 'https://bmc-games01.mgmt.test', 'chassis' => 'Chassis-3', 'secret_ref' => 'env://BMC_GAMES01']]])->save();
    expect(app(HostPowerReader::class)->sweep())->toBe(['read' => [], 'failed' => ['games01']]);
    expect((new RedfishPowerProvider)->read(['url' => ''], []))->toBeNull()->and(RedfishPowerProvider::extract(['PowerSupplies' => [['PowerOutputWatts' => 100], ['LastPowerOutputWatts' => 50.6]]]))->toBe(151);
    $node->forceFill(['tags' => ['bmc' => ['driver' => 'ipmi-dcmi', 'url' => 'https://x']]])->save();
    expect(app(HostPowerReader::class)->read($node->refresh()))->toBeNull(); // no driver for that controller

    // a hypervisor that reports the VM's own draw in the usage telemetry: the VM is charged that, the rest of the node splits the remainder
    Service::query()->whereKey($b->id)->update(['tags' => json_encode(['usage' => ['power_w' => 52, 'sampled_at' => now()->toIso8601String(), 'memory' => ['used' => 2 * 1073741824]]])]);
    expect($green->serviceWatts($b->refresh()))->toBe(52);
    $rows = collect($green->footprint($org)['rows'])->keyBy('service_id');
    expect($rows[$b->id])->toMatchArray(['split' => 'vm', 'kwh' => 37.96])->and($rows[$a->id])->toMatchArray(['split' => 'ram', 'kwh' => 47.45]); // (312 − 52) W × 8/32 × 730 h
    Service::query()->whereKey($b->id)->update(['tags' => json_encode(['usage' => ['power_w' => 52, 'sampled_at' => now()->subHours(30)->toIso8601String()]])]);
    expect($green->serviceWatts($b->refresh()))->toBeNull(); // stale telemetry is not a reading
});
