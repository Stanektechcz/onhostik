<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\OrderRiskService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Settings\SettingsStore;

/*
 * Shared risk signals (audit §5m-4): the order loop and the referral loop read each other's verdicts — a referrer with a
 * rejected order weighs on the referrals it brings, a held or refused referral weighs on the referred organization's orders —
 * and each loop's staff decisions teach the other loop's cross signal.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function crossQuote(Organization $org)
{
    return app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
}

function crossPaidInvoice(Organization $org): void
{
    $ctx = CommandContext::system('test');
    $invoices = app(InvoiceService::class);
    $draft = $invoices->draft($org, 'invoice', 'CZK', [['sku' => 'web-start', 'description' => 'Webhosting', 'qty' => 1, 'unit' => 'ks', 'unit_net' => 50000, 'discount' => 0, 'net' => 50000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 10500, 'total' => 60500]], $ctx, null, ['postpaid' => true]);
    $invoice = $invoices->issue($draft, $ctx);
    $invoices->markPaid($invoice, $invoice->total(), 'bank', $ctx);
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending();
}

it('lets a rejected order weigh on the referrals a referrer brings and a refused referral weigh on the referred organization\'s orders', function () {
    // the referrer places an order that risk holds (new account + disposable mail) and staff reject it
    [$referrerOwner, $referrer] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'company', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($referrerOwner, $referrer);
    app(WalletService::class)->topup($referrer, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $held = app(CheckoutService::class)->placeOrder(crossQuote($referrer), $referrer, $referrerOwner, $consents, ['mode' => 'wallet'], 'cross-1', $ctx)['order']->refresh();
    expect($held->meta['review']['state'])->toBe('pending');
    $referrals = app(ReferralService::class);
    $code = $referrals->code($referrer);
    [$referredOwner, $referred] = $this->customerWithOrganization(['email' => 'eva@firma.cz'], ['name' => 'Eva s.r.o.']);
    $referrals->attach($referred, $code, '203.0.113.9', CommandContext::system('test'));
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $this->withHeader('Idempotency-Key', 'cross-rv-1')->postJson("/v1/staff/orders/{$held->id}/review", ['decision' => 'reject', 'reason' => 'stolen card pattern'])->assertOk();
    $this->flushHeaders();
    expect($referrals->weights()['referrer_risk'])->toBe(45); // the order loop's reject taught the referral loop's cross signal

    // the referral the referrer brought carries `referrer_risk`; at the hold mark finance sees it
    app(SettingsStore::class)->set(ReferralService::WEIGHTS_SETTING, ['referrer_risk' => 60]); // finance had turned it up to the hold mark
    expect($referrals->weights()['referrer_risk'])->toBe(60);
    crossPaidInvoice($referred);
    $referral = Referral::query()->where('referred_organization_id', $referred->id)->firstOrFail();
    expect($referral->state)->toBe(Referral::HELD)->and($referral->signals)->toBe(['referrer_risk'])->and($referral->score)->toBe(60);

    // finance refuses it: the referral loop's reject teaches the order loop's `referral_flagged`
    expect(app(OrderRiskService::class)->weights()['referral_flagged'])->toBe(40);
    $this->actingAs($staff, 'sanctum');
    $this->withHeader('Idempotency-Key', 'cross-rf-1')->postJson("/v1/staff/referrals/{$referral->id}/review", ['decision' => 'reject', 'note' => 'referrer rejected'])->assertOk()->assertJsonPath('state', Referral::REFUSED);
    $this->flushHeaders();
    expect(app(OrderRiskService::class)->weights()['referral_flagged'])->toBe(45)->and($referrals->weights()['referrer_risk'])->toBe(65);

    // the referred organization's next order carries the referral verdict; at the hold mark it waits for staff
    $referredCtx = $this->contextFor($referredOwner, $referred);
    app(WalletService::class)->topup($referred, Money::decimal('5000', 'CZK'), 'card', 'seed-2', $referredCtx, bankProvider: 'comgate');
    $order = app(CheckoutService::class)->placeOrder(crossQuote($referred), $referred, $referredOwner, $consents, ['mode' => 'wallet'], 'cross-2', $referredCtx)['order']->refresh();
    expect($order->meta['risk']['reasons'])->toBe(['new_account', 'referral_flagged'])->and($order->meta['risk'])->toMatchArray(['score' => 70])->and($order->meta['review']['state'])->toBe('pending'); // 25 + 45 (the shared table was reset above); a paid invoice is not a paid order, so the account is still new
    app(SettingsStore::class)->set(OrderRiskService::WEIGHTS_SETTING, ['referral_flagged' => 5, 'new_account' => 25]);
    $second = app(CheckoutService::class)->placeOrder(crossQuote($referred), $referred, $referredOwner, $consents, ['mode' => 'wallet'], 'cross-3', $referredCtx)['order']->refresh();
    expect($second->meta['risk'])->toMatchArray(['score' => 5, 'reasons' => ['referral_flagged']])->and($second->meta)->not->toHaveKey('review'); // the first order paid, so the account is no longer new; finance can turn the cross signal down to the floor

    // an organization nobody referred and a referrer without referrals see neither signal
    [$plainOwner, $plain] = $this->customerWithOrganization(['email' => 'plain@firma.cz'], ['name' => 'Plain s.r.o.']);
    crossPaidInvoice($plain);
    $plainCtx = $this->contextFor($plainOwner, $plain);
    app(WalletService::class)->topup($plain, Money::decimal('5000', 'CZK'), 'card', 'seed-3', $plainCtx, bankProvider: 'comgate');
    $plainOrder = app(CheckoutService::class)->placeOrder(crossQuote($plain), $plain, $plainOwner, $consents, ['mode' => 'wallet'], 'cross-4', $plainCtx)['order']->refresh();
    expect($plainOrder->meta['risk']['reasons'])->not->toContain('referral_flagged');
});
