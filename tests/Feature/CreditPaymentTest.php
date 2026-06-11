<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\CreditLedger;
use Brick\Money\Money;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
    Queue::fake();
});

it('pays an invoice from credit through the append-only ledger', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    app(CreditLedger::class)->deposit($customer, Money::ofMinor(10_000, 'CZK'), 'Test deposit');

    ['invoice' => $invoice] = placeOrder($user); // total 5 929 minor

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-credit', $invoice))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);

    $payment = Payment::firstOrFail();
    expect($payment->method)->toBe(PaymentMethod::Credit)
        ->and($payment->status)->toBe(PaymentStatus::Completed)
        ->and($payment->gateway_transaction_id)->toBe('CREDIT-' . $invoice->uuid);

    // Balance changed ONLY through a ledger row: 10 000 − 5 929 = 4 071.
    expect(app(CreditLedger::class)->getBalance($customer->refresh())->getMinorAmount()->toInt())->toBe(4_071)
        ->and(CreditTransaction::count())->toBe(2)
        ->and(CreditTransaction::latest('id')->firstOrFail()->reference_id)->toBe($invoice->id);
});

it('never allows an overdraw', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    app(CreditLedger::class)->deposit($customer, Money::ofMinor(1_000, 'CZK'), 'Small deposit');

    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-credit', $invoice))
        ->assertRedirect()
        ->assertSessionHasErrors('payment');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Sent)
        ->and(Payment::where('status', PaymentStatus::Completed->value)->count())->toBe(0)
        ->and(CreditTransaction::count())->toBe(1) // deposit only — nothing was deducted
        ->and(app(CreditLedger::class)->getBalance($customer->refresh())->getMinorAmount()->toInt())->toBe(1_000);
});

it('does not deduct twice on a duplicate credit payment submit', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    app(CreditLedger::class)->deposit($customer, Money::ofMinor(20_000, 'CZK'), 'Deposit');

    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)->post(route('panel.billing.invoices.pay-credit', $invoice));
    $this->actingAs($user)->post(route('panel.billing.invoices.pay-credit', $invoice));

    expect(Payment::count())->toBe(1)
        ->and(CreditTransaction::where('type', 'deduction')->count())->toBe(1)
        ->and(app(CreditLedger::class)->getBalance($customer->refresh())->getMinorAmount()->toInt())->toBe(20_000 - 5_929);
});
