<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use App\Notifications\InvoiceIssuedNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues the proforma invoice (zálohová faktura) for an order.
 *
 * Proforma-first flow: the proforma is NOT a tax document. The tax document
 * (InvoiceType::Invoice) is issued after payment in a later phase, linked
 * via parent_invoice_id — see docs/billing.md.
 *
 * Idempotent: re-running for an order that already has a non-cancelled
 * proforma returns the existing invoice instead of issuing a second one.
 */
final class IssueProformaInvoiceAction
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
    ) {}

    public function execute(Order $order): Invoice
    {
        $existing = $order->invoices()
            ->where('type', InvoiceType::Proforma->value)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $customer = $order->customer
            ?? throw new InvalidArgumentException("Order [{$order->id}] has no customer.");

        $scenario = VatScenario::from((string) $order->vat_scenario);
        // Audit 111 — a reseller with its own series prefix numbers its
        // customers' invoices under that prefix; otherwise the global series.
        $seriesKey = $customer->reseller?->invoiceSeriesPrefix() ?? $scenario->invoiceSeries()->value;
        $number    = $this->numbers->nextForKey($seriesKey);
        $address   = $customer->billingAddress();

        $invoice = DB::transaction(function () use ($order, $customer, $scenario, $seriesKey, $number, $address): Invoice {
            $invoice = Invoice::create([
                'customer_id'  => $customer->id,
                'order_id'     => $order->id,
                'type'         => InvoiceType::Proforma,
                'series'       => $seriesKey,
                'number'       => $number,
                'status'       => InvoiceStatus::Sent,
                'vat_scenario' => $scenario,
                'currency'     => $order->currency,
                'subtotal'     => $order->subtotal,
                'tax_amount'   => $order->tax_amount,
                'total'        => $order->total,

                // Czech bank-matching identifier: digits of the number,
                // e.g. CZ-2026-000123 → 2026000123 (max 10 digits).
                'variable_symbol' => mb_substr(preg_replace('/\D/', '', $number) ?? '', 0, 10),

                'issue_date' => now()->toDateString(),
                /*
                 | Audit D41 — honour the customer's agreed payment terms.
                 |
                 | Everyone previously got the same proforma window, so a
                 | corporate customer on net-30 was "overdue" from day 11:
                 | dunning chased them and late fees applied for an invoice
                 | that was contractually fine. NULL still means the default.
                 */
                'due_date'   => now()
                    ->addDays($customer->payment_terms_days
                        ?? Config::integer('billing.proforma_validity_days', 10))
                    ->toDateString(),

                // --- immutable billing snapshot ---
                // Address may legitimately be empty on a proforma (not a tax
                // document). Issuing the tax document after payment WILL
                // require completed billing details — enforced in Phase 3.
                'snapshot_name'                => $customer->company_name ?? $customer->user->name ?? $customer->email,
                'snapshot_company'             => $customer->company_name,
                'snapshot_street'              => $address->street ?? '',
                'snapshot_city'                => $address->city ?? '',
                'snapshot_zip'                 => $address->zip ?? '',
                'snapshot_country_code'        => $customer->country_code,
                'snapshot_vat_number'          => $customer->vat_number,
                'snapshot_registration_number' => $customer->registration_number,
            ]);

            foreach ($order->items as $item) {
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
            ->causedBy($customer->user)
            ->withProperties([
                'order_id' => $order->id,
                'number'   => $number,
                'type'     => InvoiceType::Proforma->value,
            ])
            ->log('invoice.issued');

        $customer->user?->notify(new InvoiceIssuedNotification($invoice));

        return $invoice->load('items');
    }
}
