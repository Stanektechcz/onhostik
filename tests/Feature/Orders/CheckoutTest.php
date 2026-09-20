<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\Budget;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

function quoteFor($org, array $items, string $currency = 'CZK', int $commit = 1)
{
    return app(QuoteService::class)->quote($items, $currency, ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], $commit, null, $org);
}

function consentsFor(): array
{
    return ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Jan Novák'], 'registry_terms_cz' => ['person' => 'Jan Novák'], 'sla' => []];
}

it('quotes a mixed cart with tax, renewal totals and locked versions', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ']);
    $quote = quoteFor($org, [
        ['product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1],
        ['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz', 'period_years' => 1]],
        ['product_key' => 'vps', 'plan_key' => 'compute-4', 'config' => ['options' => ['vcpu' => 2, 'ipv4' => true]]],
    ]);
    expect($quote->subtotal_minor)->toBe(18900 + 17900 + 44900 + 2 * 9900 + 4900)
        ->and($quote->tax_minor)->toBe(Money::minor($quote->subtotal_minor, 'CZK')->percent(21)->minor)
        ->and($quote->total_minor)->toBe($quote->subtotal_minor + $quote->tax_minor)
        ->and($quote->renewal_total_minor)->toBe(18900 + 17900 + 44900 + 2 * 9900 + 4900)
        ->and($quote->versions['plans'])->toHaveCount(2)
        ->and($quote->lines[1]['config']['tld'])->toBe('cz');
});

it('gives no commitment discount by default and applies one only after staff approved it, on top of promo codes', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $gross = 189000 * 2; // two yearly periods
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 24, null, $org);
    expect($quote->subtotal_minor)->toBe($gross)->and($quote->discount_minor)->toBe(0); // a longer term is not a discount in itself

    app(PricingRules::class)->setCommitDiscounts(['families' => ['web' => [24 => 5]]]);
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 24, 'ONHOST10', $org);
    $commit = Money::minor($gross, 'CZK')->percent(5)->minor;
    $promo = Money::minor($gross - $commit, 'CZK')->percent(10)->minor;
    expect($quote->subtotal_minor)->toBe($gross)->and($quote->discount_minor)->toBe($commit + $promo);
});

it('places a wallet-paid order: hold, PAID, credit statement, order.paid event', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ctx = $this->contextFor($owner, $org);
    $wallets->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $quote = quoteFor($org, [['product_key' => 'web-hosting', 'plan_key' => 'start'], ['product_key' => 'domain', 'config' => ['fqdn' => 'example.cz']]]);

    $result = app(CheckoutService::class)->placeOrder($quote, $org, $owner, consentsFor(), ['mode' => 'wallet'], 'chk-1', $ctx);
    $order = $result['order']->refresh();
    expect($order->state)->toBe(OrderStateMachine::PAID)
        ->and($order->number)->toMatch('/^OH-\d{4}-\d{4}$/')
        ->and($order->wallet_hold_id)->not->toBeNull()
        ->and($order->items()->count())->toBe(2)
        ->and($wallets->balances($org, 'CZK')['reserved']->minor)->toBe($order->total_minor)
        ->and(OutboxMessage::query()->where('name', 'order.paid')->exists())->toBeTrue();

    $statement = Invoice::query()->find($order->invoice_id);
    expect($statement->type)->toBe('statement')->and($statement->state)->toBe(Invoice::PAID)->and($statement->number)->toStartWith('VY-')
        ->and($statement->pdf_hash)->not->toBeNull();

    // idempotent replay returns the same order
    $again = app(CheckoutService::class)->placeOrder($quote, $org, $owner, consentsFor(), ['mode' => 'wallet'], 'chk-1', $ctx);
    expect($again['order']->id)->toBe($order->id);
});

