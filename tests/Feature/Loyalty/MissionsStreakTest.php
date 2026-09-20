<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\MissionService;
use Onhost\Domain\Loyalty\Models\LoyaltyBadge;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Missions and streaks (audit §5j-3): monthly missions award points once per month, a full month earns the badge; the
 * on-time streak is counted from the documents, the badge and the event fire at the target, finance grants the
 * permanent discount and the quote applies it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function streakInvoice(Organization $org, CarbonImmutable $due, bool $onTime): void
{
    $ctx = CommandContext::system('test');
    $invoices = app(InvoiceService::class);
    $draft = $invoices->draft($org, 'invoice', 'CZK', [['sku' => 'web-start', 'description' => 'Webhosting', 'qty' => 1, 'unit' => 'ks', 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 2100, 'total' => 12100]], $ctx, null, ['postpaid' => true]);
    $invoice = $invoices->issue($draft, $ctx);
    $invoices->markPaid($invoice, $invoice->total(), 'bank', $ctx);
    $invoice->forceFill(['due_at' => $due, 'paid_at' => $onTime ? $due->subDays(2) : $due->addDays(3)])->save();
}

it('awards completed missions once per month and the badge for a full month', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $summary = $this->withHeaders($h)->getJson('/v1/account/missions')->assertOk()->json('data');
    expect($summary['total'])->toBe(5)->and($summary['done'])->toBe(0)->and(collect($summary['missions'])->firstWhere('key', 'mfa_all')['progress'])->toBe('0/1');

    // nothing done yet: the evaluation awards nothing
    $this->withHeaders($h + ['Idempotency-Key' => 'mi-1'])->postJson('/v1/account/missions/evaluate')->assertOk()->assertJsonPath('awarded', []);

    // the profile, a monitor and a tested restore
    $org->forceFill(['street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '11000'])->save();
    UptimeMonitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://shop.cz/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up']);
    app(OperationService::class)->start(GameMigrationWorkflow::class, 'restore-1', ['action' => 'restore'], CommandContext::system('test'), $service->id, $org->id, null, null, null, false)
        ->forceFill(['kind' => 'service.restore', 'state' => Operation::SUCCEEDED, 'finished_at' => now()])->save();
    $result = $this->withHeaders($h + ['Idempotency-Key' => 'mi-2'])->postJson('/v1/account/missions/evaluate')->assertOk()->json();
    expect($result['awarded'])->toEqualCanonicalizing(['monitor', 'restore_test', 'profile'])->and($result['badge'])->toBeFalse()->and($result['summary']['done'])->toBe(3);
    expect(app(LoyaltyService::class)->points($org->id))->toBe(20 + 60 + 10);
    $this->withHeaders($h + ['Idempotency-Key' => 'mi-3'])->postJson('/v1/account/missions/evaluate')->assertOk()->assertJsonPath('awarded', []); // once per month
    expect(app(LoyaltyService::class)->points($org->id))->toBe(90);

    // two-factor on every member and every document paid on time: the month is complete
    $owner->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();
    streakInvoice($org, CarbonImmutable::now()->startOfMonth()->addDays(3), true);
    $full = app(MissionService::class)->evaluate($org->refresh());
    expect($full['awarded'])->toEqualCanonicalizing(['mfa_all', 'on_time'])->and($full['badge'])->toBeTrue();
    expect(LoyaltyBadge::query()->where('organization_id', $org->id)->where('badge', MissionService::BADGE_ALL)->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'loyalty.mission.completed')->count())->toBeGreaterThanOrEqual(1);
    $this->artisan('onhost:loyalty:missions')->assertSuccessful();
});

it('counts the on-time streak, fires the target once, and finance grants a discount the quote applies', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $missions = app(MissionService::class);
    $now = CarbonImmutable::now();
    for ($i = 1; $i <= 12; $i++) {
        streakInvoice($org, $now->startOfMonth()->subMonths($i)->addDays(10), true);
    }
    expect($missions->streak($org))->toMatchArray(['months' => 12, 'target' => 12, 'eligible' => true]);

    // a late month further back does not break the run of twelve; a late month last month would
    streakInvoice($org, $now->startOfMonth()->subMonths(13)->addDays(10), false);
    expect($missions->streak($org)['months'])->toBe(12);
    $result = $missions->evaluate($org);
    expect($result['streak_reached'])->toBeTrue()->and($missions->evaluate($org)['streak_reached'])->toBeFalse(); // once
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'loyalty.streak.reached')->exists())->toBeTrue();

    // finance grants 5 %: the quote takes it off every line; 0 removes it
    $quoteFor = fn (Organization $o) => app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'none'], 1, null, $o);
    $list = $quoteFor($org);
    $finance = $this->staff('platform_owner');
    $this->actingAs($finance, 'sanctum');
    // a discount is money: it takes a fresh proof of identity
    $this->withHeader('Idempotency-Key', 'st-x')->postJson("/v1/staff/loyalty/streak/{$org->id}", ['percent' => 5])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'st-0')->postJson("/v1/staff/loyalty/streak/{$org->id}", ['percent' => 50])->assertStatus(422);
    $granted = $this->withHeader('Idempotency-Key', 'st-1')->postJson("/v1/staff/loyalty/streak/{$org->id}", ['percent' => 5, 'note' => 'věrný zákazník'])->assertOk()->json();
    expect($granted['discount']['pct'])->toEqual(5)->and($granted['streak']['months'])->toBe(12);
    $discounted = $quoteFor($org->refresh());
    expect($discounted->discount_minor)->toBe(Money::minor($list->subtotal_minor, 'CZK')->percent('5')->minor)->and($discounted->lines[0]['config']['loyalty_pct'])->toEqual(5);
    $this->withHeader('Idempotency-Key', 'st-2')->postJson("/v1/staff/loyalty/streak/{$org->id}", ['percent' => 0])->assertOk()->assertJsonPath('discount', null);
    expect($quoteFor($org->refresh())->discount_minor)->toBe(0);

    // an organization without the streak cannot be granted one
    [, $other] = $this->customerWithOrganization(['email' => 'other@firma.cz']);
    $this->withHeader('Idempotency-Key', 'st-3')->postJson("/v1/staff/loyalty/streak/{$other->id}", ['percent' => 5])->assertStatus(409)->assertJsonPath('error', 'loyalty_streak_not_reached');
});
