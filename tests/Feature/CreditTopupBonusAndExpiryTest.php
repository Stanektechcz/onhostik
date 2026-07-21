<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CreateCreditTopUpInvoiceAction;
use App\Domains\Billing\Actions\PayInvoiceWithCreditAction;
use App\Domains\Billing\Enums\CreditTransactionType;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Services\CreditLedger;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Credit top-up limits and volume bonuses (audit D58) and credit expiry
 * (D59).
 *
 * The bonus is booked as its own ledger entry so promotional credit stays
 * distinguishable from the customer's own money.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** Pay a top-up invoice of the given minor amount and return the customer. */
function payTopUp(int $minor): \App\Domains\Customer\Models\Customer
{
    $user     = customerUser();
    $customer = $user->customer;

    // Fund the wallet so the top-up proforma itself can be settled, then
    // subtract that funding from the assertions via the returned customer.
    app(CreditLedger::class)->deposit($customer, Money::ofMinor($minor, 'CZK'), 'Test funding');

    $invoice = app(CreateCreditTopUpInvoiceAction::class)->execute($customer, Money::ofMinor($minor, 'CZK'));
    app(PayInvoiceWithCreditAction::class)->execute($invoice);

    return $customer->fresh();
}

// ── D58: top-up limits ────────────────────────────────────────────────────────

it('refuses a top-up below the configured minimum', function (): void {
    $customer = customerUser()->customer;

    expect(fn () => app(CreateCreditTopUpInvoiceAction::class)
        ->execute($customer, Money::ofMinor(1, 'CZK')))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a top-up above the configured maximum', function (): void {
    $customer = customerUser()->customer;

    expect(fn () => app(CreateCreditTopUpInvoiceAction::class)
        ->execute($customer, Money::ofMinor(99_000_000, 'CZK')))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an out-of-range top-up from the panel', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->from(route('panel.billing.credits'))
        ->post(route('panel.billing.credits.topup'), ['amount' => 0])
        ->assertSessionHasErrors('amount');
});

// ── D58: volume bonus ─────────────────────────────────────────────────────────

it('grants no bonus below the lowest band', function (): void {
    config()->set('billing.credit_topup.bonus_tiers', [
        ['min_minor' => 100_000, 'percent' => 2.0],
    ]);

    $customer = payTopUp(50_000); // under the 1 000 CZK band

    expect(CreditTransaction::where('customer_id', $customer->id)
        ->where('type', CreditTransactionType::Deposit->value)
        ->where('description', 'like', 'Bonus%')
        ->count())->toBe(0);
});

it('grants the matching bonus band on a paid top-up', function (): void {
    config()->set('billing.credit_topup.bonus_tiers', [
        ['min_minor' => 100_000, 'percent' => 10.0],
    ]);

    $customer = payTopUp(100_000); // exactly on the band

    $bonus = CreditTransaction::where('customer_id', $customer->id)
        ->where('description', 'like', 'Bonus%')
        ->first();

    expect($bonus)->not->toBeNull()
        ->and($bonus->amount->getMinorAmount()->toInt())->toBe(10_000); // 10 % of 100 000
});

it('applies only the highest matching band', function (): void {
    config()->set('billing.credit_topup.bonus_tiers', [
        ['min_minor' => 100_000, 'percent' => 2.0],
        ['min_minor' => 500_000, 'percent' => 7.0],
    ]);

    $customer = payTopUp(500_000);

    $bonuses = CreditTransaction::where('customer_id', $customer->id)
        ->where('description', 'like', 'Bonus%')
        ->get();

    // One entry only, at the 7 % rate — bands must not stack.
    expect($bonuses)->toHaveCount(1)
        ->and($bonuses->first()->amount->getMinorAmount()->toInt())->toBe(35_000);
});

it('grants no bonus when the feature is switched off', function (): void {
    config()->set('billing.credit_topup.bonus_tiers', []);

    $customer = payTopUp(1_000_000);

    expect(CreditTransaction::where('customer_id', $customer->id)
        ->where('description', 'like', 'Bonus%')
        ->count())->toBe(0);
});

// ── D59: credit expiry ────────────────────────────────────────────────────────

it('removes expired credit from the balance', function (): void {
    $customer = customerUser()->customer;
    $ledger   = app(CreditLedger::class);

    $ledger->deposit($customer, Money::ofMinor(100_000, 'CZK'), 'Promo kredit');
    expect($ledger->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(100_000);

    $ledger->expire($customer->fresh(), Money::ofMinor(100_000, 'CZK'), 'Expirace promo kreditu');

    expect($ledger->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(0);
});

it('writes expiry as its own negative ledger entry', function (): void {
    $customer = customerUser()->customer;
    $ledger   = app(CreditLedger::class);

    $ledger->deposit($customer, Money::ofMinor(50_000, 'CZK'), 'Kredit');
    $ledger->expire($customer->fresh(), Money::ofMinor(20_000, 'CZK'), 'Částečná expirace');

    $expiry = CreditTransaction::where('customer_id', $customer->id)
        ->where('type', CreditTransactionType::Expiry->value)
        ->firstOrFail();

    // Auditability: the expiry must be visible in history, not a silent edit.
    expect($expiry->amount->getMinorAmount()->toInt())->toBe(-20_000)
        ->and($ledger->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(30_000);
});

it('keeps the credit ledger append-only', function (): void {
    $customer = customerUser()->customer;
    $tx       = app(CreditLedger::class)->deposit($customer, Money::ofMinor(10_000, 'CZK'), 'Kredit');

    // Rewriting history would let a balance be changed without a trace.
    expect(fn () => $tx->update(['description' => 'upraveno']))
        ->toThrow(RuntimeException::class);
});

it('expires deposits whose expires_at has passed via the scheduled command', function (): void {
    $customer = customerUser()->customer;
    $ledger   = app(CreditLedger::class);

    $tx = $ledger->deposit($customer, Money::ofMinor(80_000, 'CZK'), 'Kredit s expirací');

    // The ledger model forbids UPDATE, so simulate a deposit that carried an
    // expiry at write time by setting it underneath Eloquent.
    \Illuminate\Support\Facades\DB::table('credit_transactions')
        ->where('id', $tx->id)
        ->update(['expires_at' => now()->subDay()]);

    $this->artisan('billing:expire-credit')->assertSuccessful();

    expect($ledger->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(0);
});
