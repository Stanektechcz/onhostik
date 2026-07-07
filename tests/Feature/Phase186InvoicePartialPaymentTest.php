<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\InvoicePartialPaymentController;
use App\Models\InvoicePartialPayment;

test('invoice partial payment controller exists', function (): void {
    expect(class_exists(InvoicePartialPaymentController::class))->toBeTrue();
});

test('invoice partial payment model exists', function (): void {
    expect(class_exists(InvoicePartialPayment::class))->toBeTrue();
});

test('invoice partial payments table exists', function (): void {
    expect(\Schema::hasTable('invoice_partial_payments'))->toBeTrue();
});

test('invoice partial payment index route exists', function (): void {
    expect(Route::has('admin.invoice-partial-payments.index'))->toBeTrue();
});

test('invoice partial payment store requires amount_haler', function (): void {
    $admin = adminUser();
    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create();
    $response = $this->actingAs($admin)->post(route('admin.invoice-partial-payments.store', $invoice), [
        'payment_method' => 'cash',
        'paid_at'        => now()->toDateString(),
    ]);
    $response->assertSessionHasErrors('amount_haler');
});
