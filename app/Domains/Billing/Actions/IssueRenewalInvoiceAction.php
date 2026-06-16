<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\InvoiceIssuedNotification;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Issues the renewal proforma invoice for an active service approaching
 * its next_due_date.
 *
 * Idempotency key: (renewal_service_id, due_date) — re-running for the
 * same service and the same due-date cycle returns the existing invoice
 * instead of issuing a second one. due_date is set to the service's
 * current next_due_date (the day it would otherwise lapse), which doubles
 * as the cycle identifier.
 *
 * Pricing is copied from the original OrderItem snapshot — the plan price
 * the customer originally agreed to — never re-derived from the live
 * PricingPlan, so a later price change never silently applies to an
 * existing subscription without the customer re-ordering.
 *
 * Scope: issuance only. Extending Service.next_due_date once this invoice
 * is paid belongs to HandleInvoicePaid and is a separate, not-yet-wired
 * follow-up (see Phase 8 audit notes).
 */
final class IssueRenewalInvoiceAction
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
    ) {}

    public function execute(Service $service): ?Invoice
    {
        $dueDate = $service->next_due_date;

        if ($dueDate === null) {
            return null;
        }

        $existing = Invoice::query()
            ->where('renewal_service_id', $service->id)
            ->whereDate('due_date', $dueDate->toDateString())
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $orderItem = $service->orderItem;
        $order     = $orderItem?->order;
        $customer  = $service->customer;
        $plan      = $orderItem?->pricingPlan;

        if ($orderItem === null || $order === null || $customer === null || $plan === null) {
            return null; // defensive — cannot price a renewal without the original order item
        }

        $scenario = VatScenario::from((string) $order->vat_scenario);
        $number   = $this->numbers->next($scenario->invoiceSeries());
        $address  = $customer->billingAddress();

        $subtotal = $orderItem->total; // net amount, quantity always 1 (see CreateOrderAction)
        $tax      = $subtotal->multipliedBy((float) $orderItem->vat_rate / 100, RoundingMode::HALF_UP);
        $total    = $subtotal->plus($tax);

        $invoice = DB::transaction(function () use (
            $service, $order, $orderItem, $customer, $scenario, $number, $address,
            $subtotal, $tax, $total, $dueDate, $plan,
        ): Invoice {
            $invoice = Invoice::create([
                'customer_id'         => $customer->id,
                'order_id'            => $order->id,
                'renewal_service_id'  => $service->id,
                'type'                => InvoiceType::Proforma,
                'purpose'             => 'renewal',
                'series'              => $scenario->invoiceSeries()->value,
                'number'              => $number,
                'status'              => InvoiceStatus::Sent,
                'vat_scenario'        => $scenario,
                'currency'            => $orderItem->currency,
                'subtotal'            => $subtotal,
                'tax_amount'          => $tax,
                'total'               => $total,

                'variable_symbol' => mb_substr(preg_replace('/\D/', '', $number) ?? '', 0, 10),

                'issue_date' => now()->toDateString(),
                'due_date'   => $dueDate->toDateString(),

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

            $productName = $plan->product->name ?? 'Hosting';

            $invoice->items()->create([
                'description' => trim("{$productName} {$plan->name} — obnova"),
                'quantity'    => 1,
                'currency'    => $orderItem->currency,
                'unit_price'  => $orderItem->unit_price,
                'vat_rate'    => $orderItem->vat_rate,
                'total'       => $subtotal,
            ]);

            return $invoice;
        });

        activity('invoice')
            ->performedOn($invoice)
            ->withProperties([
                'service_id' => $service->id,
                'order_id'   => $order->id,
                'number'     => $number,
                'purpose'    => 'renewal',
            ])
            ->log('invoice.renewal_issued');

        $customer->user?->notify(new InvoiceIssuedNotification($invoice));

        return $invoice->load('items');
    }
}
