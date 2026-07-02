<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues a credit note (dobropis) against a paid invoice.
 *
 * The credit note:
 *  - is linked to the source invoice via parent_invoice_id
 *  - has negative amounts (matching the source invoice totals)
 *  - is immediately deposited into the customer's credit balance
 *    so they can use it on future invoices
 *
 * Idempotent: if a non-cancelled credit note already exists for the
 * source invoice it is returned without creating a duplicate.
 *
 * Legal: only paid invoices (type=invoice) may be credited. Proformas
 * and existing credit notes cannot be credited.
 */
final class IssueCreditNoteAction
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly CreditLedger           $ledger,
    ) {}

    public function execute(Invoice $source, ?string $reason = null): Invoice
    {
        if ($source->type !== InvoiceType::Invoice) {
            throw new InvalidArgumentException(
                "Cannot credit [{$source->number}]: only issued invoices (type=invoice) can be credited."
            );
        }

        if ($source->status !== InvoiceStatus::Paid) {
            throw new InvalidArgumentException(
                "Cannot credit [{$source->number}]: invoice must be paid first."
            );
        }

        // Idempotency guard
        $existing = Invoice::query()
            ->where('parent_invoice_id', $source->id)
            ->where('type', InvoiceType::CreditNote->value)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $customer = $source->customer
            ?? throw new InvalidArgumentException("Invoice [{$source->id}] has no customer.");

        $number = $this->numbers->next(InvoiceSeries::CreditNote);

        $creditNote = DB::transaction(function () use ($source, $customer, $number, $reason): Invoice {
            // Billing snapshot is copied from the source invoice — credit notes
            // must reflect the address at the time of the original invoice, and
            // also ensures the NOT NULL snapshot columns are always populated.
            $creditNote = Invoice::create([
                'customer_id'        => $customer->id,
                'order_id'           => $source->order_id,
                'parent_invoice_id'  => $source->id,
                'type'               => InvoiceType::CreditNote,
                'purpose'            => $source->purpose,
                'series'             => InvoiceSeries::CreditNote->value,
                'number'             => $number,
                'status'             => InvoiceStatus::Paid,
                'vat_scenario'       => $source->vat_scenario,
                'currency'           => $source->currency,
                'subtotal'           => $source->subtotal->negated(),
                'tax_amount'         => $source->tax_amount->negated(),
                'total'              => $source->total->negated(),
                'variable_symbol'    => mb_substr(preg_replace('/\D/', '', $number) ?? '', 0, 10),
                'issue_date'         => now()->toDateString(),
                'taxable_supply_date'=> now()->toDateString(),
                'due_date'           => now()->toDateString(),
                'paid_at'            => now(),
                'notes'              => trim(
                    "Dobropis k faktuře {$source->number}."
                    . ($reason !== null ? " Důvod: {$reason}" : '')
                    . ' Částka bude připsána na kredit zákazníka.'
                ),
                'snapshot_name'                => $source->snapshot_name,
                'snapshot_company'             => $source->snapshot_company,
                'snapshot_street'              => $source->snapshot_street,
                'snapshot_city'                => $source->snapshot_city,
                'snapshot_zip'                 => $source->snapshot_zip,
                'snapshot_country_code'        => $source->snapshot_country_code,
                'snapshot_vat_number'          => $source->snapshot_vat_number,
                'snapshot_registration_number' => $source->snapshot_registration_number,
            ]);

            // Mirror line items with negated amounts
            foreach ($source->items as $item) {
                $creditNote->items()->create([
                    'description' => $item->description,
                    'quantity'    => $item->quantity,
                    'currency'    => $item->currency,
                    'unit_price'  => $item->unit_price->negated(),
                    'vat_rate'    => $item->vat_rate,
                    'total'       => $item->total->negated(),
                ]);
            }

            // Credit the full amount to the customer's credit balance
            $this->ledger->deposit(
                $customer,
                $source->total,
                "Dobropis {$number} — vrácení platby za fakturu {$source->number}",
                $creditNote,
            );

            return $creditNote;
        });

        activity('invoice')
            ->performedOn($creditNote)
            ->withProperties([
                'number'  => $number,
                'parent'  => $source->number,
                'type'    => InvoiceType::CreditNote->value,
                'reason'  => $reason,
                'amount'  => (string) $source->total,
            ])
            ->log('invoice.credit_note_issued');

        return $creditNote;
    }
}
