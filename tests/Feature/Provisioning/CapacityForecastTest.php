<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\CapacityForecast;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Trend-driven pre-provisioning (audit §5m-7): per role and region the sellable RAM against what is sold and how fast the
 * measured load climbs — the days left before the pool is sold out — warned to operations once a day while it is short.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('tells operations how many days a pool has left from the 7-day trend, once a day', function () {
    [$user, $org] = $this->customerWithOrganization();
    featureGameService($org, [], 77, 'e4c1abc0');
    featureGameService($org, [], 78, 'e4c1abc1'); // 16 GB sold in game/cz1
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $hot = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    $hot->forceFill(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 40, 'ram_used_mb' => 45000, 'disk_used_gb' => 100], 'last_seen_at' => now()])->save();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 1024, 'disk_used_gb' => 10], 'last_seen_at' => now()]);
    $forecast = app(CapacityForecast::class);

    // no history: the pool is measured by the last readings, nothing grows, nothing is short (49 152 MB sellable = N+1 × 0.75)
    $game = collect($forecast->forecast())->firstWhere('role', 'game');
    expect($game)->toMatchArray(['region' => 'cz1', 'nodes' => 2, 'sellable_mb' => 49152, 'sold_mb' => 16384, 'used_mb' => 46024, 'headroom_mb' => 3128, 'growth_mb_per_day' => 0, 'days_left' => null, 'low' => false, 'basis' => 'sold']);
    expect($forecast->warn())->toBe([]);

    // a week of samples climbing 15 GB: the trend says the pool is sold out within days → operations hear it once today
    for ($h = 7 * 24; $h >= 1; $h--) {
        NodeUsageSample::query()->create(['node_id' => $hot->id, 'sampled_at' => now()->subHours($h), 'cpu_pct' => 40, 'ram_used_mb' => (int) round(30000 + (7 * 24 - $h) * (15000 / (7 * 24))), 'disk_used_gb' => 100, 'source' => 'snapshot']);
    }
    $game = collect($forecast->forecast())->firstWhere('role', 'game');
    expect($game['basis'])->toBe('trend')->and($game['growth_mb_per_day'])->toBeGreaterThan(1500)->and($game['days_left'])->not->toBeNull()->and($game['days_left'])->toBeLessThan(30)->and($game['low'])->toBeTrue();
    expect($forecast->warn())->toBe(['game:cz1'])->and($forecast->warn())->toBe([]);
    app(OutboxPublisher::class)->relayPending();
    $note = Notification::query()->where('audience', 'internal')->where('event', 'capacity.forecast.low')->firstOrFail();
    expect($note->title)->toContain('game cz1')->and($note->body)->toContain('dní')->and($note->severity)->toBe('hot');
    expect(Artisan::call('onhost:provisioning:capacity-forecast'))->toBe(0)->and(Artisan::output())->toContain('game')->toContain('pools warned: 0');

    // a wider warning window and the staff capacity view carry the same numbers
    config()->set('onhost.provisioning.capacity_forecast.warn_days', 1);
    $strict = collect($forecast->forecast())->firstWhere('role', 'game');
    expect($strict['low'])->toBe($strict['days_left'] < 1);
    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    $pool = collect($this->getJson('/v1/staff/capacity')->assertOk()->json('data.forecast'))->firstWhere('role', 'game');
    expect($pool)->toMatchArray(['region' => 'cz1', 'sellable_mb' => 49152, 'sold_mb' => 16384, 'basis' => 'trend']);
});
