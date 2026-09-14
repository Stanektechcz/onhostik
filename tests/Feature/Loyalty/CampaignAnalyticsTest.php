<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Loyalty\MissionService;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Campaign analytics (audit §5m-5): per campaign the organizations it reached, the ones that completed it, what every
 * mission awarded inside the window (organizations, points), the points in total and the credit of level-ups reached.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('reports what a campaign reached, awarded and cost', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization(['email' => 'other@firma.cz'], ['name' => 'Other s.r.o.']);
    $service = featureGameService($org);
    $missions = app(MissionService::class);
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->putJson('/v1/staff/loyalty/campaigns', ['campaigns' => [
        ['key' => 'secure-autumn', 'cs' => 'Bezpečný podzim', 'en' => 'Secure autumn', 'missions' => ['profile', 'monitor'], 'badge' => 'autumn', 'active_from' => now()->subDay()->toDateString(), 'active_to' => now()->addDays(30)->toDateString()],
    ]])->assertOk();
    $this->getJson('/v1/staff/loyalty/campaigns/nope/analytics')->assertNotFound();
    $missions->announceCampaigns();
    app(OutboxPublisher::class)->relayPending();

    // announced to two organizations, nobody done yet
    $empty = $this->getJson('/v1/staff/loyalty/campaigns/secure-autumn/analytics')->assertOk()->json('data');
    expect($empty)->toMatchArray(['announced' => 2, 'completed' => 0, 'points' => 0, 'completion_rate' => 0.0])->and($empty['missions'])->toBe([['key' => 'profile', 'organizations' => 0, 'points' => 0], ['key' => 'monitor', 'organizations' => 0, 'points' => 0]])->and($empty['campaign']['key'])->toBe('secure-autumn');

    // one organization completes both missions: every number follows
    $org->forceFill(['street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '11000'])->save();
    $missions->evaluate($org->refresh());
    UptimeMonitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://shop.cz/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up']);
    expect($missions->evaluate($org->refresh())['campaigns_completed'])->toBe(['secure-autumn']);
    $other->forceFill(['street' => 'Krátká 1', 'city' => 'Brno', 'postal_code' => '60200'])->save();
    expect($missions->evaluate($other->refresh())['awarded'])->toBe(['profile']); // half way, not completed
    $profilePoints = (int) LoyaltyPoint::query()->where('rule', 'mission:profile')->sum('points');
    $monitorPoints = (int) LoyaltyPoint::query()->where('rule', 'mission:monitor')->sum('points');
    expect($profilePoints)->toBeGreaterThan(0)->and($monitorPoints)->toBeGreaterThan(0);
    $data = $this->getJson('/v1/staff/loyalty/campaigns/secure-autumn/analytics')->assertOk()->json('data');
    expect($data)->toMatchArray(['announced' => 2, 'completed' => 1, 'points' => $profilePoints + $monitorPoints, 'completion_rate' => 50.0])
        ->and($data['missions'])->toBe([['key' => 'profile', 'organizations' => 2, 'points' => $profilePoints], ['key' => 'monitor', 'organizations' => 1, 'points' => $monitorPoints]])
        ->and($data['level_ups'])->toMatchArray(['count' => 0])->and($data['level_ups']['credit']['minor'])->toBe(0)
        ->and($data['window']['from'])->toBe(now()->subDay()->toDateString());
});
