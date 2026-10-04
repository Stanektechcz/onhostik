<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * Owner decision R12 (audit 2026-10): an add-on is sold per period and renews with the service it belongs to. Behind
 * `ONHOST_ADDON_RENEWALS=false` (the old default) an add-on was billed once, at the order, and then delivered for ever
 * for nothing. Now the default is on: a new add-on gets a renewing subscription, its renewal is a line on the renewal
 * document of its own, and ending it — or ending the service it belongs to — stops the renewals.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
});

/** A running web hosting (web-hosting/start, monthly) with the subscription its order would have made. */
function addonRenewalParent(Organization $org): Service
{
    $version = app(CatalogService::class)->resolve('web-hosting', 'start', 'CZK', 'month')['version'];
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['plan_version_id' => $version->id, 'entitlements' => (array) $version->entitlements])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $version->id, 'price_id' => null, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 8900,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(3), 'current_period_end' => now()->addDays(27), 'next_renewal_at' => now()->addDays(20), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();

    return $service->fresh();
}

/** The mail add-on ordered and paid for one parent, as checkout and fulfilment deliver it. */
function addonRenewalOrder(Organization $org, Service $parent, CommandContext $context): Service
{
    $quote = app(QuoteService::class)->quote([['product_key' => 'mail-hosting', 'plan_key' => 'basic', 'period' => 'month', 'config' => ['parent_service_id' => $parent->id]]], 'CZK', [], 1, null, $org);
    $consents = [];
    foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $key) {
        $consents[$key] = ['person' => 'test'];
    }
    $order = app(CheckoutService::class)->placeOrder($quote, $org, null, $consents, ['mode' => 'wallet'], 'addon-renewal-'.Str::random(8), $context, 'staff')['order'];

    return app(ServiceService::class)->createFromOrderItem(OrderItem::query()->where('order_id', $order->id)->sole(), $order, $context);
}

/** Renewal documents that carry a line of this service. @return list<Invoice> */
function addonRenewalDocuments(Service $service): array
{
    return Invoice::query()->whereNotNull('meta->renewal_period')->with('lines')->get()
        ->filter(fn (Invoice $invoice) => $invoice->lines->contains(fn ($line) => $line->service_id === $service->id))->values()->all();
}

it('renews an add-on with its service by default: a subscription at the order, a line on the renewal document', function () {
    [, $org] = $this->customerWithOrganization();
    $parent = addonRenewalParent($org);
    $context = CommandContext::system('addon renewal test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'addon-renewal-seed', $context);

    expect(config('onhost.addon_renewals'))->toBeTrue(); // the owner's decision R12 is the default, not an opt-in
    $addon = addonRenewalOrder($org, $parent, $context);
    $subscription = Subscription::query()->where('service_id', $addon->id)->sole();
    expect($subscription->state)->toBe(Subscription::ACTIVE)->and($subscription->period)->toBe('month')->and($subscription->amount_minor)->toBe(3900)
        ->and($addon->fresh()->subscription_id)->toBe($subscription->id)
        ->and(addonRenewalDocuments($addon))->toBe([]); // the order paid the first period; nothing is renewed yet

    $this->travelTo($subscription->next_renewal_at->copy()->addMinute());
    $stats = app(SubscriptionService::class)->tick();

    expect($stats['renewed'])->toBeGreaterThanOrEqual(1);
    $documents = addonRenewalDocuments($addon);
    expect($documents)->toHaveCount(1);
    $line = $documents[0]->lines->firstWhere('service_id', $addon->id);
    expect($line->sku)->toBe('mail-hosting-renewal')->and((int) $line->net_minor)->toBe(3900)
        ->and(data_get($documents[0]->meta, 'subscription_id'))->toBe($subscription->id)
        ->and($subscription->fresh()->current_period_end->greaterThan($subscription->current_period_end))->toBeTrue();
});

it('stops renewing an add-on the customer cancelled, and one whose service ended', function () {
    [, $org] = $this->customerWithOrganization();
    $parent = addonRenewalParent($org);
    $context = CommandContext::system('addon renewal test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'addon-renewal-stop-seed', $context);
    $cancelled = addonRenewalOrder($org, $parent, $context);
    $orphaned = addonRenewalOrder($org, $parent, $context);
    $cancelledSubscription = Subscription::query()->where('service_id', $cancelled->id)->sole();
    $orphanedSubscription = Subscription::query()->where('service_id', $orphaned->id)->sole();

    // the customer ends one add-on at the end of the period it paid for
    app(SubscriptionService::class)->cancelAtPeriodEnd($cancelledSubscription, true, $context);
    // the service the other one belongs to is cancelled (in its deletion grace window, then gone)
    $parent->forceFill(['state' => ServiceStateMachine::TERMINATED])->save();

    $this->travelTo($cancelledSubscription->current_period_end->copy()->addMinute());
    app(SubscriptionService::class)->tick();

    expect($cancelledSubscription->fresh()->state)->toBe(Subscription::CANCELLED)
        ->and($orphanedSubscription->fresh()->state)->toBe(Subscription::CANCELLED)
        ->and(addonRenewalDocuments($cancelled))->toBe([])->and(addonRenewalDocuments($orphaned))->toBe([]);

    // a month later still nothing: a cancelled subscription is not picked up again
    $this->travel(31)->days();
    app(SubscriptionService::class)->tick();
    expect(addonRenewalDocuments($cancelled))->toBe([])->and(addonRenewalDocuments($orphaned))->toBe([]);
});
