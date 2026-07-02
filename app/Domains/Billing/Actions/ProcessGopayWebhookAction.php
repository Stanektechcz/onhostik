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
use App\Domains\Billing\Services\Gateways\GopayGateway;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent GoPay IPN (notification) processor.
 *
 * GoPay POSTs to our notify_url with ?id={payment_id}.
 * We re-fetch status server-to-server (never trust inbound body alone).
 * Idempotency is enforced by UNIQUE(gateway_transaction_id) on payments.
 */
final class ProcessGopayWebhookAction
{
    public function __construct(
        private readonly GopayGateway $gateway,
    ) {}

    public function execute(string $paymentId, string $sourceIp): void
    {
        $log = PaymentWebhookLog::create([
            'provider'   => 'gopay',
            'event_id'   => $paymentId,
            'payload'    => ['payment_id' => $paymentId],
            'ip_address' => $sourceIp,
        ]);

        try {
            $status = $this->gateway->getPaymentStatus($paymentId);
            $log->update(['signature_valid' => true]);

            if (($status['state'] ?? null) !== 'PAID') {
                $log->update([
                    'processed'     => true,
                    'processed_at'  => now(),
                    'error_message' => 'State not PAID: ' . ($status['state'] ?? 'unknown'),
                ]);

                return;
            }

            $invoice = Invoice::where('number', $status['order_number'] ?? '')->firstOrFail();

            DB::transaction(function () use ($invoice, $paymentId, $status): void {
                $existing = Payment::where('gateway_transaction_id', $paymentId)->lockForUpdate()->first();

                if ($existing?->isCompleted()) {
                    return;
                }

                $amount = Money::ofMinor((int) ($status['amount'] ?? 0), $status['currency'] ?? 'CZK');

                $payment = $existing ?? new Payment();
                $payment->fill([
                    'customer_id'            => $invoice->customer_id,
                    'invoice_id'             => $invoice->id,
                    'method'                 => PaymentMethod::GoPay,
                    'status'                 => PaymentStatus::Completed,
                    'amount'                 => $amount,
                    'gateway_transaction_id' => $paymentId,
                    'gateway_response'       => $status,
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
            $log->update(['error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
