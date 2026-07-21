<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\AutoChargeSavedMethodAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Services\QrPaymentGenerator;
use App\Models\SavedPaymentMethod;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * D45 QR Platba (SPAYD) + D39 auto-charge from a saved payment method.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
    config(['billing.supplier.bank_account_czk' => 'CZ6508000000192000145399']);
});

// ── D45: SPAYD payload ────────────────────────────────────────────────────────

it('builds a SPAYD payload for an unpaid invoice', function (): void {
    ['invoice' => $invoice] = placeOrder(customerUser());

    $spayd = app(QrPaymentGenerator::class)->forInvoice($invoice);

    expect($spayd)->toStartWith('SPD*1.0*')
        ->and($spayd)->toContain('ACC:CZ6508000000192000145399')
        ->and($spayd)->toContain('CC:CZK')
        ->and($spayd)->toContain('X-VS:' . $invoice->variable_symbol);
});

it('strips spaces from the configured account number', function (): void {
    config(['billing.supplier.bank_account_czk' => 'CZ65 0800 0000 1920 0014 5399']);
    ['invoice' => $invoice] = placeOrder(customerUser());

    // A space inside ACC makes the payload unscannable in most bank apps.
    expect(app(QrPaymentGenerator::class)->forInvoice($invoice))
        ->toContain('ACC:CZ6508000000192000145399');
});

it('returns nothing for an already paid invoice', function (): void {
    ['invoice' => $invoice] = placeOrder(customerUser());
    $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

    // Printing a payable QR on a paid invoice invites a duplicate payment.
    expect(app(QrPaymentGenerator::class)->forInvoice($invoice->fresh()))->toBeNull();
});

it('returns nothing when no account is configured for the currency', function (): void {
    config(['billing.supplier.bank_account_czk' => null]);
    ['invoice' => $invoice] = placeOrder(customerUser());

    // Better no QR than one that scans to an empty account.
    expect(app(QrPaymentGenerator::class)->forInvoice($invoice))->toBeNull();
});

it('never lets a stray asterisk split the payload', function (): void {
    ['invoice' => $invoice] = placeOrder(customerUser());
    $invoice->update(['number' => 'CZ-2026-0001*EVIL']);

    $spayd = app(QrPaymentGenerator::class)->forInvoice($invoice->fresh());

    // `*` is the SPAYD field separator — unescaped it would inject fields and
    // could redirect the payment.
    $msgSegment = collect(explode('*', (string) $spayd))->first(fn ($s) => str_starts_with($s, 'MSG:'));
    expect($msgSegment)->not->toContain('EVIL*');
});

it('renders the payment block on an unpaid invoice', function (): void {
    ['invoice' => $invoice] = placeOrder(customerUser());

    // Rendered from the template rather than the print route, which returns a
    // PDF binary that assertSee cannot read.
    $html = view('pdf.invoice', ['invoice' => $invoice->fresh()->load('items'), 'isPdf' => true])->render();

    expect($html)->toContain('CZ6508000000192000145399')
        ->and($html)->toContain('data-spayd="SPD*1.0*');
});

it('omits the payment block once the invoice is paid', function (): void {
    ['invoice' => $invoice] = placeOrder(customerUser());
    $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

    $html = view('pdf.invoice', ['invoice' => $invoice->fresh()->load('items'), 'isPdf' => true])->render();

    // Showing payment details on a settled invoice invites a duplicate payment.
    expect($html)->not->toContain('data-spayd');
});

// ── D39: auto-charge ──────────────────────────────────────────────────────────

it('skips a customer with no saved payment method', function (): void {
    ['invoice' => $invoice] = placeOrder(customerUser());

    // Auto-pay is opt-in — nobody gets charged for storing nothing.
    expect(app(AutoChargeSavedMethodAction::class)->execute($invoice))
        ->toBe('skipped_no_method');
});

it('charges an open renewal invoice from the default saved method', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    SavedPaymentMethod::create([
        'customer_id' => $user->customer->id,
        'provider'    => 'stripe',
        'label'       => 'Visa …4242',
        'last4'       => '4242',
        'is_default'  => true,
    ]);

    expect(app(AutoChargeSavedMethodAction::class)->execute($invoice))->toBe('charged')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('never charges an invoice twice', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    SavedPaymentMethod::create([
        'customer_id' => $user->customer->id,
        'provider'    => 'stripe', 'label' => 'Visa', 'last4' => '4242', 'is_default' => true,
    ]);

    app(AutoChargeSavedMethodAction::class)->execute($invoice);

    // This runs on a schedule and WILL be retried — the second pass must be
    // a no-op, not a second charge.
    expect(app(AutoChargeSavedMethodAction::class)->execute($invoice->fresh()))
        ->toBe('skipped_not_payable');
});

it('declines rather than faking success when no real recurring API exists', function (): void {
    config(['provisioning.mock_mode' => false]);

    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    SavedPaymentMethod::create([
        'customer_id' => $user->customer->id,
        'provider'    => 'stripe', 'label' => 'Visa', 'last4' => '4242', 'is_default' => true,
    ]);

    // Marking it paid here would book revenue that never arrived.
    expect(app(AutoChargeSavedMethodAction::class)->execute($invoice))->toBe('declined')
        ->and($invoice->fresh()->status)->not->toBe(InvoiceStatus::Paid);
});

it('leaves the stored token out of the audit trail', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    SavedPaymentMethod::create([
        'customer_id' => $user->customer->id,
        'provider'    => 'stripe', 'label' => 'Visa',
        'token'       => 'tok_super_secret_value', 'last4' => '4242', 'is_default' => true,
    ]);

    app(AutoChargeSavedMethodAction::class)->execute($invoice);

    $activity = \Spatie\Activitylog\Models\Activity::where('description', 'invoice.auto_charged')->firstOrFail();

    expect(json_encode($activity->properties))->not->toContain('tok_super_secret_value')
        ->and($activity->properties['last4'])->toBe('4242');
});