it('refuses checkout without required consents or funds', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $quote = quoteFor($org, [['product_key' => 'web-hosting', 'plan_key' => 'start']]);
    expect(fn () => app(CheckoutService::class)->placeOrder($quote, $org, $owner, ['terms' => []], ['mode' => 'wallet'], 'chk-2', $ctx))
        ->toThrow(DomainError::class, 'Consent');
    expect(fn () => app(CheckoutService::class)->placeOrder($quote, $org, $owner, consentsFor(), ['mode' => 'wallet'], 'chk-3', $ctx))
        ->toThrow(DomainError::class, 'Insufficient');
});

it('runs the gateway path end to end with a verified Comgate callback, deduplicating repeats', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    putenv('COMGATE_MERCHANT=123456');
    putenv('COMGATE_SECRET=topsecret');
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'topsecret';
    config(['onhost.payments.comgate.callback_allowlist' => []]);
    $quote = quoteFor($org, [['product_key' => 'web-hosting', 'plan_key' => 'standard']]);
    $total = $quote->total_minor;

    Http::fake([
        'payments.comgate.cz/v2.0/payment' => Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'AB12-CD34-EF56', 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=AB12-CD34-EF56']),
        'payments.comgate.cz/v2.0/payment/transId/AB12-CD34-EF56' => Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'AB12-CD34-EF56', 'status' => 'PAID', 'price' => $total, 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2', 'refId' => 'x']),
    ]);

    $result = app(CheckoutService::class)->placeOrder($quote, $org, $owner, consentsFor(), ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'], 'chk-4', $ctx);
    $order = $result['order']->refresh();
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and($result['redirect_url'])->toContain('comgate.cz');

    $request = Request::create('/v1/webhooks/payments/comgate', 'POST', ['transId' => 'AB12-CD34-EF56', 'status' => 'PAID', 'price' => $total, 'curr' => 'CZK', 'secret' => 'topsecret']);
    $payments = app(PaymentService::class);
    $first = $payments->handleWebhook('comgate', $request);
    $second = $payments->handleWebhook('comgate', $request);
    $third = $payments->handleWebhook('comgate', Request::create('/v1/webhooks/payments/comgate', 'POST', ['transId' => 'AB12-CD34-EF56', 'status' => 'PAID', 'price' => $total, 'curr' => 'CZK']));

    expect($first['result'])->toBe('settled')->and($second['result'])->toBe('duplicate')->and($third['result'])->toBe('duplicate');
    $order->refresh();
    expect($order->state)->toBe(OrderStateMachine::PAID)->and($order->wallet_hold_id)->not->toBeNull();

    $wallets = app(WalletService::class);
    $b = $wallets->balances($org, 'CZK');
    expect($b['posted']->minor)->toBe($total)->and($b['reserved']->minor)->toBe($total)->and($b['available']->minor)->toBe(0);
    expect(Invoice::query()->where('type', 'receipt')->count())->toBe(1)
        ->and(Invoice::query()->where('type', 'statement')->count())->toBe(1)
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();
    $receipt = Invoice::query()->where('type', 'receipt')->first();
    expect($receipt->total_minor)->toBe($total)->and($receipt->tax_minor + $receipt->subtotal_minor)->toBe($total);
});

it('issues a proforma with variable symbol for bank transfer and settles it from a statement line', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $quote = quoteFor($org, [['product_key' => 'mail', 'plan_key' => 'mail-business']]);
    $result = app(CheckoutService::class)->placeOrder($quote, $org, $owner, consentsFor(), ['mode' => 'bank'], 'chk-5', $ctx);
    $order = $result['order']->refresh();
    $proforma = Invoice::query()->find($order->invoice_id);
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and($proforma->type)->toBe('proforma')->and($result['bank_instructions']['variable_symbol'])->toBe($proforma->payment_reference);

    $line = BankStatementLine::query()->create([
        'account' => 'CZ00', 'amount_minor' => $order->total_minor, 'currency' => 'CZK', 'variable_symbol' => $proforma->payment_reference,
        'counterparty' => 'Test s.r.o.', 'booked_at' => now(), 'external_id' => 'stmt-1', 'state' => 'unmatched',
    ]);
    $intent = app(PaymentService::class)->matchBankLine($line, $ctx);
    expect($intent)->not->toBeNull()->and($order->refresh()->state)->toBe(OrderStateMachine::PAID)->and($proforma->refresh()->state)->toBe(Invoice::PAID);
});

