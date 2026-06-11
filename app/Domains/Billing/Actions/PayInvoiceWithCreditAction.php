<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\CreditLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pays an invoice from the customer's credit (zálohový účet).
 *
 * Safety model:
 *  - the whole operation runs in one DB transaction,
 *  - CreditLedger::deduct() locks the customer row and recomputes the
 *    authoritative balance — overdraw is impossible (and additionally
 *    enforced by the append-only ledger's DB triggers on MySQL),
 *  - the balance NEVER changes without a ledger row,
 *  - the deterministic gateway_transaction_id (CREDIT-{invoice uuid}) plus
 *    its UNIQUE index make a double-submit unable to deduct twice,
 *  - InvoicePaid fires exactly once — the same event path as the gateway.
 *
 * Throws InsufficientCreditException when the balance does not cover the
 * invoice total (caller turns it into a friendly flash message).
 */
final class PayInvoiceWithCreditAction
{
    public function __construct(
        private readonly CreditLedger $ledger,
    ) {}

    public function execute(Invoice $invoice): Payment
    {
        $transactionId = 'CREDIT-' . $invoice->uuid;

        if ($invoice->status === InvoiceStatus::Paid) {
            $existing = Payment::where('gateway_transaction_id', $transactionId)->first();

            if ($existing !== null) {
                return $existing; // duplicate submit — no second deduction
            }

            throw new InvalidArgumentException("Invoice [{$invoice->number}] is already paid.");
        }

        if (!$invoice->status->isOpen()) {
            throw new InvalidArgumentException(
                "Invoice [{$invoice->number}] is not payable (status: {$invoice->status->value})."
            );
        }

        $customer = $invoice->customer
            ?? throw new InvalidArgumentException("Invoice [{$invoice->id}] has no customer.");

        $total = $invoice->total
            ?? throw new InvalidArgumentException("Invoice [{$invoice->id}] has no total.");

        $payment = DB::transaction(function () use ($invoice, $customer, $total, $transactionId): Payment {
            // Idempotency under lock — a concurrent duplicate waits here and
            // then observes the completed payment instead of deducting again.
            $existing = Payment::where('gateway_transaction_id', $transactionId)
                ->lockForUpdate()
                ->first();

            if ($existing?->isCompleted()) {
                return $existing;
            }

            // Throws InsufficientCreditException — rolls everything back.
            $ledgerEntry = $this->ledger->deduct(
                customer: $customer,
                amount: $total,
                description: "Úhrada faktury {$invoice->number} z kreditu",
                reference: $invoice,
            );

            $payment = $existing ?? new Payment();
            $payment->fill([
                'customer_id'            => $customer->id,
                'invoice_id'             => $invoice->id,
                'method'                 => PaymentMethod::Credit,
                'status'                 => PaymentStatus::Completed,
                'amount'                 => $total,
                'gateway_transaction_id' => $transactionId,
                'gateway_response'       => [
                    'mock'                  => false,
                    'internal'              => 'credit_ledger',
                    'credit_transaction_id' => $ledgerEntry->id,
                ],
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
            ->causedBy($customer->user)
            ->withProperties([
                'invoice_id' => $invoice->id,
                'method'     => PaymentMethod::Credit->value,
            ])
            ->log('payment.completed');

        return $payment;
    }
}
