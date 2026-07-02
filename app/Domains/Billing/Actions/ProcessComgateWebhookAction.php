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
use App\Domains\Billing\Services\Gateways\ComgateGateway;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent Comgate webhook processor.
 *
 * Flow:
 *  1. Log raw (sanitized) webhook.
 *  2. Verify source IP.
 *  3. Re-fetch status server-to-server (never trust the inbound body).
 *  4. UPSERT payment guarded by UNIQUE(gateway_transaction_id) —
 *     a duplicate webhook can never double-pay.
 *  5. Mark invoice paid exactly once (row lock + status check).
 */
final class ProcessComgateWebhookAction
{
    public function __construct(
        private readonly ComgateGateway $gateway,
    ) {}

    /** @param array<string, mixed> $payload */
    public function execute(array $payload, string $sourceIp): void
    {
        $log = PaymentWebhookLog::create([
            'provider'   => 'comgate',
            'event_id'   => $payload['transId'] ?? null,
            'payload'    => $this->sanitize($payload),
            'ip_address' => $sourceIp,
        ]);

        try {
            if (!$this->gateway->verifyWebhookSource($sourceIp)) {
                $log->update(['signature_valid' => false, 'error_message' => 'IP not whitelisted']);

                return;
            }

            $transId = $payload['transId'] ?? throw new \InvalidArgumentException('Missing transId');

            // Authoritative status — server-to-server.
            $status = $this->gateway->getStatus($transId);
            $log->update(['signature_valid' => true]);

            if (($status['status'] ?? null) !== 'PAID') {
                $log->update(['processed' => true, 'processed_at' => now(),
                    'error_message' => 'Status not PAID: ' . ($status['status'] ?? 'unknown')]);

                return;
            }

            $invoice = Invoice::where('uuid', $status['refId'] ?? '')->firstOrFail();

            DB::transaction(function () use ($invoice, $transId, $status): void {
                // Idempotency gate #1: unique transaction id.
                $existing = Payment::where('gateway_transaction_id', $transId)->lockForUpdate()->first();

                if ($existing?->isCompleted()) {
                    return; // duplicate webhook — safely ignored
                }

                $amount = Money::ofMinor((int) $status['price'], $status['curr']);

                // Idempotency gate #2: amount + currency must match the invoice.
                if (!$amount->isEqualTo($invoice->total)) {
                    throw new \RuntimeException(
                        "Comgate amount mismatch for invoice {$invoice->number}: expected {$invoice->total}, got {$amount}."
                    );
                }

                $payment = $existing ?? new Payment();
                $payment->fill([
                    'customer_id'             => $invoice->customer_id,
                    'invoice_id'              => $invoice->id,
                    'method'                  => PaymentMethod::Comgate,
                    'status'                  => PaymentStatus::Completed,
                    'amount'                  => $amount,
                    'gateway_transaction_id'  => $transId,
                    'gateway_response'        => $this->sanitize($status),
                    'processed_at'            => now(),
                ])->save();

                // Idempotency gate #3: invoice transitions to paid exactly once.
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
            $log->update(['error_message' => $e->getMessage()]);

            throw $e; // bubble up → queue retry / alerting
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        unset($data['secret'], $data['password'], $data['merchant']);

        return $data;
    }
}
