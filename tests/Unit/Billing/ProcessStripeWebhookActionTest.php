<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\ProcessStripeWebhookAction;
use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentWebhookLog;
use App\Domains\Billing\Services\Gateways\StripeGateway;
use App\Domains\Customer\Models\Customer;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const STRIPE_TEST_SECRET = 'whsec_test_stripe_gateway_action';

function makeStripeGateway(): StripeGateway
{
    return new StripeGateway(
        apiKey:        'sk_test_dummy',
        webhookSecret: STRIPE_TEST_SECRET,
        baseUrl:       'https://api.stripe.com/v1',
    );
}

function makeStripeAction(): ProcessStripeWebhookAction
{
    return new ProcessStripeWebhookAction(makeStripeGateway());
}

/** Creates an open (Sent) invoice for the given customer. */
function sentStripeInvoice(Customer $customer, int $totalMinor = 10_000): Invoice
{
    $subtotal = (int) round($totalMinor / 1.21);
    $tax      = $totalMinor - $subtotal;

    return Invoice::create([
        'customer_id'          => $customer->id,
        'type'                 => InvoiceType::Invoice,
        'purpose'              => 'order',
        'series'               => InvoiceSeries::Czech->value,
        'number'               => 'CZ-2026-WH0001',
        'status'               => InvoiceStatus::Sent,
        'vat_scenario'         => VatScenario::CzechB2C,
        'currency'             => 'CZK',
        'subtotal'             => Money::ofMinor($subtotal, 'CZK'),
        'tax_amount'           => Money::ofMinor($tax, 'CZK'),
        'total'                => Money::ofMinor($totalMinor, 'CZK'),
        'variable_symbol'      => '20260002',
        'issue_date'           => now()->toDateString(),
        'taxable_supply_date'  => now()->toDateString(),
        'due_date'             => now()->addDays(14)->toDateString(),
        'snapshot_name'        => 'Test Zákazník',
        'snapshot_company'     => 'Test s.r.o.',
        'snapshot_street'      => 'Testovací 1',
        'snapshot_city'        => 'Praha',
        'snapshot_zip'         => '11000',
        'snapshot_country_code' => 'CZ',
    ]);
}

function buildStripeEvent(Invoice $invoice, string $paymentIntentId = 'pi_stripe_test', ?int $overrideAmount = null): string
{
    $amount = $overrideAmount ?? $invoice->total->getMinorAmount()->toInt();

    return json_encode([
        'id'   => 'evt_stripe_test_001',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id'             => 'cs_test_abc',
                'payment_status' => 'paid',
                'payment_intent' => $paymentIntentId,
                'amount_total'   => $amount,
                'currency'       => 'czk',
                'metadata'       => ['invoice_uuid' => $invoice->uuid],
            ],
        ],
    ]);
}

function buildStripeSig(string $payload): string
{
    $ts  = time();
    $sig = hash_hmac('sha256', "{$ts}.{$payload}", STRIPE_TEST_SECRET);

    return "t={$ts},v1={$sig}";
}

// ───────── happy path ─────────

it('marks the invoice as paid on checkout.session.completed with payment_status=paid', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer);
    $payload  = buildStripeEvent($invoice);

    makeStripeAction()->execute($payload, buildStripeSig($payload), '185.93.0.1');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('creates a completed Payment record with method=stripe', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer, 5_000);
    $payload  = buildStripeEvent($invoice, 'pi_uniq_001');

    makeStripeAction()->execute($payload, buildStripeSig($payload), '185.93.0.1');

    $payment = Payment::where('gateway_transaction_id', 'pi_uniq_001')->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Completed)
        ->and($payment->method->value)->toBe('stripe')
        ->and($payment->amount->getMinorAmount()->toInt())->toBe(5_000);
});

it('logs the webhook to payment_webhook_logs with processed=true', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer);
    $payload  = buildStripeEvent($invoice);

    makeStripeAction()->execute($payload, buildStripeSig($payload), '1.2.3.4');

    $log = PaymentWebhookLog::where('provider', 'stripe')->firstOrFail();

    expect($log->processed)->toBeTrue()
        ->and($log->signature_valid)->toBeTrue()
        ->and($log->ip_address)->toBe('1.2.3.4');
});

// ───────── idempotency ─────────

it('is idempotent — duplicate webhook does not create a second Payment', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer);
    $payload  = buildStripeEvent($invoice, 'pi_idempotent_001');
    $sig      = buildStripeSig($payload);

    makeStripeAction()->execute($payload, $sig, '1.2.3.4');
    makeStripeAction()->execute($payload, buildStripeSig($payload), '1.2.3.4');

    expect(Payment::where('gateway_transaction_id', 'pi_idempotent_001')->count())->toBe(1);
});

it('does not change invoice status on a duplicate webhook', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer);
    $payload  = buildStripeEvent($invoice, 'pi_idempotent_002');

    makeStripeAction()->execute($payload, buildStripeSig($payload), '1.2.3.4');
    makeStripeAction()->execute($payload, buildStripeSig($payload), '1.2.3.4');

    // Must remain Paid, not reset to some error state
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

// ───────── non-payment events ─────────

it('skips events other than checkout.session.completed', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer);

    $otherPayload = json_encode([
        'id'   => 'evt_other',
        'type' => 'customer.created',
        'data' => ['object' => []],
    ]);

    makeStripeAction()->execute($otherPayload, buildStripeSig($otherPayload), '1.2.3.4');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent) // unchanged
        ->and(Payment::count())->toBe(0);
});

it('skips checkout.session.completed when payment_status is not paid', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer);

    $unpaidPayload = json_encode([
        'id'   => 'evt_unpaid',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id'             => 'cs_test_unpaid',
                'payment_status' => 'unpaid',
                'payment_intent' => 'pi_unpaid',
                'amount_total'   => $invoice->total->getMinorAmount()->toInt(),
                'currency'       => 'czk',
                'metadata'       => ['invoice_uuid' => $invoice->uuid],
            ],
        ],
    ]);

    makeStripeAction()->execute($unpaidPayload, buildStripeSig($unpaidPayload), '1.2.3.4');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

// ───────── error cases ─────────

it('throws and logs the error when the webhook signature is invalid', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer);
    $payload  = buildStripeEvent($invoice);

    $badSig = 't=' . time() . ',v1=' . str_repeat('0', 64); // wrong hash

    expect(fn () => makeStripeAction()->execute($payload, $badSig, '9.9.9.9'))
        ->toThrow(RuntimeException::class);

    $log = PaymentWebhookLog::where('provider', 'stripe')->firstOrFail();
    expect($log->signature_valid)->toBeFalse()
        ->and($log->error_message)->not->toBeNull();
});

it('throws and logs on amount mismatch between event and invoice', function (): void {
    $user     = \App\Models\User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice  = sentStripeInvoice($customer, 10_000);

    $payload = buildStripeEvent($invoice, 'pi_mismatch', 9_999); // 1 CZK short
    $sig     = buildStripeSig($payload);

    expect(fn () => makeStripeAction()->execute($payload, $sig, '1.2.3.4'))
        ->toThrow(RuntimeException::class, 'amount mismatch');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent); // not marked paid
});
