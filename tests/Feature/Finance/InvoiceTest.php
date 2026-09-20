<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Invoicing\InvoiceNumberAllocator;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\UblExporter;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

it('allocates gap-free sequential numbers per series and year', function () {
    $alloc = app(InvoiceNumberAllocator::class);
    $a = $alloc->allocate('onhost-cz', 'FV', 2026);
    $b = $alloc->allocate('onhost-cz', 'FV', 2026);
    $c = $alloc->allocate('onhost-cz', 'DK', 2026);
    expect($a['number'])->toBe('FV-2026-0001')->and($b['number'])->toBe('FV-2026-0002')->and($c['number'])->toBe('DK-2026-0001')
        ->and(InvoiceNumberAllocator::variableSymbol($b['number']))->toBe('20260002');
});

it('issues an immutable postpaid invoice with receivable, PDF hash and EN16931 UBL, then credits it', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'ico' => '12345678', 'vat_id' => 'CZ12345678']);
    $service = app(InvoiceService::class);
    $ctx = $this->contextFor($owner, $org);
    $draft = $service->draft($org, 'invoice', 'CZK', [
        ['sku' => 'vps-compute-4', 'description' => 'VPS Compute 4 — září 2026', 'qty' => 1, 'unit_net' => 44900, 'discount' => 0, 'net' => 44900, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 9429, 'total' => 54329, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30'],
    ], $ctx, null, ['postpaid' => true, 'payment_method' => 'bank']);
    $invoice = $service->issue($draft, $ctx);

    expect($invoice->state)->toBe(Invoice::ISSUED)->and($invoice->number)->toStartWith('FV-')->and($invoice->pdf_hash)->toHaveLength(64)
        ->and($invoice->structured['InvoiceTypeCode'])->toBe('380')
        ->and(app(LedgerService::class)->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(54329);

    $xml = app(UblExporter::class)->export($invoice);
    expect($xml)->toContain('<cbc:CustomizationID>urn:cen.eu:en16931:2017')->and($xml)->toContain('<cbc:PayableAmount currencyID="CZK">543.29</cbc:PayableAmount>')
        ->and(simplexml_load_string($xml))->not->toBeFalse();

    $pdf = $service->pdfBinary($invoice);
    expect(substr($pdf, 0, 4))->toBe('%PDF');

    $service->markPaid($invoice, Money::minor(54329, 'CZK'), 'bank', $ctx);
    expect($invoice->refresh()->state)->toBe(Invoice::PAID)
        ->and(app(LedgerService::class)->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0);

    $credit = $service->creditNote($invoice, 'SLA credit INC-1', $ctx, incidentRef: 'INC-1');
    expect($credit->type)->toBe('credit_note')->and($credit->total_minor)->toBe(-54329)->and($credit->number)->toStartWith('DK-')
        ->and($invoice->refresh()->state)->toBe(Invoice::CREDITED)
        ->and(app(UblExporter::class)->export($credit))->toContain('<CreditNote');
});

it('moves issued invoices past due date to OVERDUE', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = app(InvoiceService::class);
    $ctx = $this->contextFor($owner, $org);
    $invoice = $service->issue($service->draft($org, 'invoice', 'CZK', [['sku' => 'x', 'description' => 'x', 'qty' => 1, 'unit_net' => 100, 'net' => 100, 'tax_rate' => '21', 'tax' => 21, 'total' => 121]], $ctx, null, ['postpaid' => true]), $ctx, dueDays: 0);
    Invoice::query()->where('id', $invoice->id)->update(['due_at' => now()->subDay()]);
    expect($service->overdueSweep())->toBe(1)->and($invoice->refresh()->state)->toBe(Invoice::OVERDUE);
});

it('books the revenue of a postpaid invoice once: paying it from credit settles the receivable', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ']);
    $service = app(InvoiceService::class);
    $ledger = app(LedgerService::class);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::minor(200000, 'CZK'), 'bank', 'topup-m2', $ctx);
    $line = [['sku' => 'vps-compute-4', 'description' => 'VPS Compute 4', 'qty' => 1, 'unit_net' => 100000, 'discount' => 0, 'net' => 100000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 21000, 'total' => 121000]];
    $invoice = $service->issue($service->draft($org, 'invoice', 'CZK', $line, $ctx, null, ['postpaid' => true]), $ctx);
    $booked = fn () => [
        'revenue' => $ledger->balance(LedgerService::revenueAccount('postpaid', 'CZK'), 'CZK')->minor + $ledger->balance(LedgerService::revenueAccount('services', 'CZK'), 'CZK')->minor,
        'vat' => $ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor,
        'receivable' => $ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor,
        'wallet' => $ledger->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor,
    ];
    expect($booked())->toBe(['revenue' => 100000, 'vat' => 21000, 'receivable' => 121000, 'wallet' => 200000]);

    $this->actingAs($owner, 'sanctum')->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'], ['X-Organization' => $org->id, 'Idempotency-Key' => 'm2-pay-1'])->assertOk()->assertJsonPath('state', Invoice::PAID);
    // the payment moved 1 210 Kč from the customer's credit to the receivable; it earned nothing a second time
    expect($booked())->toBe(['revenue' => 100000, 'vat' => 21000, 'receivable' => 0, 'wallet' => 79000])->and($ledger->verifyInvariant()['balanced'])->toBeTrue();

    // an invoice that was NOT booked when issued (no receivable) still books its revenue when it is paid
    $cash = $service->issue($service->draft($org, 'invoice', 'CZK', [['sku' => 'work', 'description' => 'Práce', 'qty' => 1, 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 2100, 'total' => 12100]], $ctx), $ctx);
    $this->postJson("/v1/invoices/{$cash->id}/pay", ['method' => 'wallet'], ['X-Organization' => $org->id, 'Idempotency-Key' => 'm2-pay-2'])->assertOk();
    expect($booked())->toBe(['revenue' => 110000, 'vat' => 23100, 'receivable' => 0, 'wallet' => 66900]);
});
