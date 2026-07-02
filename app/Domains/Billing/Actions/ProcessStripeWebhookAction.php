<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentWebhookLog;
use App\Domains\Billing\Services\Gateways\StripeGateway;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent Stripe webhook processor.
 *
 * Handled events:
 *  - checkout.session.completed (payment_status=paid)
 *
 * Same idempotency strategy as ProcessComgateWebhookAction:
 *  1. Log raw (sanitized) webhook.
 *  2. Verify HMAC-SHA256 signature on the raw payload.
 *  3. UPSERT payment guarded by UNIQUE(gateway_transaction_id).
 *  4. Mark invoice paid exactly once (row lock + status check).
 *
 * Security: api_key / webhook_secret are never logged or serialized.
 */
final class ProcessStripeWebhookAction
{
    public function __construct(
        private readonly StripeGateway $gateway,
    ) {}

    /**
     * @param  string  $rawPayload  raw request body (before any decoding)
     * @param  string  $sigHeader   value of the `Stripe-Signature` HTTP header
     */
    public function execute(string $rawPayload, string $sigHeader, string $sourceIp): void
    {
        $log = PaymentWebhookLog::create([
            'provider'   => 'stripe',
            'event_id'   => null,
            'payload'    => ['raw_length' => strlen($rawPayload), 'source_ip' => $sourceIp],
            'ip_address' => $sourceIp,
        ]);

        try {
            $event = $this->gateway->constructEvent($rawPayload, $sigHeader);

            $log->update([
                'signature_valid' => true,
                'event_id'        => $event['id'] ?? null,
                'payload'         => $this->sanitize($event),
            ]);

            // Only process checkout.session.completed with payment_status=paid
            if ($event['type'] !== 'checkout.session.completed') {
                $log->update(['processed' => true, 'processed_at' => now(),
                    'error_message' => "Skipped event type: {$event['type']}",
                ]);

                return;
            }

            $session = $event['data']['object'] ?? [];

            if (($session['payment_status'] ?? null) !== 'paid') {
                $log->update(['processed' => true, 'processed_at' => now(),
                    'error_message' => 'payment_status is not paid: ' . ($session['payment_status'] ?? 'unknown'),
                ]);

                return;
            }

            $invoiceUuid = $session['metadata']['invoice_uuid'] ?? ($session['client_reference_id'] ?? null);
            $invoice     = Invoice::where('uuid', $invoiceUuid)->firstOrFail();
            $sessionId   = $session['id'] ?? throw new \InvalidArgumentException('Missing Checkout Session id');
            $paymentIntentId = $session['payment_intent'] ?? $sessionId;

            DB::transaction(function () use ($invoice, $paymentIntentId, $session): void {
                $existing = Payment::where('gateway_transaction_id', $paymentIntentId)
                    ->lockForUpdate()->first();

                if ($existing?->isCompleted()) {
                    return; // duplicate webhook — safely ignored
                }

                $amountTotal = (int) ($session['amount_total'] ?? 0);
                $currency    = mb_strtoupper((string) ($session['currency'] ?? 'CZK'));
                $amount      = Money::ofMinor($amountTotal, $currency);

                if (! $amount->isEqualTo($invoice->total)) {
                    throw new \RuntimeException(
                        "Stripe amount mismatch for invoice {$invoice->number}: "
                        . "expected {$invoice->total}, got {$amount}."
                    );
                }

                $payment = $existing ?? new Payment();
                $payment->fill([
                    'customer_id'            => $invoice->customer_id,
                    'invoice_id'             => $invoice->id,
                    'method'                 => PaymentMethod::Stripe,
                    'status'                 => PaymentStatus::Completed,
                    'amount'                 => $amount,
                    'gateway_transaction_id' => $paymentIntentId,
                    'gateway_response'       => ['session_id' => $session['id'] ?? null],
                    'processed_at'           => now(),
                ])->save();

                $lockedInvoice = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

                if ($lockedInvoice->status !== InvoiceStatus::Paid) {
                    $lockedInvoice->update([
                        'status'  => InvoiceStatus::Paid,
                        'paid_at' => now(),
                    ]);

                    event(new InvoicePaid($lockedInvoice, $payment));
                }
            });

            $log->update(['processed' => true, 'processed_at' => now()]);
        } catch (\Throwable $e) {
            $log->update([
                'signature_valid' => $log->signature_valid ?? false,
                'error_message'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        // Remove any sensitive keys that could appear in Stripe events
        array_walk_recursive($data, function (mixed &$value, string $key): void {
            if (in_array($key, ['number', 'cvc', 'exp_month', 'exp_year'], true)) {
                $value = '[REDACTED]';
            }
        });

        return $data;
    }
}