/*
 * The provider took the money: crediting it must not depend on what is built on top of it. A frozen wallet (or, before
 * this rule, a budget) made the order's hold throw inside the settlement transaction — the customer had paid, and
 * neither the credit, nor the receipt, nor the order existed, on every retry of the callback.
 */
it('keeps a captured payment when the order cannot be marked paid, tells finance, and asks the budget before anybody pays', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    putenv('COMGATE_MERCHANT=123456');
    putenv('COMGATE_SECRET=topsecret');
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'topsecret';
    config(['onhost.payments.comgate.callback_allowlist' => []]);
    $quote = quoteFor($org, [['product_key' => 'web-hosting', 'plan_key' => 'standard']]);
    $total = $quote->total_minor;
    Http::fake([
        'payments.comgate.cz/v2.0/payment' => Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'ZZ99-FR0Z-EN00', 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=ZZ99-FR0Z-EN00']),
        'payments.comgate.cz/v2.0/payment/transId/ZZ99-FR0Z-EN00' => Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'ZZ99-FR0Z-EN00', 'status' => 'PAID', 'price' => $total, 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2', 'refId' => 'x']),
    ]);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, consentsFor(), ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'], 'chk-frozen', $ctx)['order'];

    app(WalletService::class)->freeze($org, 'CZK', 'fraud review', CommandContext::system('test')); // between the order and the payment
    $result = app(PaymentService::class)->handleWebhook('comgate', Request::create('/v1/webhooks/payments/comgate', 'POST', ['transId' => 'ZZ99-FR0Z-EN00', 'status' => 'PAID', 'price' => $total, 'curr' => 'CZK', 'secret' => 'topsecret']));

    expect($result['result'])->toBe('settled'); // the callback is answered: the provider does not retry a payment we hold
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe($total) // the money is the customer's credit …
        ->and(Invoice::query()->where('type', 'receipt')->count())->toBe(1)                  // … with its receipt
        ->and($order->fresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT);             // the order is simply not paid yet
    expect(OutboxMessage::query()->where('name', 'finance.reconciliation.mismatch')->get()->contains(fn (OutboxMessage $m) => ($m->payload['kind'] ?? '') === 'paid_but_not_applied'))->toBeTrue();
    expect(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();

    // a hard budget refuses a card order when it is placed — not after the card was charged
    [$owner2, $org2] = $this->customerWithOrganization();
    Budget::query()->create(['organization_id' => $org2->id, 'currency' => 'CZK', 'limit_minor' => 10000, 'hard' => true, 'alert_thresholds' => [100], 'period_start' => now()->startOfMonth()->toDateString()]);
    expect(fn () => app(CheckoutService::class)->placeOrder(quoteFor($org2, [['product_key' => 'web-hosting', 'plan_key' => 'standard']]), $org2, $owner2, consentsFor(), ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'], 'chk-budget', $this->contextFor($owner2, $org2)))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('budget_exceeded'));
});

/* A promo code is used when an order is placed with it. The counter existed and nothing wrote to it: every limited code was unlimited. */
it('counts a promo code when an order is placed with it, stops at the limit, and gives the use back when an unpaid order is cancelled', function () {
    PromoCode::query()->updateOrCreate(['code' => 'PRVNI2'], ['kind' => 'percent', 'value' => 10, 'max_uses' => 2, 'uses' => 0, 'state' => 'active', 'first_period_only' => true]);
    $place = function (string $key, string $mode = 'bank') {
        [$owner, $org] = $this->customerWithOrganization();
        $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, 'PRVNI2', $org);

        return [app(CheckoutService::class)->placeOrder($quote, $org, $owner, consentsFor(), ['mode' => $mode], $key, $this->contextFor($owner, $org))['order'], $owner, $org];
    };
    [$first, $firstOwner, $firstOrg] = $place('promo-1');
    $place('promo-2');
    expect(PromoCode::query()->where('code', 'PRVNI2')->value('uses'))->toBe(2)->and($first->discount_minor)->toBeGreaterThan(0);

    // the third one quoted while the code still looked free: refused at placement, nothing ordered
    [$lateOwner, $lateOrg] = $this->customerWithOrganization();
    PromoCode::query()->where('code', 'PRVNI2')->update(['uses' => 1]);
    $lateQuote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, 'PRVNI2', $lateOrg);
    PromoCode::query()->where('code', 'PRVNI2')->update(['uses' => 2]);
    expect(fn () => app(CheckoutService::class)->placeOrder($lateQuote, $lateOrg, $lateOwner, consentsFor(), ['mode' => 'bank'], 'promo-3', $this->contextFor($lateOwner, $lateOrg)))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('promo_exhausted'));
    expect(Order::query()->where('organization_id', $lateOrg->id)->exists())->toBeFalse();

    // an unpaid order that is cancelled gives its use back
    app(CheckoutService::class)->transition($first, OrderStateMachine::CANCELLED, $this->contextFor($firstOwner, $firstOrg), 'rozmyslel jsem si to');
    expect(PromoCode::query()->where('code', 'PRVNI2')->value('uses'))->toBe(1);
});

