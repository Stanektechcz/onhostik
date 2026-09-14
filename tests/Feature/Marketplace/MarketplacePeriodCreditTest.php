<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Late credit on subscriptions (audit §5n-2): a monthly listing owes a deliverable every period; the partner reports it,
 * the customer hears it; a partner mostly through the period without a report is reminded once; a period that ended
 * without a deliverable credits the customer half the period price at the next renewal, taken from the partner's share.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('reports monthly deliverables, reminds the partner and credits a period nobody served', function () {
    [, $partnerOrg] = $this->customerWithOrganization(['email' => 'agentura@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $marketplace = app(MarketplaceService::class);
    $listing = $marketplace->createListing($partner, ['key' => 'wp-care-monthly', 'title' => 'WordPress péče měsíčně', 'category' => 'care', 'price_minor' => 100000, 'billing' => 'monthly', 'delivery_days' => 3], CommandContext::system('test'));
    $marketplace->setListingState($listing, 'published', 'ok', CommandContext::system('test'));
    [$owner, $org] = $this->customerWithOrganization(['email' => 'petra@eshop.cz'], ['name' => 'E-shop Petra s.r.o.']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('9000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $posted = fn () => app(WalletService::class)->balances($org, 'CZK')['posted']->minor;
    $order = $marketplace->order($org, $owner, $listing, ['brief' => 'Měsíční péče, prosím.'], $ctx);
    $marketplace->deliver($order, $partner, 'Péče nastavena.', CommandContext::system('test'));
    $marketplace->accept($order->refresh(), $org, $ctx);
    $subscription = Subscription::query()->findOrFail($order->subscription_id);
    $order->refresh();

    // the first period is served by the accepted delivery; the customer's row says so
    $period = $marketplace->sla($order)['period'];
    expect($period)->toMatchArray(['served' => true, 'delivered_at' => null, 'missed_periods' => 0])->and($period['start'])->toBe($subscription->current_period_start->toIso8601String());
    expect($marketplace->sweepPeriods(now()->addDays(28)))->toBe(['warned' => 0]);

    // the renewal finds the period served: no credit, the full share is booked; the new period starts unserved
    $renewAt = $subscription->current_period_end->copy()->addMinute();
    expect($marketplace->renewDue($renewAt))->toMatchArray(['renewed' => 1]);
    $order->refresh();
    expect($order->missed_periods)->toBe(0)->and($order->late_credit_minor)->toBe(0)->and($marketplace->sla($order)['period']['served'])->toBeFalse();
    expect(PartnerCommission::query()->where('kind', 'marketplace')->where('partner_id', $partner->id)->orderByDesc('created_at')->value('amount_minor'))->toBe((int) $order->partner_minor);

    // 80 % into the period without a report the partner is reminded once
    $subscription->refresh();
    $warnAt = $subscription->current_period_start->copy()->addDays(25);
    expect($marketplace->sweepPeriods($subscription->current_period_start->copy()->addDays(10)))->toBe(['warned' => 0]);
    expect($marketplace->sweepPeriods($warnAt))->toBe(['warned' => 1])->and($marketplace->sweepPeriods($warnAt->copy()->addDay()))->toBe(['warned' => 0]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'marketplace.period_due')->where('body', 'like', '%50 %%')->count())->toBe(1);

    // the partner reports the month (inside the period, as in life): the order stays accepted, the customer hears it, the period counts as served
    $this->travelTo($subscription->current_period_start->copy()->addDays(26));
    $marketplace->deliver($order, $partner, 'Aktualizace pluginů, záloha, report.', CommandContext::system('test'));
    $order->refresh();
    expect($order->state)->toBe(MarketplaceOrder::ACCEPTED)->and($order->period_delivered_at)->not->toBeNull()->and($marketplace->sla($order)['period']['served'])->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'marketplace.period_delivered')->where('body', 'Aktualizace pluginů, záloha, report.')->exists())->toBeTrue();
    $this->actingAs($owner, 'sanctum');
    $row = collect($this->withHeaders(['X-Organization' => $org->id])->getJson('/v1/account/marketplace/orders')->assertOk()->json('data'))->firstWhere('id', $order->id);
    expect($row['sla']['period']['served'])->toBeTrue()->and($row['sla']['late_credit_preview'])->toBeNull();

    // served → the next renewal credits nothing; unserved → the renewal after credits 50 % of the period price from the partner's share
    $second = $subscription->current_period_end->copy()->addMinute();
    expect($marketplace->renewDue($second))->toMatchArray(['renewed' => 1]);
    $order->refresh();
    expect($order->missed_periods)->toBe(0)->and($order->period_delivered_at)->toBeNull();
    $before = $posted();
    $subscription->refresh();
    $third = $subscription->current_period_end->copy()->addMinute();
    expect($marketplace->renewDue($third))->toMatchArray(['renewed' => 1]);
    $order->refresh();
    expect($order->missed_periods)->toBe(1)->and($order->late_credit_minor)->toBe(50000)->and($posted())->toBe($before - 121000 + 50000);
    expect(PartnerCommission::query()->where('kind', 'marketplace')->where('partner_id', $partner->id)->orderByDesc('created_at')->orderByDesc('id')->value('amount_minor'))->toBe((int) $order->partner_minor - 50000);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'marketplace.period_missed')->where('title', 'like', 'Kredit za chybějící měsíční plnění%')->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'marketplace.period_missed_partner')->exists())->toBeTrue();
    expect($marketplace->sla($order->refresh())['late_credit'])->toEqual(Money::minor(50000, 'CZK'));

    // the knob: a zero rate switches the credit off
    config()->set('onhost.marketplace.subscription_missed_credit_pct', 0);
    $subscription->refresh();
    expect($marketplace->renewDue($subscription->current_period_end->copy()->addMinute()))->toMatchArray(['renewed' => 1])->and($order->refresh()->missed_periods)->toBe(1);
    expect(Artisan::call('onhost:marketplace:sla'))->toBe(0)->and(Artisan::output())->toContain('running periods reminded');
});
