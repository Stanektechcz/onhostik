<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Loyalty\MissionService;
use Onhost\Domain\Loyalty\Models\LoyaltyBadge;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Mission campaigns (audit §5l-5): a bundle of missions with a shared badge and a window; the start is announced to every
 * active organization (mail when asked), completing every mission inside the window earns the badge once.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('announces a campaign once, tracks progress and grants the campaign badge when every mission is done in the window', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $missions = app(MissionService::class);
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->putJson('/v1/staff/loyalty/campaigns', ['campaigns' => [['key' => 'x1', 'missions' => ['nope'], 'active_from' => '2026-09-01']]])->assertStatus(422)->assertJsonPath('error', 'campaign_mission_unknown');
    $this->putJson('/v1/staff/loyalty/campaigns', ['campaigns' => [['key' => 'x1', 'missions' => ['profile'], 'active_from' => '2026-09-10', 'active_to' => '2026-09-01']]])->assertStatus(422);
    $table = $this->putJson('/v1/staff/loyalty/campaigns', ['campaigns' => [
        ['key' => 'secure-autumn', 'cs' => 'Bezpečný podzim', 'en' => 'Secure autumn', 'missions' => ['profile', 'monitor'], 'badge' => 'autumn', 'active_from' => now()->subDay()->toDateString(), 'active_to' => now()->addDays(30)->toDateString(), 'mail' => true],
        ['key' => 'later', 'cs' => 'Až později', 'missions' => ['profile'], 'active_from' => now()->addDays(10)->toDateString()],
    ]])->assertOk()->json('campaigns');
    expect($table)->toHaveCount(2)->and(collect($table)->firstWhere('key', 'secure-autumn')['in_window'])->toBeTrue()->and(collect($table)->firstWhere('key', 'later')['in_window'])->toBeFalse();
    expect($this->getJson('/v1/staff/loyalty/campaigns')->assertOk()->json('data.missions'))->toContain('profile');

    // the announcement goes out once, with the mail the campaign asked for
    expect($missions->announceCampaigns())->toMatchArray(['campaigns' => 1])->and($missions->announceCampaigns()['campaigns'])->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'loyalty.campaign.started')->count())->toBe(1)
        ->and(MailOutbox::query()->where('template_key', 'loyalty-campaign')->where('to', $owner->email)->exists())->toBeTrue();
    $this->artisan('onhost:loyalty:campaigns')->assertSuccessful();

    // progress: the profile alone is 1 of 2; the monitor completes it, the badge is granted once
    $org->forceFill(['street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '11000'])->save();
    $result = $missions->evaluate($org->refresh());
    expect($result['awarded'])->toBe(['profile'])->and($result['campaigns_completed'])->toBe([]);
    $progress = collect($missions->summary($org, CarbonImmutable::now(), 'en')['campaigns'])->firstWhere('key', 'secure-autumn');
    expect($progress)->toMatchArray(['done' => 1, 'total' => 2, 'earned' => false, 'title' => 'Secure autumn']);
    UptimeMonitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://shop.cz/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up']);
    $done = $missions->evaluate($org->refresh());
    expect($done['awarded'])->toBe(['monitor'])->and($done['campaigns_completed'])->toBe(['secure-autumn']);
    expect(LoyaltyBadge::query()->where('organization_id', $org->id)->where('badge', 'campaign:autumn')->exists())->toBeTrue();
    expect($missions->evaluate($org->refresh())['campaigns_completed'])->toBe([]); // once
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'loyalty.campaign.completed')->count())->toBe(1);
    $this->putJson('/v1/staff/loyalty/campaigns', ['campaigns' => []])->assertOk();
    expect($missions->campaigns(null, true))->toBe([]);
});
