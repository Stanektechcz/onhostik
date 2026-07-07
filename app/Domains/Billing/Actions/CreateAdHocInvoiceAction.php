<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use App\Domains\Billing\Services\VatResolver;
use App\Domains\Customer\Models\Customer;
use App\Notifications\InvoiceIssuedNotification;
use Brick\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a one-off (ad-hoc) invoice for a customer from a list of line items.
 *
 * Unlike order-driven invoices, this has no associated Order; purpose = 'adhoc'.
 * VAT scenario is resolved automatically from the customer's billing profile.
 * The invoice is issued immediately as InvoiceType::Invoice (tax document).
 */
final class CreateAdHocInvoiceAction
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly VatResolver $vat,
    ) {}

    /**
     * @param list<array{description: string, quantity: int, unit_price_minor: int, vat_rate: float}> $items
     */
    public function execute(
        Customer $customer,
        array $items,
        Carbon $dueDate,
        ?string $notes = null,
    ): Invoice {
        $scenario = $this->vat->resolveScenario($customer);
        $number   = $this->numbers->next($scenario->invoiceSeries());
        $address  = $customer->billingAddress();
        $currency = $customer->preferred_currency->value;

        $subtotalMinor = 0;
        $taxMinor      = 0;
        $builtItems    = [];

        foreach ($items as $item) {
            $lineMinor  = $item['unit_price_minor'] * $item['quantity'];
            $lineTax    = (int) round($lineMinor * $item['vat_rate'] / 100);

            $builtItems[] = [
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'currency'    => $currency,
                'unit_price'  => Money::ofMinor($item['unit_price_minor'], $currency),
                'vat_rate'    => $item['vat_rate'],
                'total'       => Money::ofMinor($lineMinor, $currency),
            ];

            $subtotalMinor += $lineMinor;
            $taxMinor      += $lineTax;
        }

        $subtotal = Money::ofMinor($subtotalMinor, $currency);
        $tax      = Money::ofMinor($taxMinor, $currency);
        $total    = Money::ofMinor($subtotalMinor + $taxMinor, $currency);

        $invoice = DB::transaction(function () use (
            $customer, $scenario, $number, $address, $currency,
            $subtotal, $tax, $total, $dueDate, $notes, $builtItems,
        ): Invoice {
            $invoice = Invoice::create([
                'customer_id'         => $customer->id,
                'type'                => InvoiceType::Invoice,
                'purpose'             => 'adhoc',
                'series'              => $scenario->invoiceSeries()->value,
                'number'              => $number,
                'status'              => InvoiceStatus::Sent,
                'vat_scenario'        => $scenario,
                'currency'            => $currency,
                'subtotal'            => $subtotal,
                'tax_amount'          => $tax,
                'total'               => $total,
                'variable_symbol'     => mb_substr(preg_replace('/\D/', '', $number) ?? '', 0, 10),
                'issue_date'          => now()->toDateString(),
                'taxable_supply_date' => now()->toDateString(),
                'due_date'            => $dueDate->toDateString(),
                'notes'               => $notes,

                // --- immutable billing snapshot ---
                'snapshot_name'                => $customer->company_name ?? $customer->user->name ?? $customer->email,
                'snapshot_company'             => $customer->company_name,
                'snapshot_street'              => $address->street ?? '',
                'snapshot_city'                => $address->city ?? '',
                'snapshot_zip'                 => $address->zip ?? '',
                'snapshot_country_code'        => $customer->country_code,
                'snapshot_vat_number'          => $customer->vat_number,
                'snapshot_registration_number' => $customer->registration_number,
            ]);

            foreach ($builtItems as $builtItem) {
                $invoice->items()->create($builtItem);
            }

            return $invoice;
        });

        activity('invoice')
            ->performedOn($invoice)
            ->withProperties(['number' => $number, 'purpose' => 'adhoc', 'customer_id' => $customer->id])
            ->log('invoice.adhoc_created');

        $customer->user?->notify(new InvoiceIssuedNotification($invoice));

        return $invoice->load('items');
    }
}
