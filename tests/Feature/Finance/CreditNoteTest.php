<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * A credit note knows which line it corrects. What a line has left is what it was issued for minus every credit note
 * already written against it: nothing is credited twice, a credit note is never credited itself, a document with a credit
 * note is paid for what it has left, and a booked document gives its VAT back together with its revenue.
 */

beforeEach(fn () => $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]));

/** A postpaid invoice of two lines: 1 000 + 21 % and 500 + 21 %. */
function creditNotePostpaidInvoice(Organization $org, CommandContext $ctx): Invoice
{
    $service = app(InvoiceService::class);

    return $service->issue($service->draft($org, 'invoice', 'CZK', [
        ['sku' => 'vps', 'description' => 'VPS Compute 4', 'qty' => 1, 'unit_net' => 100000, 'discount' => 0, 'net' => 100000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 21000, 'total' => 121000],
        ['sku' => 'backup', 'description' => 'Zálohy 100 GB', 'qty' => 1, 'unit_net' => 50000, 'discount' => 0, 'net' => 50000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 10500, 'total' => 60500],
    ], $ctx, null, ['postpaid' => true, 'payment_method' => 'bank']), $ctx);
}

it('credits a document once: not twice, not line by line again, and never a credit note itself', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ']);
    $ctx = $this->contextFor($owner, $org);
    $service = app(InvoiceService::class);
    $ledger = app(LedgerService::class);
    $invoice = creditNotePostpaidInvoice($org, $ctx);
    $receivable = fn () => $ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor;
    [$vps, $backup] = $invoice->lines()->get()->all();
    expect($receivable())->toBe(181500);

    // finance, through the API: the same line twice
    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $first = $this->withHeader('Idempotency-Key', 'cn-1')->postJson("/v1/invoices/{$invoice->id}/credit-note", ['reason' => 'Zálohy nebyly dodány', 'line_ids' => [$backup->id]])->assertCreated()->json();
    $this->withHeader('Idempotency-Key', 'cn-2')->postJson("/v1/invoices/{$invoice->id}/credit-note", ['reason' => 'Zálohy nebyly dodány (znovu)', 'line_ids' => [$backup->id]])->assertStatus(409)->assertJsonPath('error', 'invoice_line_already_credited');
    $this->flushHeaders();
    $note = Invoice::query()->findOrFail($first['invoice']['id']);
    expect($note->total_minor)->toBe(-60500)->and(InvoiceLine::query()->where('invoice_id', $note->id)->value('corrects_line_id'))->toBe($backup->id)
        ->and($invoice->refresh()->credited_minor)->toBe(60500)->and($invoice->state)->toBe(Invoice::ISSUED)->and($invoice->outstanding()->minor)->toBe(121000)->and($receivable())->toBe(121000);

    // the whole document now means: what is left of it
    $rest = $service->creditNote($invoice, 'Storno celé faktury', $ctx);
    expect($rest->total_minor)->toBe(-121000)->and($rest->lines()->count())->toBe(1)->and($invoice->refresh()->state)->toBe(Invoice::CREDITED)->and($receivable())->toBe(0);
    expect(fn () => $service->creditNote($invoice->refresh(), 'potřetí', $ctx))->toThrow(DomainError::class, 'Nothing is left to credit');
    expect(fn () => $service->creditNote($rest, 'dobropis dobropisu', $ctx))->toThrow(DomainError::class, 'never credited itself');
    expect(Invoice::query()->where('corrects_invoice_id', $invoice->id)->count())->toBe(2)->and($receivable())->toBe(0);

    // the VAT went back with the revenue: nothing is owed to the state for a document that no longer stands
    expect($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(0)
        ->and($ledger->balance(LedgerService::revenueAccount('credit_note', 'CZK'), 'CZK')->minor)->toBe(-150000)->and($ledger->verifyInvariant()['balanced'])->toBeTrue();
    expect($vps->id)->not->toBe($backup->id);
});

it('splits a part of a line in the line\'s own proportion and lets the last part take the remainder to the haler', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ']);
    $ctx = $this->contextFor($owner, $org);
    $service = app(InvoiceService::class);
    $invoice = $service->issue($service->draft($org, 'invoice', 'CZK', [['sku' => 'web', 'description' => 'Webhosting Start — září 2026', 'qty' => 1, 'unit_net' => 8900, 'discount' => 0, 'net' => 8900, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 1869, 'total' => 10769, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30']], $ctx, null, ['postpaid' => true]), $ctx);
    $line = $invoice->lines()->firstOrFail();

    expect(fn () => $service->creditNote($invoice, 'moc', $ctx, null, null, [$line->id => 10770]))->toThrow(DomainError::class, 'more than it has left');
    $part = $service->creditNote($invoice, 'Výpadek 8 dní', $ctx, null, null, [$line->id => ['gross' => 3000, 'period_from' => '2026-09-10', 'period_to' => '2026-09-17']]);
    $partLine = $part->lines()->firstOrFail();
    expect($part->total_minor)->toBe(-3000)->and($part->tax_minor)->toBe(-521)->and($partLine->net_minor)->toBe(-2479)->and($partLine->period_from->toDateString())->toBe('2026-09-10')
        ->and($invoice->refresh()->outstanding()->minor)->toBe(7769)->and($invoice->state)->toBe(Invoice::ISSUED);

    expect(fn () => $service->creditNote($invoice, 'moc', $ctx, null, null, [$line->id => 7770]))->toThrow(DomainError::class, 'more than it has left');
    $rest = $service->creditNote($invoice, 'Zbytek', $ctx);
    expect($rest->total_minor)->toBe(-7769)->and($rest->tax_minor)->toBe(-1348)->and($rest->subtotal_minor - $rest->discount_minor)->toBe(-6421) // 2 479 + 6 421 = 8 900, 521 + 1 348 = 1 869
        ->and($invoice->refresh()->state)->toBe(Invoice::CREDITED);
});

it('asks a document with a credit note to be paid for what it has left, and returns what was overpaid', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ']);
    $ctx = $this->contextFor($owner, $org);
    $service = app(InvoiceService::class);
    $wallets = app(WalletService::class);
    $ledger = app(LedgerService::class);
    $wallets->topup($org, Money::minor(500000, 'CZK'), 'bank', 'cn-topup', $ctx);
    $invoice = creditNotePostpaidInvoice($org, $ctx);
    $backup = $invoice->lines()->get()->last();
    $service->creditNote($invoice, 'Zálohy nebyly dodány', $ctx, [$backup->id]);

    // the customer pays from the credit: 1 210, not the printed 1 815
    $this->actingAs($owner, 'sanctum')->withHeader('X-Organization', $org->id);
    $paid = $this->withHeader('Idempotency-Key', 'cn-pay')->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'])->assertOk()->json();
    $this->flushHeaders();
    expect($paid['paid']['minor'])->toBe(121000)->and($invoice->refresh()->state)->toBe(Invoice::PAID)->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(500000 - 121000)
        ->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0);

    // the rest is credited after it was paid: the money returns to the credit — as credit that cannot be paid out beyond what was really paid in
    $service->creditNote($invoice->refresh(), 'VPS nebylo možné dodat', $ctx);
    expect($invoice->refresh()->state)->toBe(Invoice::CREDITED)->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(500000)
        ->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0)->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(0)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(500000)->and($ledger->verifyInvariant()['balanced'])->toBeTrue();
});