it('does not let public forms without an e-mail share one rate-limit bucket with guest checkout', function () {
    $limits = app(RateLimiter::class)->limiter('auth');
    $keys = fn (array $input) => array_map(fn ($limit) => $limit->key, $limits(Request::create('/v1/leads', 'POST', $input)));
    expect($keys(['name' => 'bez e-mailu']))->toHaveCount(1)                                   // only the per-address limit
        ->and($keys(['email' => 'Nekdo@Example.cz']))->toContain('email:nekdo@example.cz')
        ->and($keys(['customer' => ['email' => 'host@example.cz']]))->toContain('email:host@example.cz'); // guest checkout carries it here
});

/* "Seen before" is not "settled": the event id is `transId:STATUS`, and the customer knows their transId. */
it('settles the real callback even when somebody posted the same event before the payment happened', function () {
    [$owner, $org] = $this->customerWithOrganization();
    putenv('COMGATE_MERCHANT=123456');
    putenv('COMGATE_SECRET=topsecret');
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'topsecret';
    config(['onhost.payments.comgate.callback_allowlist' => []]);
    $quote = quoteFor($org, [['product_key' => 'web-hosting', 'plan_key' => 'standard']]);
    $total = $quote->total_minor;
    $paid = false;
    Http::fake(function (Illuminate\Http\Client\Request $request) use (&$paid, $total) {
        return str_contains($request->url(), '/payment/transId/')
            ? Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'EARL-YB1R-D000', 'status' => $paid ? 'PAID' : 'PENDING', 'price' => $total, 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2', 'refId' => 'x'])
            : Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'EARL-YB1R-D000', 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=EARL-YB1R-D000']);
    });
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, consentsFor(), ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'], 'chk-early', $this->contextFor($owner, $org))['order'];
    $callback = fn (array $extra = []) => app(PaymentService::class)->handleWebhook('comgate', Request::create('/v1/webhooks/payments/comgate', 'POST', ['transId' => 'EARL-YB1R-D000', 'status' => 'PAID', 'price' => $total, 'curr' => 'CZK'] + $extra));

    expect($callback()['result'])->not->toBe('settled'); // posted by the customer before paying: the provider says PENDING, nothing is credited
    expect($order->fresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(0);

    $paid = true; // the customer pays; the provider's own PAID carries the same event id
    expect($callback(['secret' => 'topsecret'])['result'])->toBe('settled');
    expect($order->fresh()->state)->toBe(OrderStateMachine::PAID);
    expect($callback(['secret' => 'topsecret'])['result'])->toBe('duplicate'); // and once settled, a repeat is a repeat
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe($total);
});
