<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\IssueCreditNoteAction;
use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Creates a paid Invoice (type=Invoice) for the given customer. */
function paidInvoice(Customer $customer, int $totalMinor = 5_929): Invoice
{
    $subtotal = (int) round($totalMinor / 1.21);
    $tax      = $totalMinor - $subtotal;

    $invoice = Invoice::create([
        'customer_id'          => $customer->id,
        'type'                 => InvoiceType::Invoice,
        'purpose'              => 'order',
        'series'               => InvoiceSeries::Czech->value,
        'number'               => 'CZ-2026-000001',
        'status'               => InvoiceStatus::Paid,
        'vat_scenario'         => VatScenario::CzechB2C,
        'currency'             => 'CZK',
        'subtotal'             => Money::ofMinor($subtotal, 'CZK'),
        'tax_amount'           => Money::ofMinor($tax, 'CZK'),
        'total'                => Money::ofMinor($totalMinor, 'CZK'),
        'variable_symbol'      => '20260001',
        'issue_date'           => now()->toDateString(),
        'taxable_supply_date'  => now()->toDateString(),
        'due_date'             => now()->toDateString(),
        'paid_at'              => now(),
        'snapshot_name'                => $customer->company_name ?? 'Test',
        'snapshot_company'             => $customer->company_name ?? 'Test s.r.o.',
        'snapshot_street'              => 'Testovací 123',
        'snapshot_city'                => 'Praha',
        'snapshot_zip'                 => '11000',
        'snapshot_country_code'        => 'CZ',
        'snapshot_registration_number' => '12345678',
    ]);

    // Add a single line item
    $invoice->items()->create([
        'description' => 'Webhosting Basic 1 rok',
        'quantity'    => 1,
        'currency'    => 'CZK',
        'unit_price'  => Money::ofMinor($subtotal, 'CZK'),
        'vat_rate'    => '21.00',
        'total'       => Money::ofMinor($subtotal, 'CZK'),
    ]);

    return $invoice;
}

beforeEach(function (): void {
    $this->user     = \App\Models\User::factory()->create();
    $this->customer = Customer::factory()->for($this->user)->create();
    $this->action   = app(IssueCreditNoteAction::class);
});

it('creates a credit note with negated amounts', function (): void {
    $invoice    = paidInvoice($this->customer, 5_929);
    $creditNote = $this->action->execute($invoice);

    expect($creditNote->type)->toBe(InvoiceType::CreditNote)
        ->and($creditNote->status)->toBe(InvoiceStatus::Paid)
        ->and($creditNote->parent_invoice_id)->toBe($invoice->id)
        ->and($creditNote->total->getMinorAmount()->toInt())->toBe(-5_929)
        ->and($creditNote->number)->toStartWith('CN-');
});

it('mirrors line items with negated amounts', function (): void {
    $invoice    = paidInvoice($this->customer, 5_929);
    $creditNote = $this->action->execute($invoice);

    $creditNote->load('items');

    expect($creditNote->items)->toHaveCount(1);
    expect($creditNote->items->first()->total->getMinorAmount()->toInt())->toBeLessThan(0);
});

it('deposits the credit amount into the customer ledger', function (): void {
    $invoice = paidInvoice($this->customer, 5_929);
    $this->action->execute($invoice);

    $balance = app(CreditLedger::class)->getBalance($this->customer->refresh())->getMinorAmount()->toInt();
    expect($balance)->toBe(5_929);
});

it('is idempotent — calling execute twice returns the same credit note', function (): void {
    $invoice = paidInvoice($this->customer, 5_929);

    $first  = $this->action->execute($invoice);
    $second = $this->action->execute($invoice);

    expect(Invoice::where('parent_invoice_id', $invoice->id)->count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});

it('deposits credit only once when idempotent path is taken', function (): void {
    $invoice = paidInvoice($this->customer, 5_929);

    $this->action->execute($invoice);
    $this->action->execute($invoice); // second call — idempotent

    $balance = app(CreditLedger::class)->getBalance($this->customer->refresh())->getMinorAmount()->toInt();
    expect($balance)->toBe(5_929); // not doubled
});

it('rejects a proforma (type=Proforma) invoice', function (): void {
    $invoice = paidInvoice($this->customer, 5_929);
    $invoice->update(['type' => InvoiceType::Proforma]);

    expect(fn () => $this->action->execute($invoice))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an unpaid invoice', function (): void {
    $invoice = paidInvoice($this->customer, 5_929);
    $invoice->update(['status' => InvoiceStatus::Sent]);

    expect(fn () => $this->action->execute($invoice))
        ->toThrow(InvalidArgumentException::class);
});

it('appends the reason to the credit note notes field', function (): void {
    $invoice    = paidInvoice($this->customer, 5_929);
    $creditNote = $this->action->execute($invoice, 'Reklamace zákazníka');

    expect($creditNote->notes)->toContain('Reklamace zákazníka');
});
