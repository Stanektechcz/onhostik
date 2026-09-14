<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\HostPowerReader;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/*
 * Redfish inventory (audit §5o-6): the same controller that reports watts reports temperatures, fans and power supplies;
 * a hot host or a failed PSU/fan reaches operations once a day; a healthy host stores its inventory quietly.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('stores the thermal and PSU inventory and alerts operations once about a hot host with a failed supply', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $node = Node::query()->findOrFail($service->node_id);
    $bmc = ['driver' => 'redfish', 'url' => 'https://bmc-games01.mgmt.test', 'chassis' => 'Hot', 'secret_ref' => 'env://BMC_GAMES01'];
    $node->forceFill(['usage' => ['cpu_pct' => 30, 'ram_used_mb' => 16000, 'disk_used_gb' => 120], 'last_seen_at' => now(), 'tags' => ['bmc' => $bmc]])->save();
    app(SecretStore::class)->write(SecretRef::parse('env://BMC_GAMES01'), ['token' => 'session-token']);
    Http::fake([
        'https://bmc-games01.mgmt.test/redfish/v1/Chassis/Hot/EnvironmentMetrics' => Http::response(['PowerWatts' => ['Reading' => 410]], 200),
        'https://bmc-games01.mgmt.test/redfish/v1/Chassis/Hot/Thermal' => Http::response(['Temperatures' => [['Name' => 'CPU1', 'ReadingCelsius' => 61], ['Name' => 'Inlet', 'ReadingCelsius' => 82.4]], 'Fans' => [['Name' => 'Fan1', 'Status' => ['Health' => 'OK', 'State' => 'Enabled']], ['Name' => 'Fan2', 'Status' => ['Health' => 'Critical', 'State' => 'Enabled']], ['Name' => 'Fan3', 'Status' => ['Health' => 'Critical', 'State' => 'Absent']]]], 200),
        'https://bmc-games01.mgmt.test/redfish/v1/Chassis/Hot/Power' => Http::response(['PowerSupplies' => [['Name' => 'PS1', 'Status' => ['Health' => 'OK']], ['Name' => 'PS2', 'Status' => ['Health' => 'Warning']]]], 200),
        'https://bmc-games01.mgmt.test/redfish/v1/Chassis/Cool/EnvironmentMetrics' => Http::response(['PowerWatts' => ['Reading' => 200]], 200),
        'https://bmc-games01.mgmt.test/redfish/v1/Chassis/Cool/Thermal' => Http::response(['Temperatures' => [['ReadingCelsius' => 44]], 'Fans' => []], 200),
        'https://bmc-games01.mgmt.test/redfish/v1/Chassis/Cool/Power' => Http::response(['PowerSupplies' => [['Name' => 'PS1', 'Status' => ['Health' => 'OK']]]], 200),
    ]);
    $reader = app(HostPowerReader::class);

    // the hot host: inventory stored, one alert with every problem, no second alert today
    expect($reader->sweep())->toBe(['read' => ['games01'], 'failed' => []]);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/Thermal') && $r->header('X-Auth-Token')[0] === 'session-token');
    $node->refresh();
    expect(data_get($node->usage, 'power_w'))->toBe(410)->and(data_get($node->usage, 'bmc'))->toMatchArray(['temp_max_c' => 82, 'fans_failed' => 1, 'psu_failed' => 1])->and(data_get($node->usage, 'bmc.psus.1'))->toBe(['name' => 'PS2', 'health' => 'WARNING']);
    app(OutboxPublisher::class)->relayPending();
    $alert = Notification::query()->where('audience', 'internal')->where('event', 'node.bmc.alert')->firstOrFail();
    expect($alert->title)->toBe('Hardware hlásí problém: games01')->and($alert->body)->toContain('teplota 82 °C')->toContain('1× zdroj mimo OK')->toContain('1× ventilátor mimo OK')->and($alert->severity)->toBe('hot');
    $reader->sweep();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'node.bmc.alert')->count())->toBe(1);

    // §5p-6: the fleet view carries the controller's readout per node
    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    $row = collect($this->getJson('/v1/staff/provisioning/board')->assertOk()->json('data.nodes'))->firstWhere('name', 'games01');
    expect($row['bmc'])->toMatchArray(['temp_max_c' => 82, 'psu_failed' => 1, 'fans_failed' => 1])->and($row['power_w'])->toBe(410)->and($row['bmc']['psus'][1]['health'])->toBe('WARNING');
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js')))->toContain("' · BMC '")->toContain("'zdroj mimo OK', 'PSU not OK'");

    // a cool, healthy host: inventory without an alert; a raised threshold silences a warm one
    $node->forceFill(['tags' => ['bmc' => ['chassis' => 'Cool'] + $bmc]])->save();
    Notification::query()->where('event', 'node.bmc.alert')->delete();
    $reader->sweep();
    app(OutboxPublisher::class)->relayPending();
    expect(data_get($node->refresh()->usage, 'bmc.temp_max_c'))->toBe(44)->and(Notification::query()->where('event', 'node.bmc.alert')->exists())->toBeFalse();
    config()->set('onhost.green.bmc.temp_warn_c', 40);
    $other = Node::query()->create(['provider_instance_id' => $node->provider_instance_id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 500], 'usage' => [], 'last_seen_at' => now(), 'tags' => ['bmc' => ['chassis' => 'Cool'] + $bmc]]);
    $reader->sweep();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'node.bmc.alert')->where('title', 'like', '%games02')->where('body', 'like', 'teplota 44 °C%')->exists())->toBeTrue();
});
