<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\RefundPaymentAction;
use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Enums\RefundDestination;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\CreditLedger;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Credit notes (audit D55) and refunds (D56).
 *
 * No gateway here exposes a refund API, so a refund to the original method
 * is recorded as an obligation for the operator. A refund to credit is
 * settled immediately. The point of these tests is that the two are never
 * confused — money must never be silently assumed returned.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function completedPayment(): Payment
{
    $invoice = placeOrder(customerUser())['invoice'];

    return Payment::create([
        'customer_id'            => $invoice->customer_id,
        'invoice_id'             => $invoice->id,
        'method'                 => PaymentMethod::Comgate,
        'status'                 => PaymentStatus::Completed,
        'amount'                 => $invoice->total,
        'gateway_transaction_id' => 'tx-' . uniqid(),
        'processed_at'           => now(),
    ]);
}

// ── D55: credit note ──────────────────────────────────────────────────────────

/** A paid tax document — the only thing a credit note may be issued against. */
function paidTaxInvoice(): \App\Domains\Billing\Models\Invoice
{
    $user = customerUser();

    $user->customer->addresses()->create([
        'type' => 'billing', 'street' => 'Krátká 2', 'city' => 'Brno', 'zip' => '60200',
        'country_code' => 'CZ', 'is_primary' => true,
    ]);

    $proforma = placeOrder($user)['invoice'];

    app(CreditLedger::class)->deposit(
        $user->customer,
        \Brick\Money\Money::of(500_000, $user->customer->preferred_currency->value),
        'Test topup',
    );
    app(\App\Domains\Billing\Actions\PayInvoiceWithCreditAction::class)->execute($proforma);

    return app(\App\Domains\Billing\Actions\IssueTaxDocumentAction::class)->execute($proforma->fresh());
}

it('lets an admin issue a credit note against a paid invoice', function (): void {
    $invoice = paidTaxInvoice();

    $this->actingAs(adminUser())
        ->post(route('admin.invoices.credit-note', $invoice), ['reason' => 'Chybná fakturace'])
        ->assertRedirect();

    // The credit note is its own document in the CN series.
    expect(\App\Domains\Billing\Models\Invoice::where('number', 'like', 'CN-%')->count())->toBe(1);
});

it('refuses to credit an unpaid proforma', function (): void {
    $proforma = placeOrder(customerUser())['invoice'];

    $this->actingAs(adminUser())
        ->from(route('admin.invoices.show', $proforma))
        ->post(route('admin.invoices.credit-note', $proforma), ['reason' => 'předčasně'])
        ->assertSessionHasErrors('invoice');

    expect(\App\Domains\Billing\Models\Invoice::where('number', 'like', 'CN-%')->count())->toBe(0);
});

it('forbids a customer from issuing a credit note', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    $this->actingAs(customerUser())
        ->post(route('admin.invoices.credit-note', $invoice), ['reason' => 'chci slevu'])
        ->assertForbidden();
});

// ── D56: refunds ──────────────────────────────────────────────────────────────

it('credits the customer immediately when refunding to credit', function (): void {
    $payment  = completedPayment();
    $customer = $payment->customer;
    $before   = app(CreditLedger::class)->getBalance($customer)->getMinorAmount()->toInt();

    app(RefundPaymentAction::class)->execute($payment, adminUser(), 'Zrušená služba', RefundDestination::Credit);

    $after = app(CreditLedger::class)->getBalance($customer->fresh())->getMinorAmount()->toInt();

    expect($after - $before)->toBe($payment->amount->getMinorAmount()->toInt())
        ->and($payment->fresh()->refund_destination)->toBe(RefundDestination::Credit);
});

it('does not move any money when refunding to the original method', function (): void {
    $payment  = completedPayment();
    $customer = $payment->customer;
    $before   = app(CreditLedger::class)->getBalance($customer)->getMinorAmount()->toInt();

    app(RefundPaymentAction::class)->execute($payment, adminUser(), 'Vratka na kartu', RefundDestination::OriginalMethod);

    $after = app(CreditLedger::class)->getBalance($customer->fresh())->getMinorAmount()->toInt();

    // The operator still has to refund in the gateway dashboard — we must not
    // credit the customer as well, or they would be paid back twice.
    expect($after)->toBe($before)
        ->and($payment->fresh()->refund_destination)->toBe(RefundDestination::OriginalMethod)
        ->and($payment->fresh()->refund_destination->requiresManualAction())->toBeTrue();
});

it('records the refund reason and timestamp', function (): void {
    $payment = completedPayment();

    app(RefundPaymentAction::class)->execute($payment, adminUser(), 'Duplicitní platba', RefundDestination::Credit);

    $fresh = $payment->fresh();

    expect($fresh->status)->toBe(PaymentStatus::ManualRefund)
        ->and($fresh->refund_reason)->toBe('Duplicitní platba')
        ->and($fresh->refunded_at)->not->toBeNull();
});

it('refuses to refund a payment that was never completed', function (): void {
    $payment = completedPayment();
    $payment->update(['status' => PaymentStatus::Pending]);

    expect(fn () => app(RefundPaymentAction::class)->execute($payment, adminUser(), 'omyl', RefundDestination::Credit))
        ->toThrow(InvalidArgumentException::class);
});

it('never marks a refund as gateway-confirmed', function (): void {
    $payment = completedPayment();

    app(RefundPaymentAction::class)->execute($payment, adminUser(), 'test', RefundDestination::OriginalMethod);

    // Refunded is reserved for a real gateway confirmation we cannot make.
    expect($payment->fresh()->status)->not->toBe(PaymentStatus::Refunded);
});

it('lets an admin refund to credit from the payments screen', function (): void {
    $payment = completedPayment();

    $this->actingAs(adminUser())
        ->post(route('admin.payments.refund', $payment), [
            'reason'      => 'Zrušená objednávka',
            'destination' => RefundDestination::Credit->value,
        ])
        ->assertRedirect();

    expect($payment->fresh()->refund_destination)->toBe(RefundDestination::Credit);
});

it('defaults to the original method when no destination is chosen', function (): void {
    $payment = completedPayment();

    $this->actingAs(adminUser())
        ->post(route('admin.payments.refund', $payment), ['reason' => 'Bez volby'])
        ->assertRedirect();

    // Defaulting to credit would hand out money the operator did not intend.
    expect($payment->fresh()->refund_destination)->toBe(RefundDestination::OriginalMethod);
});

it('rejects an unknown refund destination', function (): void {
    $payment = completedPayment();

    $this->actingAs(adminUser())
        ->from(route('admin.payments.index'))
        ->post(route('admin.payments.refund', $payment), [
            'reason'      => 'test',
            'destination' => 'bitcoin',
        ])
        ->assertSessionHasErrors('destination');
});
