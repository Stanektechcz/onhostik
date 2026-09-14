<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\Models\Invoice;
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
 * Monthly marketplace listings on the subscription engine (audit §5k-2): the order opens a subscription, every renewal
 * charges the credit, issues a paid statement and books the partner's share of the period; the customer ends it with the
 * paid period; without credit it goes past due, is retried, and ends after the grace period.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function monthlyListing(): array
{
    [$partnerOwner, $partnerOrg] = customerOrg('agentura@pixel.cz', 'Agentura Pixel s.r.o.');
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $marketplace = app(MarketplaceService::class);
    $listing = $marketplace->createListing($partner, ['key' => 'wp-care-monthly', 'title' => 'WordPress péče měsíčně', 'category' => 'care', 'price_minor' => 100000, 'billing' => 'monthly', 'delivery_days' => 3], CommandContext::system('test'));
    $marketplace->setListingState($listing, 'published', 'ok', CommandContext::system('test'));

    return [$partner, $listing, $partnerOwner, $partnerOrg];
}

function customerOrg(string $email, string $name): array
{
    return test()->customerWithOrganization(['email' => $email], ['name' => $name]);
}

it('renews a monthly listing from credit, books the partner share per period and ends it when the customer says so', function () {
    [$partner, $listing] = monthlyListing();
    [$owner, $org] = customerOrg('petra@eshop.cz', 'E-shop Petra s.r.o.');
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $wallets = app(WalletService::class);
    $posted = fn () => $wallets->balances($org, 'CZK')['posted']->minor;
    $marketplace = app(MarketplaceService::class);

    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $h = ['X-Organization' => $org->id];
    $order = $this->withHeaders($h + ['Idempotency-Key' => 'ms-1'])->postJson('/v1/marketplace/wp-care-monthly/order', ['brief' => 'Měsíční péče o e-shop, prosím.'])->assertCreated()->json();
    expect($order['subscription'])->not->toBeNull()->and($order['subscription']['state'])->toBe('active')->and($order['subscription']['cancel_at_period_end'])->toBeFalse()->and($posted())->toBe(500000 - 121000);
    $subscription = Subscription::query()->findOrFail($order['subscription']['id']);
    expect($subscription->service_id)->toBeNull()->and($subscription->amount_minor)->toBe(100000)->and($subscription->period)->toBe('month');

    $model = MarketplaceOrder::query()->findOrFail($order['id']);
    $marketplace->deliver($model, $partner, 'Péče nastavena.', CommandContext::system('test'));
    $marketplace->accept($model->refresh(), $org, $ctx);
    expect(PartnerCommission::query()->where('partner_id', $partner->id)->where('kind', 'marketplace')->count())->toBe(1);

    // nothing due yet; a month later the credit pays the next period and the partner earns the period's share
    expect($marketplace->renewDue(now()))->toBe(['renewed' => 0, 'failed' => 0, 'ended' => 0]);
    $later = now()->addMonth()->addDay();
    expect($marketplace->renewDue($later))->toMatchArray(['renewed' => 1, 'failed' => 0, 'ended' => 0]);
    expect($posted())->toBe(500000 - 2 * 121000)->and($subscription->refresh()->current_period_end->toDateString())->toBe(now()->addMonths(2)->toDateString())->and($subscription->renewal_failures)->toBe(0);
    expect(Invoice::query()->where('organization_id', $org->id)->where('type', 'statement')->where('state', Invoice::PAID)->count())->toBe(1);
    expect((int) PartnerCommission::query()->where('partner_id', $partner->id)->where('kind', 'marketplace')->sum('amount_minor'))->toBe(160000);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'marketplace.renewed')->exists())->toBeTrue();
    expect($marketplace->renewDue($later))->toBe(['renewed' => 0, 'failed' => 0, 'ended' => 0]); // idempotent within the period

    // the customer ends it: the paid period runs out, then the listing stops
    $this->withHeaders($h + ['Idempotency-Key' => 'ms-2'])->postJson("/v1/account/marketplace/orders/{$order['id']}/cancel")->assertOk()->assertJsonPath('state', 'accepted')->assertJsonPath('subscription.cancel_at_period_end', true);
    expect($marketplace->renewDue(now()->addMonth()->addDays(20)))->toBe(['renewed' => 0, 'failed' => 0, 'ended' => 0]);
    expect($marketplace->renewDue(now()->addMonths(2)->addDay()))->toMatchArray(['ended' => 1, 'renewed' => 0]);
    expect(MarketplaceOrder::query()->findOrFail($order['id'])->state)->toBe('ended')->and($subscription->refresh()->state)->toBe(Subscription::CANCELLED)->and($posted())->toBe(500000 - 2 * 121000);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'marketplace.ended')->exists())->toBeTrue();
    $this->artisan('onhost:marketplace:renew')->assertSuccessful();
});

it('goes past due without credit, retries daily and ends the listing after the grace period', function () {
    [$partner, $listing] = monthlyListing();
    [$owner, $org] = customerOrg('chudy@eshop.cz', 'Chudý e-shop s.r.o.');
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('1500', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $marketplace = app(MarketplaceService::class);
    $order = $marketplace->order($org, $owner, $listing, ['brief' => 'Měsíční péče, ale kredit dojde.'], $ctx);
    $subscription = Subscription::query()->findOrFail($order->subscription_id);
    $marketplace->deliver($order, $partner, 'Hotovo.', CommandContext::system('test'));
    $marketplace->accept($order->refresh(), $org, $ctx);

    $due = now()->addMonth()->addDay();
    expect($marketplace->renewDue($due))->toMatchArray(['renewed' => 0, 'failed' => 1, 'ended' => 0]);
    expect($subscription->refresh()->state)->toBe(Subscription::PAST_DUE)->and($subscription->renewal_failures)->toBe(1)->and($subscription->next_renewal_at->toDateString())->toBe($due->copy()->addDay()->toDateString());
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'marketplace.renewal_failed')->exists())->toBeTrue();
    expect($marketplace->renewDue($due))->toBe(['renewed' => 0, 'failed' => 0, 'ended' => 0]); // not before the retry
    expect($marketplace->renewDue($due->copy()->addDays(2)))->toMatchArray(['failed' => 1]);

    // a top-up in time rescues it; without one the grace period ends the listing
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'card', 'rescue', $ctx, bankProvider: 'comgate');
    expect($marketplace->renewDue($due->copy()->addDays(4)))->toMatchArray(['renewed' => 1])->and($subscription->refresh()->state)->toBe(Subscription::ACTIVE)->and($subscription->renewal_failures)->toBe(0);
    $nextDue = $subscription->current_period_end->copy()->addDay();
    expect($marketplace->renewDue($nextDue))->toMatchArray(['failed' => 1]);
    expect($marketplace->renewDue($subscription->current_period_end->copy()->addDays(15)))->toMatchArray(['ended' => 1]);
    expect($order->refresh()->state)->toBe('ended')->and($subscription->refresh()->state)->toBe(Subscription::CANCELLED);
});
