<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Comgate initiation ────────────────────────────────────────────────────────

it('Comgate payment initiation redirects to gateway URL', function (): void {
    $user   = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    Http::fake([
        '*comgate*' => Http::response(
            'code=0&message=OK&transId=TEST-TRANS-001&redirect=https://payments.comgate.cz/v1.0/pay?id=TEST-TRANS-001',
            200,
        ),
    ]);

    config(['comgate.merchant_id' => 'test_merchant', 'comgate.secret' => 'test_secret']);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-comgate', $invoice))
        ->assertRedirect('https://payments.comgate.cz/v1.0/pay?id=TEST-TRANS-001');

    $payment = Payment::where('invoice_id', $invoice->id)
        ->where('method', PaymentMethod::Comgate->value)
        ->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status->value)->toBe(PaymentStatus::Pending->value)
        ->and($payment->gateway_transaction_id)->toBe('TEST-TRANS-001');
});

it('Comgate returns flash message on gateway error', function (): void {
    $user   = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    Http::fake([
        '*comgate*' => Http::response('code=1400&message=Invalid merchant', 200),
    ]);

    config(['comgate.merchant_id' => 'test_merchant', 'comgate.secret' => 'test_secret']);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-comgate', $invoice))
        ->assertRedirect()
        ->assertSessionHasErrors('payment');
});

it('Comgate return URL with paid status redirects to invoice', function (): void {
    $user   = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.billing.invoices.comgate-return', $invoice) . '?status=paid')
        ->assertRedirect(route('panel.billing.invoices.show', $invoice))
        ->assertSessionHas('status');
});

it('Comgate return URL with cancelled status sets error flash', function (): void {
    $user   = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.billing.invoices.comgate-return', $invoice) . '?status=cancelled')
        ->assertRedirect(route('panel.billing.invoices.show', $invoice))
        ->assertSessionHas('payment_failed');
});

// ── Stripe initiation ─────────────────────────────────────────────────────────

it('Stripe payment initiation redirects to Stripe checkout URL', function (): void {
    $user   = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    Http::fake([
        '*stripe*' => Http::response([
            'id'  => 'cs_test_FAKE123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_FAKE123',
        ], 200),
    ]);

    config(['stripe.api_key' => 'sk_test_fake', 'stripe.webhook_secret' => 'whsec_fake']);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-stripe', $invoice))
        ->assertRedirect('https://checkout.stripe.com/pay/cs_test_FAKE123');

    $payment = Payment::where('invoice_id', $invoice->id)
        ->where('method', PaymentMethod::Stripe->value)
        ->first();

    expect($payment)->not->toBeNull()
        ->and($payment->gateway_transaction_id)->toBe('cs_test_FAKE123');
});

it('Stripe return with cancelled status sets error flash', function (): void {
    $user   = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.billing.invoices.stripe-return', $invoice) . '?status=cancelled')
        ->assertRedirect(route('panel.billing.invoices.show', $invoice))
        ->assertSessionHas('payment_failed');
});

it('Stripe return with pending sets processing flash', function (): void {
    $user   = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.billing.invoices.stripe-return', $invoice))
        ->assertRedirect(route('panel.billing.invoices.show', $invoice))
        ->assertSessionHas('status');
});
