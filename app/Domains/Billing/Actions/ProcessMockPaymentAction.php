<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * MOCK payment provider — simulates a gateway payment WITHOUT any external
 * call, while exercising the exact same idempotency gates and event path
 * as the real Comgate webhook processor (ProcessComgateWebhookAction):
 *
 *  gate #1  unique gateway_transaction_id (deterministic per invoice here,
 *           so a double-submit can never create a second completed payment)
 *  gate #2  amount always equals the invoice total (mock pays the invoice)
 *  gate #3  the invoice transitions to Paid exactly once under row lock,
 *           firing InvoicePaid exactly once
 *
 * Downstream (order transition, provisioning, domain registration) listens
 * to InvoicePaid — the same event the real gateway will fire later.
 */
final class ProcessMockPaymentAction
{
    public function execute(Invoice $invoice, bool $simulateFailure = false): Payment
    {
        $transactionId = 'MOCK-' . $invoice->uuid;

        if ($invoice->status === InvoiceStatus::Paid) {
            $existing = Payment::where('gateway_transaction_id', $transactionId)->first();

            if ($existing !== null) {
                return $existing; // duplicate submit after success — no-op
            }
        }

        if (!$invoice->status->isOpen() && $invoice->status !== InvoiceStatus::Paid) {
            throw new InvalidArgumentException(
                "Invoice [{$invoice->number}] is not payable (status: {$invoice->status->value})."
            );
        }

        if ($simulateFailure) {
            return $this->recordFailedAttempt($invoice, $transactionId);
        }

        $payment = DB::transaction(function () use ($invoice, $transactionId): Payment {
            // Gate #1 — deterministic transaction id under row lock.
            $existing = Payment::where('gateway_transaction_id', $transactionId)
                ->lockForUpdate()
                ->first();

            if ($existing?->isCompleted()) {
                return $existing;
            }

            $payment = $existing ?? new Payment();
            $payment->fill([
                'customer_id'            => $invoice->customer_id,
                'invoice_id'             => $invoice->id,
                'method'                 => PaymentMethod::Comgate,
                'status'                 => PaymentStatus::Completed,
                'amount'                 => $invoice->total, // gate #2 by construction
                'gateway_transaction_id' => $transactionId,
                'gateway_response'       => ['mock' => true, 'outcome' => 'paid'],
                'processed_at'           => now(),
            ])->save();

            // Gate #3 — pay the invoice exactly once.
            $lockedInvoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status !== InvoiceStatus::Paid) {
                $lockedInvoice->update([
                    'status'  => InvoiceStatus::Paid,
                    'paid_at' => now(),
                ]);

                event(new InvoicePaid($lockedInvoice, $payment));
            }

            return $payment;
        });

        activity('payment')
            ->performedOn($payment)
            ->causedBy($invoice->customer?->user)
            ->withProperties([
                'invoice_id' => $invoice->id,
                'mock'       => true,
                'outcome'    => 'paid',
            ])
            ->log('payment.completed');

        return $payment;
    }

    private function recordFailedAttempt(Invoice $invoice, string $transactionId): Payment
    {
        $payment = DB::transaction(function () use ($invoice, $transactionId): Payment {
            $existing = Payment::where('gateway_transaction_id', $transactionId)
                ->lockForUpdate()
                ->first();

            if ($existing?->isCompleted()) {
                return $existing; // never downgrade a completed payment
            }

            $payment = $existing ?? new Payment();
            $payment->fill([
                'customer_id'            => $invoice->customer_id,
                'invoice_id'             => $invoice->id,
                'method'                 => PaymentMethod::Comgate,
                'status'                 => PaymentStatus::Failed,
                'amount'                 => $invoice->total,
                'gateway_transaction_id' => $transactionId,
                'gateway_response'       => ['mock' => true, 'outcome' => 'failed', 'simulated' => true],
                'processed_at'           => now(),
            ])->save();

            return $payment;
        });

        activity('payment')
            ->performedOn($payment)
            ->causedBy($invoice->customer?->user)
            ->withProperties([
                'invoice_id' => $invoice->id,
                'mock'       => true,
                'outcome'    => 'failed',
            ])
            ->log('payment.failed');

        return $payment;
    }
}
