<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Orders\Models\Consent;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Money\Money;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

it('lets staff credit a wallet, place an order from it on the customer\'s behalf and confirm a bank payment; the credit shown drops by the order (audit §5y)', function () {
    [, $org] = $this->customerWithOrganization();
    $finance = $this->staff('platform_owner');
    $this->actingAs($finance, 'sanctum');

    // manual credit: a reason is required and it is a HIGH action behind a fresh step-up
    $this->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 1000])->assertUnprocessable()->assertJsonValidationErrors(['note']);
    $this->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 1000, 'note' => 'platba hotově na pobočce'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $credit = $this->withHeader('Idempotency-Key', 'credit-1')->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 1000, 'note' => 'platba hotově na pobočce'])->assertCreated();
    expect($credit->json('spendable.minor'))->toBe(100000);
    expect(AuditEvent::query()->where('action', 'staff.customer.wallet.credit')->where('result', 'succeeded')->exists())->toBeTrue();

    // assisted order paid from credit: the same quote and checkout as the panel, consents recorded as confirmed by staff
    $order = $this->withHeader('Idempotency-Key', 'assist-1')->postJson("/v1/staff/customers/{$org->id}/orders", ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['domain' => 'asistence.cz']]], 'payment' => 'wallet', 'note' => 'tiket #4411'])->assertCreated();
    expect($order->json('state'))->toBe('PAID')->and($order->json('number'))->toStartWith('OH-');
    $placed = Order::query()->findOrFail($order->json('order_id'));
    expect($placed->source)->toBe('staff')->and(Consent::query()->where('order_id', $placed->id)->pluck('person')->first())->toContain('asistovaná objednávka: tiket #4411');
    $spendable = app(WalletService::class)->spendable($org, 'CZK');
    expect($spendable->minor)->toBe(100000 - $placed->total_minor)->and($order->json('spendable.minor'))->toBe($spendable->minor);

    // bank transfer: a proforma; staff confirm the incoming payment with its variable symbol and the order is paid
    $bank = $this->withHeader('Idempotency-Key', 'assist-2')->postJson("/v1/staff/customers/{$org->id}/orders", ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['domain' => 'prevod.cz']]], 'payment' => 'bank', 'note' => 'telefonát 15. 9.'])->assertCreated();
    expect($bank->json('state'))->toBe('PENDING_PAYMENT');
    $pending = Order::query()->findOrFail($bank->json('order_id'));
    $vs = (string) $bank->json('bank_instructions.variable_symbol');
    expect($vs)->not->toBe('');
    $detail = $this->getJson("/v1/staff/customers/{$org->id}")->assertOk();
    expect(collect($detail->json('data.orders'))->firstWhere('number', $pending->number)['state'])->toBe('PENDING_PAYMENT');
    $this->withHeader('Idempotency-Key', 'bank-line-assist')->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => $vs, 'amount' => $pending->total()->toDecimal(), 'currency' => 'CZK', 'external_id' => 'manual-'.$pending->number])->assertCreated();
    expect($pending->fresh()->state)->not->toBe('PENDING_PAYMENT');
});

it('pays an order with bonus credit when the purchased credit does not cover it (audit §5z)', function () {
    [$user, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ctx = $this->contextFor($user, $org);
    $wallets->topup($org, Money::decimal('50', 'CZK'), 'card', 'bought', $ctx, bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('500', 'CZK'), 'promo', 'bonus', $ctx, promo: true);
    expect($wallets->spendable($org, 'CZK')->minor)->toBe(55000);
    $this->actingAs($user, 'sanctum');
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start']], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $this->postJson('/v1/cart/quote')->assertOk();
    $order = $this->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => ['terms' => [], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => []], 'payment' => ['mode' => 'wallet']])->assertCreated();
    expect($order->json('state'))->toBe('PAID');
    $total = (int) $quote->json('data.total');
    $balances = $wallets->balances($org, 'CZK');
    expect($wallets->spendable($org, 'CZK')->minor)->toBe(55000 - $total)
        ->and($balances['promo']->minor)->toBe(55000 - $total - 5000 < 0 ? 0 : 50000 - ($total - 5000))
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(0); // the purchased 50 Kč went into the order first, the bonus is never refundable
});

it('places an assisted order once: the same request sent again is the same order, not a second one paid from the customer\'s credit', function () {
    [, $org] = $this->customerWithOrganization();
    $finance = $this->staff('platform_owner');
    $this->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'assist-once-seed', $this->contextFor($finance));
    $body = ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['domain' => 'jednou.cz']]], 'payment' => 'wallet', 'note' => 'telefonická objednávka, tiket #5120'];

    $first = $this->withHeader('Idempotency-Key', 'assist-once')->postJson("/v1/staff/customers/{$org->id}/orders", $body)->assertCreated();
    // A finished request is replayed by the HTTP layer. The bus has to recognise the request by itself when that layer has nothing
    // to replay — the process died after the order was committed and before the answer was stored, or the answer was a 5xx. The
    // second the request was sent in used to be part of the command key: the retry was a new command, and a second order was
    // placed and paid from the customer's credit.
    $this->travel(7)->seconds();
    DB::table('idempotency_keys')->where('key', 'like', 'http:%')->delete();
    $again = $this->withHeader('Idempotency-Key', 'assist-once')->postJson("/v1/staff/customers/{$org->id}/orders", $body);

    expect(Order::query()->where('organization_id', $org->id)->count())->toBe(1)->and($again->json('order_id'))->toBe($first->json('order_id'));
    $paid = Order::query()->where('organization_id', $org->id)->sole();
    expect(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(500000 - $paid->total_minor);
});
