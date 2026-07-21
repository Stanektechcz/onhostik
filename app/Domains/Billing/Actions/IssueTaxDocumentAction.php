<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Exceptions\IncompleteBillingDetailsException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues the TAX DOCUMENT (daňový doklad) for a PAID order proforma.
 *
 * Legal model (accountant review required — see docs/invoices.md):
 *  - the tax document is issued at payment time, linked to the proforma
 *    via parent_invoice_id,
 *  - taxable supply date = the proforma payment date,
 *  - the billing snapshot is taken from the customer's CURRENT details,
 *    which must be complete (name + street + city + zip + country) —
 *    otherwise IncompleteBillingDetailsException is thrown and the
 *    document can be issued later from the admin panel.
 *
 * Idempotent: an existing non-cancelled child tax document is returned.
 */
final class IssueTaxDocumentAction
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
    ) {}

    public function execute(Invoice $proforma): Invoice
    {
        if ($proforma->type !== InvoiceType::Proforma) {
            throw new InvalidArgumentException("Invoice [{$proforma->number}] is not a proforma.");
        }

        if ($proforma->status !== InvoiceStatus::Paid) {
            throw new InvalidArgumentException("Proforma [{$proforma->number}] is not paid.");
        }

        $existing = Invoice::query()
            ->where('parent_invoice_id', $proforma->id)
            ->where('type', InvoiceType::Invoice->value)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $customer = $proforma->customer
            ?? throw new InvalidArgumentException("Proforma [{$proforma->id}] has no customer.");

        $address = $customer->billingAddress();
        $name    = $customer->company_name ?? $customer->user->name ?? '';

        if ($name === '' || $address === null
            || ($address->street ?? '') === '' || ($address->city ?? '') === '' || ($address->zip ?? '') === '') {
            throw new IncompleteBillingDetailsException(
                "Cannot issue tax document for [{$proforma->number}]: billing details are incomplete."
            );
        }

        $scenario = $proforma->vat_scenario;
        // Audit 111 — the reseller's own series prefix wins, else the global.
        $seriesKey = $customer->reseller?->invoiceSeriesPrefix() ?? $scenario->invoiceSeries()->value;
        $number    = $this->numbers->nextForKey($seriesKey);

        $invoice = DB::transaction(function () use ($proforma, $customer, $scenario, $seriesKey, $number, $address, $name): Invoice {
            $invoice = Invoice::create([
                'customer_id'       => $customer->id,
                'order_id'          => $proforma->order_id,
                'parent_invoice_id' => $proforma->id,
                'type'              => InvoiceType::Invoice,
                'purpose'           => $proforma->purpose,
                'series'            => $seriesKey,
                'number'            => $number,
                'status'            => InvoiceStatus::Paid,
                'vat_scenario'      => $scenario,
                'currency'          => $proforma->currency,
                'subtotal'          => $proforma->subtotal,
                'tax_amount'        => $proforma->tax_amount,
                'total'             => $proforma->total,
                'variable_symbol'   => mb_substr(preg_replace('/\D/', '', $number) ?? '', 0, 10),
                'issue_date'          => now()->toDateString(),
                'taxable_supply_date' => $proforma->paid_at?->toDateString() ?? now()->toDateString(),
                'due_date'            => now()->toDateString(),
                'paid_at'             => $proforma->paid_at ?? now(),
                'notes' => "Daňový doklad k zálohové faktuře {$proforma->number}. "
                    . 'Vystaveno automaticky po úhradě — ke kontrole účetním.',

                'snapshot_name'                => $name,
                'snapshot_company'             => $customer->company_name,
                'snapshot_street'              => $address->street,
                'snapshot_city'                => $address->city,
                'snapshot_zip'                 => $address->zip,
                'snapshot_country_code'        => $customer->country_code,
                'snapshot_vat_number'          => $customer->vat_number,
                'snapshot_registration_number' => $customer->registration_number,
            ]);

            foreach ($proforma->items as $item) {
                $invoice->items()->create([
                    'description' => $item->description,
                    'quantity'    => $item->quantity,
                    'currency'    => $item->currency,
                    'unit_price'  => $item->unit_price,
                    'vat_rate'    => $item->vat_rate,
                    'total'       => $item->total,
                ]);
            }

            return $invoice;
        });

        activity('invoice')
            ->performedOn($invoice)
            ->withProperties(['number' => $number, 'parent' => $proforma->number, 'type' => InvoiceType::Invoice->value])
            ->log('invoice.tax_document_issued');

        return $invoice;
    }
}
