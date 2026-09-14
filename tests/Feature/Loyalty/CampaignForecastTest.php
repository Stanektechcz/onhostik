<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\MissionService;
use Onhost\Platform\Commands\CommandContext;

/*
 * Campaign cost forecast (audit §5n-5): before a campaign opens, staff see how many organizations it reaches, the points it
 * can hand out at most and what to expect from history, and the promo credit of the level-ups it may trigger.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('prices a draft campaign from the catalogue points, the level table and the missions\' history', function () {
    [, $near] = $this->customerWithOrganization(['email' => 'near@firma.cz'], ['name' => 'Near s.r.o.']);
    [, $far] = $this->customerWithOrganization(['email' => 'far@firma.cz'], ['name' => 'Far s.r.o.']);
    app(LoyaltyService::class)->award($near->id, 'seed', 'x', 480, null, CommandContext::system('test')); // 20 points under silver (500, 100 Kč credit)
    $missions = app(MissionService::class);
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->postJson('/v1/staff/loyalty/campaigns/forecast', ['missions' => ['nope']])->assertStatus(422);
    $this->postJson('/v1/staff/loyalty/campaigns/forecast', ['missions' => []])->assertStatus(422);

    // nothing in history: the default completion rate; the campaign's 30 points push one organization over silver
    $forecast = $this->postJson('/v1/staff/loyalty/campaigns/forecast', ['cs' => 'Bezpečný podzim', 'missions' => ['profile', 'monitor'], 'badge' => 'autumn', 'active_from' => now()->toDateString()])->assertOk()->json('data');
    expect($forecast)->toMatchArray(['organizations' => 2, 'points_max' => 60, 'points_expected' => 15, 'level_ups_max' => 1, 'expected_completion_pct' => 25.0])
        ->and($forecast['credit_max']['minor'])->toBe(10000)->and($forecast['credit_expected']['minor'])->toBe(2500)
        ->and($forecast['missions'])->toEqual([['key' => 'profile', 'points' => 10, 'historical_completion_pct' => 25, 'points_max' => 20, 'points_expected' => 5], ['key' => 'monitor', 'points' => 20, 'historical_completion_pct' => 25, 'points_max' => 40, 'points_expected' => 10]]) // JSON drops the zero fraction
        ->and($forecast['campaign']['key'])->toBe('draft')->and($forecast['campaign']['missions'])->toBe(['profile', 'monitor']);

    // history: one of two organizations completed the profile mission lately → 50 % expected for it
    $near->forceFill(['street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '11000'])->save();
    expect($missions->evaluate($near->refresh())['awarded'])->toBe(['profile']);
    $forecast = $missions->campaignForecast(['key' => 'autumn', 'missions' => ['profile', 'monitor']]);
    expect($forecast['missions'][0])->toMatchArray(['key' => 'profile', 'historical_completion_pct' => 50.0, 'points_expected' => 10])->and($forecast['points_expected'])->toBe(20)->and($forecast['expected_completion_pct'])->toBe(33.3)
        ->and($forecast['level_ups_max'])->toBe(1)->and($forecast['credit_expected']->minor)->toBe(3333); // near sits at 490: the campaign's 30 points still cross silver; a third of the ceiling is expected
    config()->set('onhost.loyalty.forecast.default_completion_pct', 100);
    expect($missions->campaignForecast(['missions' => ['monitor']])['points_expected'])->toBe(40);
});
