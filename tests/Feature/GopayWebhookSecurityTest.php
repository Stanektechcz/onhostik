<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\ProcessGopayWebhookAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentWebhookLog;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Http;

/**
 * GoPay never trusts the inbound body — it re-fetches the payment status
 * server-to-server. These guard the two things that re-fetch alone does NOT
 * protect: the reported amount must match the invoice, and credentials in
 * the gateway response must never be persisted.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/**
 * Fake the authoritative status GoPay would return. The gateway is final, so
 * we fake at the HTTP boundary (token call + payment lookup).
 */
function fakeGopayStatus(array $status): void
{
    Http::fake([
        '*/oauth2/token'      => Http::response(['access_token' => 'test-token', 'expires_in' => 3600], 200),
        '*/payments/payment/*' => Http::response($status, 200),
    ]);
}

it('marks the invoice paid when GoPay reports the exact invoice amount', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    fakeGopayStatus([
        'state'        => 'PAID',
        'order_number' => $invoice->number,
        'amount'       => $invoice->total->getMinorAmount()->toInt(),
        'currency'     => $invoice->total->getCurrency()->getCurrencyCode(),
    ]);

    app(ProcessGopayWebhookAction::class)->execute('gopay-1', '127.0.0.1');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(Payment::where('gateway_transaction_id', 'gopay-1')->exists())->toBeTrue();
});

it('refuses an underpayment instead of marking the invoice fully paid', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    fakeGopayStatus([
        'state'        => 'PAID',
        'order_number' => $invoice->number,
        'amount'       => 1, // 1 haléř for a whole hosting order
        'currency'     => $invoice->total->getCurrency()->getCurrencyCode(),
    ]);

    expect(fn () => app(ProcessGopayWebhookAction::class)->execute('gopay-short', '127.0.0.1'))
        ->toThrow(RuntimeException::class);

    expect($invoice->fresh()->status)->not->toBe(InvoiceStatus::Paid)
        ->and(Payment::where('gateway_transaction_id', 'gopay-short')->exists())->toBeFalse();
});

it('refuses a payment reported in a different currency', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    fakeGopayStatus([
        'state'        => 'PAID',
        'order_number' => $invoice->number,
        'amount'       => $invoice->total->getMinorAmount()->toInt(),
        'currency'     => 'USD', // invoice is CZK
    ]);

    // Brick\Money refuses to compare across currencies, so the mismatch can
    // never be silently coerced into "paid".
    expect(fn () => app(ProcessGopayWebhookAction::class)->execute('gopay-cur', '127.0.0.1'))
        ->toThrow(\Brick\Money\Exception\MoneyMismatchException::class);

    expect($invoice->fresh()->status)->not->toBe(InvoiceStatus::Paid)
        ->and(Payment::where('gateway_transaction_id', 'gopay-cur')->exists())->toBeFalse();
});

it('never persists gateway credentials from the response', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    fakeGopayStatus([
        'state'         => 'PAID',
        'order_number'  => $invoice->number,
        'amount'        => $invoice->total->getMinorAmount()->toInt(),
        'currency'      => $invoice->total->getCurrency()->getCurrencyCode(),
        'client_secret' => 'super-secret-value',
        'access_token'  => 'tok_live_leak',
    ]);

    app(ProcessGopayWebhookAction::class)->execute('gopay-secret', '127.0.0.1');

    $stored = Payment::where('gateway_transaction_id', 'gopay-secret')->firstOrFail();
    $raw    = json_encode($stored->gateway_response) ?: '';

    expect($raw)->not->toContain('super-secret-value')
        ->and($raw)->not->toContain('tok_live_leak');
});

it('ignores a notification whose state is not PAID', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    fakeGopayStatus(['state' => 'CANCELED', 'order_number' => $invoice->number]);

    app(ProcessGopayWebhookAction::class)->execute('gopay-cancel', '127.0.0.1');

    expect($invoice->fresh()->status)->not->toBe(InvoiceStatus::Paid)
        ->and(Payment::where('gateway_transaction_id', 'gopay-cancel')->exists())->toBeFalse();
});

it('is idempotent when GoPay resends the same notification', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    fakeGopayStatus([
        'state'        => 'PAID',
        'order_number' => $invoice->number,
        'amount'       => $invoice->total->getMinorAmount()->toInt(),
        'currency'     => $invoice->total->getCurrency()->getCurrencyCode(),
    ]);

    app(ProcessGopayWebhookAction::class)->execute('gopay-dup', '127.0.0.1');
    app(ProcessGopayWebhookAction::class)->execute('gopay-dup', '127.0.0.1');

    expect(Payment::where('gateway_transaction_id', 'gopay-dup')->count())->toBe(1);
});

it('logs every notification for audit', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    fakeGopayStatus(['state' => 'CANCELED', 'order_number' => $invoice->number]);

    app(ProcessGopayWebhookAction::class)->execute('gopay-log', '10.0.0.9');

    $log = PaymentWebhookLog::where('event_id', 'gopay-log')->firstOrFail();

    expect($log->provider)->toBe('gopay')
        ->and($log->ip_address)->toBe('10.0.0.9');
});
