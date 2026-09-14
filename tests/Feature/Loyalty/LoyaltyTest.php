<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\Models\LoyaltyBadge;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Loyalty programme: points for what customers do, counted once per rule and reference; levels earn a badge and a
 * promo credit booked once; staff set the level table and may award points by hand; the panel reads one summary.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('awards points from events once, levels up with a promo credit and a badge, and shows the summary in the panel', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $loyalty = app(LoyaltyService::class);
    $ctx = CommandContext::system('test');
    expect($loyalty->summary($org->id))->toMatchArray(['points' => 0])->and($loyalty->levelFor(0)['key'])->toBe('bronze');

    // events → points, idempotent per reference; the first service and 2FA also bring badges
    $publish = fn (string $name, array $payload, string $type = 'organization', ?string $id = null) => app(OutboxPublisher::class)->publish(GenericEvent::of($name, $type, $id ?? $org->id, $payload, $org->id));
    $publish('payment.succeeded', ['amount' => ['minor' => 50000, 'currency' => 'CZK']], 'payment', 'pi_1');
    $publish('payment.succeeded', ['amount' => ['minor' => 50000, 'currency' => 'CZK']], 'payment', 'pi_1'); // redelivered: counted once
    $publish('security.mfa', ['method' => 'totp', 'action' => 'enabled'], 'user', $owner->id);
    app(OutboxPublisher::class)->relayPending();
    expect($loyalty->points($org->id))->toBe(60)->and(LoyaltyBadge::query()->where('organization_id', $org->id)->pluck('badge')->all())->toBe(['guardian']);
    $service = featureGameService($org);
    $publish('service.activated', ['product_key' => 'game'], 'service', $service->id);
    app(OutboxPublisher::class)->relayPending();
    expect($loyalty->points($org->id))->toBe(160)->and(LoyaltyBadge::query()->where('organization_id', $org->id)->where('badge', 'first-service')->exists())->toBeTrue();

    // staff shape the levels; crossing one earns the badge and the promo credit exactly once
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $this->withHeader('Idempotency-Key', 'lv-1')->putJson('/v1/staff/loyalty/levels', ['levels' => [['key' => 'bronze', 'name' => 'Bronze', 'min' => 0], ['key' => 'silver', 'name' => 'Silver', 'min' => 200, 'reward_minor' => 10000], ['key' => 'gold', 'name' => 'Gold', 'min' => 1000, 'reward_minor' => 30000]]])->assertOk()->assertJsonPath('levels.1.min', 200);
    $this->flushHeaders();
    $this->putJson('/v1/staff/loyalty/levels', ['levels' => [['key' => 'x', 'min' => 10]]])->assertStatus(422);
    $award = $this->withHeader('Idempotency-Key', 'aw-1')->postJson('/v1/staff/loyalty/award', ['organization_id' => $org->id, 'points' => 50, 'note' => 'Poděkování za trpělivost'])->assertCreated()->json();
    $this->flushHeaders();
    expect($award)->toMatchArray(['awarded' => true, 'total' => 210, 'level_up' => 'silver']);
    expect(app(WalletService::class)->balances($org, 'CZK')['promo']->minor)->toBe(10000)->and(LoyaltyBadge::query()->where('organization_id', $org->id)->where('badge', 'level:silver')->exists())->toBeTrue();
    $loyalty->award($org->id, 'manual', 'again', 10, null, $ctx);
    expect(app(WalletService::class)->balances($org, 'CZK')['promo']->minor)->toBe(10000); // still silver: no second reward
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Nová úroveň věrnostního programu: Silver')->value('body'))->toContain('100 Kč');
    $this->postJson('/v1/staff/loyalty/award', ['organization_id' => $org->id, 'points' => 0])->assertStatus(422);

    // the customer's summary; nothing here is reachable by another customer
    $this->actingAs($owner, 'sanctum');
    $summary = $this->getJson('/v1/account/rewards')->assertOk()->json('data');
    expect($summary)->toMatchArray(['points' => 220])->and($summary['level'])->toMatchArray(['key' => 'silver', 'name' => 'Silver'])->and($summary['next'])->toMatchArray(['key' => 'gold', 'missing' => 780])
        ->and(collect($summary['badges'])->pluck('badge')->all())->toBe(['guardian', 'first-service', 'level:silver'])->and($summary['history'][0])->toMatchArray(['rule' => 'manual', 'points' => 10]);
    [$stranger] = $this->customerWithOrganization(['email' => 'other@example.cz']);
    expect($this->actingAs($stranger, 'sanctum')->getJson('/v1/account/rewards')->assertOk()->json('data.points'))->toBe(0);
});
