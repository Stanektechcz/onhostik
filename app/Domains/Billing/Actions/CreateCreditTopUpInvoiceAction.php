<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use App\Domains\Billing\Services\VatResolver;
use App\Domains\Customer\Models\Customer;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues a proforma for a credit top-up (purpose=credit_topup).
 *
 * When this invoice is paid, HandleInvoicePaid deposits the amount into
 * the customer's credit ledger instead of provisioning anything.
 *
 * TAX NOTE (accountant review required): the top-up proforma is issued at
 * 0 % VAT as an advance; VAT treatment of wallet credits depends on how
 * credit is later consumed (single- vs multi-purpose voucher rules). This
 * is intentionally conservative and flagged in docs/credits-wallet.md.
 */
final class CreateCreditTopUpInvoiceAction
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly VatResolver $vatResolver,
    ) {}

    public function execute(Customer $customer, Money $amount): Invoice
    {
        if (!$amount->isPositive()) {
            throw new InvalidArgumentException('Top-up amount must be positive.');
        }

        if ($amount->getCurrency()->getCurrencyCode() !== $customer->preferred_currency->value) {
            throw new InvalidArgumentException('Top-up currency must match the customer ledger currency.');
        }

        $minorAmount = $amount->getMinorAmount()->toInt();
        $min         = (int) config('billing.credit_topup.min_minor', 10_000);
        $max         = (int) config('billing.credit_topup.max_minor', 5_000_000);

        if ($minorAmount < $min || $minorAmount > $max) {
            throw new InvalidArgumentException("Top-up amount out of allowed range [{$min}–{$max} minor units].");
        }

        $scenario = $this->vatResolver->resolveScenario($customer);
        $number   = $this->numbers->next($scenario->invoiceSeries());
        $zero     = Money::ofMinor(0, $amount->getCurrency()->getCurrencyCode());
        $address  = $customer->billingAddress();

        $invoice = DB::transaction(function () use ($customer, $scenario, $number, $amount, $zero, $address): Invoice {
            $invoice = Invoice::create([
                'customer_id'     => $customer->id,
                'type'            => InvoiceType::Proforma,
                'purpose'         => 'credit_topup',
                'series'          => $scenario->invoiceSeries()->value,
                'number'          => $number,
                'status'          => InvoiceStatus::Sent,
                'vat_scenario'    => $scenario,
                'currency'        => $customer->preferred_currency,
                'subtotal'        => $amount,
                'tax_amount'      => $zero,
                'total'           => $amount,
                'variable_symbol' => mb_substr(preg_replace('/\D/', '', $number) ?? '', 0, 10),
                'issue_date'      => now()->toDateString(),
                'due_date'        => now()->addDays((int) config('billing.proforma_validity_days', 10))->toDateString(),
                'notes'           => 'Dobití kreditu — záloha, 0 % DPH (režim poukazu, ke kontrole účetním).',

                'snapshot_name'                => $customer->company_name ?? $customer->user->name ?? $customer->email,
                'snapshot_company'             => $customer->company_name,
                'snapshot_street'              => $address->street ?? '',
                'snapshot_city'                => $address->city ?? '',
                'snapshot_zip'                 => $address->zip ?? '',
                'snapshot_country_code'        => $customer->country_code,
                'snapshot_vat_number'          => $customer->vat_number,
                'snapshot_registration_number' => $customer->registration_number,
            ]);

            $invoice->items()->create([
                'description' => 'Dobití kreditu (zálohový účet)',
                'quantity'    => 1,
                'currency'    => $customer->preferred_currency->value,
                'unit_price'  => $amount,
                'vat_rate'    => 0,
                'total'       => $amount,
            ]);

            return $invoice;
        });

        activity('invoice')
            ->performedOn($invoice)
            ->causedBy($customer->user)
            ->withProperties(['number' => $number, 'purpose' => 'credit_topup', 'amount' => $minorAmount])
            ->log('invoice.topup_issued');

        return $invoice;
    }
}
