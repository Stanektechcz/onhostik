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
 * Records a manual, admin-confirmed payment against an open invoice — the
 * "we received the bank transfer / I'm accepting this order" path.
 *
 * It fires the same InvoicePaid event as the gateway and the mock provider,
 * so the whole downstream (order transition, provisioning, tax document)
 * runs identically. Unlike ProcessMockPaymentAction this is NOT gated to
 * mock mode: recording that money arrived is a billing fact, not an external
 * API write, and any real provisioning it triggers stays gated by the
 * driver's own mock / allow-real-writes settings.
 *
 * Idempotent: one deterministic transaction id per invoice under a row lock,
 * so a double click can never create a second completed payment.
 */
final class RecordManualPaymentAction
{
    public function execute(Invoice $invoice, ?string $note = null): Payment
    {
        $transactionId = 'MANUAL-' . $invoice->uuid;

        if ($invoice->status === InvoiceStatus::Paid) {
            $existing = Payment::where('gateway_transaction_id', $transactionId)->first();

            if ($existing !== null) {
                return $existing; // already accepted — no-op
            }
        }

        if (! $invoice->status->isOpen() && $invoice->status !== InvoiceStatus::Paid) {
            throw new InvalidArgumentException(
                "Invoice [{$invoice->number}] is not payable (status: {$invoice->status->value})."
            );
        }

        $payment = DB::transaction(function () use ($invoice, $transactionId, $note): Payment {
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
                'method'                 => PaymentMethod::BankTransfer,
                'status'                 => PaymentStatus::Completed,
                'amount'                 => $invoice->total,
                'gateway_transaction_id' => $transactionId,
                'gateway_response'       => array_filter(['manual' => true, 'note' => $note]),
                'processed_at'           => now(),
            ])->save();

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
            ->withProperties(['invoice_id' => $invoice->id, 'manual' => true])
            ->log('payment.manual_recorded');

        return $payment;
    }
}
