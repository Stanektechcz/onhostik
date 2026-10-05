<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\CzkTaxStatement;
use Onhost\Domain\Invoicing\InvoicePdfRenderer;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Invoicing\UblExporter;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as S;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\Tax\Commands\SetVatPayerModeCommand;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\Tax\VatPayerMode;
use Onhost\Domain\Tax\VatReports;
use Onhost\Domain\Tax\VatRounding;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * G2 (owner decision G-R1): only the invoice is a tax document, and the platform runs in either VAT mode.
 *
 *  · the mode is configuration (ONHOST_VAT_PAYER → config vat.payer) written to the legal entity (onhost:vat:payer-mode --apply,
 *    through the CommandBus); the doctor says which mode is in force and what to do when the two disagree; a document keeps the
 *    seller it was issued by (the frozen snapshot), whatever the mode is later;
 *  · a VAT payer's tax document carries every particular of § 29: both parties with their numbers, number, dates incl. the DUZP,
 *    what was supplied, base, rate and VAT per rate, the total; VAT in CZK at the ČNB rate of the DUZP for another currency;
 *    the reverse charge note; VAT rounded to the haléř (§ 37);
 *  · a proforma is no tax document and has its own series; its payment gets the tax document for the received payment (a payer)
 *    or a payment confirmation (a non-payer); the final invoice deducts the advance and names its tax document; an advance tax
 *    document is corrected by a credit note of its own;
 *  · a non-payer's EUR invoice does not enter the CZK VAT recap;
 *  · KH and SH drafts for the accountant, read only.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    config(['onhost.billing.fx.fetch' => false]);
    $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Europe/Prague'));
});

function g2Ctx(): CommandContext
{
    return CommandContext::system('test:g2');
}

/** A Czech company registered for VAT (checked in VIES today). */
function g2CzPayer(Organization $org): Organization
{
    $org->forceFill(['ico' => '12345678', 'dic' => 'CZ12345678', 'vat_id' => 'CZ12345678', 'vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_number' => 'CZ12345678', 'vat_checked_at' => now(),
        'street' => 'Dlouhá 1', 'city' => 'Brno', 'postal_code' => '602 00'])->save();

    return $org->refresh();
}

/** A Slovak company registered for VAT (checked in VIES today): an EU B2B buyer, reverse charge. */
function g2SkPayer(Organization $org): Organization
{
    $org->forceFill(['country' => 'SK', 'ico' => '87654321', 'vat_id' => 'SK1234567890', 'vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_number' => 'SK1234567890', 'vat_checked_at' => now(), 'customer_class' => 'b2b'])->save();

    return $org->refresh();
}

function g2SellerNotPayer(): void
{
    // through the model (its saved event drops the cached mode, as the bus handler does), never a mass update
    LegalEntity::query()->get()->each(fn (LegalEntity $e) => $e->forceFill(['vat_payer' => false])->save());
}

/** A member of staff of finance with a fresh step-up acting as staff. */
function g2FinanceContext(User $user): CommandContext
{
    return new CommandContext('user', $user->id, null, null, '127.0.0.1', 'pest', 'test-session', stepUpMethod: 'totp', staffMode: true);
}

/**
 * Switches the mode the way staff do: the bus asks for a second person (CRITICAL), somebody else approves, the same request
 * is repeated with the approval and consumes it.
 */
function g2SwitchMode(User $finance, bool $payer, string $reason = 'Registrace k DPH zrušena finančním úřadem'): array
{
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $bus = app(CommandBus::class);
    $payload = ['payer' => $payer, 'reason' => $reason];
    try {
        $bus->dispatch(new SetVatPayerModeCommand('g2-mode-ask-'.bin2hex(random_bytes(4)), $payload), g2FinanceContext($finance));
        throw new RuntimeException('the switch ran without a second person');
    } catch (DomainError $e) {
        expect($e->error)->toBe('approval_required');
        $approvalId = (string) $e->extra['approval_id'];
    }
    secondPersonApproves($approvalId);
    $ctx = new CommandContext('user', $finance->id, null, null, '127.0.0.1', 'pest', 'test-session', stepUpMethod: 'totp', approvalIds: [$approvalId], staffMode: true);

    return (array) $bus->dispatch(new SetVatPayerModeCommand('vat.payer-mode:'.$approvalId, $payload), $ctx);
}

/** An issued invoice: 200,00 at 21 % and 50,00 at 12 % (or the lines given), in the currency given. @param list<array<string,mixed>>|null $lines */
function g2Invoice(Organization $org, string $currency = 'CZK', ?array $lines = null, string $type = 'invoice'): Invoice
{
    $service = app(InvoiceService::class);
    $lines ??= [
        ['sku' => 'web-hosting-start', 'description' => 'Webhosting Start — říjen 2026', 'qty' => 2, 'unit' => 'ks', 'unit_net' => 10000, 'discount' => 0, 'net' => 20000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 4200, 'total' => 24200, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31'],
        ['sku' => 'book', 'description' => 'Příručka správce', 'qty' => 1, 'unit' => 'ks', 'unit_net' => 5000, 'discount' => 0, 'net' => 5000, 'tax_rate' => '12', 'tax_category' => 'S', 'tax' => 600, 'total' => 5600],
    ];

    return $service->issue($service->draft($org, $type, $currency, $lines, g2Ctx(), null, ['postpaid' => true, 'payment_method' => 'bank']), g2Ctx());
}

function g2Html(Invoice $invoice): string
{
    return app(InvoicePdfRenderer::class)->html($invoice->refresh());
}

function g2EurRate(string $validOn, int $rateMicro): void
{
    ExchangeRate::query()->create(['source' => 'cnb', 'currency' => 'EUR', 'valid_on' => $validOn, 'amount' => 1, 'rate_micro' => $rateMicro, 'fetched_at' => now()]);
}

/** The organization's tax documents (what the VAT return counts). @return Collection<int, Invoice> */
function g2TaxDocuments(Organization $org): Collection
{
    return Invoice::query()->where('organization_id', $org->id)->whereIn('type', CzkTaxStatement::TYPES)->where('state', '!=', Invoice::DRAFT)->orderBy('created_at')->get();
}

/** A bank-transfer order for a web hosting: the proforma is issued, the transfer arrives (booked yesterday) and is matched. @return array{order:Order, proforma:Invoice} */
function g2BankOrderPaid(object $test, Organization $org, $owner): array
{
    ['order' => $order, 'proforma' => $proforma] = g2BankOrderPlaced($org, $owner);

    return g2PayBankOrder($order, $proforma);
}

/** A bank-transfer order placed: its proforma is issued, nothing is paid yet. @return array{order:Order, proforma:Invoice} */
function g2BankOrderPlaced(Organization $org, $owner): array
{
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2b'], 1, null, $org);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $placed = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'g2-bank-'.bin2hex(random_bytes(3)), new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'test-session'));
    $order = $placed['order']->refresh();

    return ['order' => $order, 'proforma' => Invoice::query()->findOrFail($order->invoice_id)];
}

/** The transfer of a placed bank order arrives (booked yesterday) and is matched. @return array{order:Order, proforma:Invoice} */
function g2PayBankOrder(Order $order, Invoice $proforma): array
{
    $line = BankStatementLine::query()->create([
        'account' => 'CZ00', 'amount_minor' => $order->total_minor, 'currency' => 'CZK', 'variable_symbol' => $proforma->payment_reference,
        'counterparty' => 'Test s.r.o.', 'booked_at' => now()->subDay(), 'external_id' => 'g2-stmt-'.bin2hex(random_bytes(3)), 'state' => 'unmatched',
    ]);
    expect(app(PaymentService::class)->matchBankLine($line, CommandContext::system('bank:test')))->not->toBeNull();

    return ['order' => $order->refresh(), 'proforma' => $proforma->refresh()];
}

// ── 1. the mode ──────────────────────────────────────────────────────────────────────────────────────────────────────────────

it('takes the mode from configuration and the legal entity: payer by default, switched by staff with step-up and four eyes, told by the doctor', function () {
    expect(app(VatPayerMode::class)->isPayer())->toBeTrue()->and(app(InvoiceService::class)->sellerIsVatPayer())->toBeTrue();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $row = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('check', 'VAT payer mode');
    expect($row['status'])->toBe('OK')->and($row['detail'])->toContain('VAT payer');

    // the operator declares the company is no VAT payer: until staff switch the legal entity, the doctor says so and how
    config(['vat.payer' => false]);
    Artisan::call('onhost:doctor', ['--json' => true]);
    $row = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('check', 'VAT payer mode');
    expect($row['status'])->not->toBe('OK')->and($row['remedy'])->toContain('/v1/staff/tax/vat-payer-mode');
    expect(Artisan::call('onhost:vat:payer-mode'))->toBe(1); // only shows: the two disagree

    $result = g2SwitchMode($this->staff('billing_finance_admin'), false);
    expect($result['changed'])->toBeTrue()->and(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeFalse()
        ->and(app(VatPayerMode::class)->isPayer())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'tax.vat_payer_mode.set')->where('result', 'succeeded')->exists())->toBeTrue()
        ->and(LegalEntity::query()->findOrFail('onhost-cz')->meta['vat_payer_history'][0]['payer'] ?? null)->toBeFalse();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $row = collect(json_decode(Artisan::output(), true)['checks'])->firstWhere('check', 'VAT payer mode');
    expect($row['status'])->toBe('OK')->and($row['detail'])->toContain('not a VAT payer');

    // a non-payer charges no VAT: the tax engine decides 0 % "E" for a domestic customer, whatever the rule set says
    $decision = app(TaxEngine::class)->calculate(['country' => 'CZ', 'customer_class' => 'b2c'], [['key' => 'a', 'net' => Money::minor(10000, 'CZK')]], 'CZK');
    expect($decision['lines'][0]['rate'])->toBe('0')->and($decision['lines'][0]['category'])->toBe(TaxEngine::CAT_EXEMPT);
});

it('never changes a document issued before the mode was switched', function () {
    [, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    $before = g2Invoice($org);
    $frozen = $before->refresh()->only(['number', 'type', 'seller', 'buyer', 'tax_summary', 'tax_minor', 'total_minor', 'supply_date', 'pdf_hash', 'meta', 'structured']);
    $htmlBefore = g2Html($before);

    g2SwitchMode($this->staff('billing_finance_admin'), false);
    app(InvoiceService::class)->completeCzkStatements(); // nothing to complete, nothing touched

    expect($before->refresh()->only(array_keys($frozen)))->toEqual($frozen)
        ->and($before->seller['vat_payer'])->toBeTrue()
        ->and(g2Html($before))->toBe($htmlBefore)->and($htmlBefore)->toContain('daňový doklad');
    $after = g2Invoice($org, 'CZK', [['sku' => 'web-hosting-start', 'description' => 'Webhosting Start', 'qty' => 1, 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '0', 'tax_category' => 'E', 'tax' => 0, 'total' => 10000]]);
    expect($after->seller['vat_payer'])->toBeFalse()->and(g2Html($after))->not->toContain('daňový doklad /')->and(g2Html($after))->toContain('Neplátce DPH');
});

// ── 2. a payer's tax document ────────────────────────────────────────────────────────────────────────────────────────────────

it('prints every particular of § 29 on a payer\'s invoice, in Czech and English, with the VAT recap per rate', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Odběratel s.r.o.']);
    $org = g2CzPayer($org);
    $invoice = g2Invoice($org);
    $html = g2Html($invoice);

    expect($invoice->number)->toStartWith('FV-')->and($invoice->supply_date?->format('Y-m-d'))->toBe('2026-10-15')
        ->and($invoice->tax_summary)->toBe([['rate' => '21', 'category' => 'S', 'net' => 20000, 'tax' => 4200], ['rate' => '12', 'category' => 'S', 'net' => 5000, 'tax' => 600]]);
    foreach ([
        'Faktura – daňový doklad', 'Invoice', $invoice->number, 'ONhost s.r.o.', 'IČO 00000000', 'DIČ CZ00000000', 'Odběratel s.r.o.', 'IČO 12345678', 'DIČ CZ12345678',
        'Datum vystavení / Date of issue: 15.10.2026', 'Datum uskutečnění zdanitelného plnění / Date of taxable supply: 15.10.2026',
        'Webhosting Start — říjen 2026', 'Příručka správce', 'Rekapitulace DPH / VAT summary', 'Základ daně / Tax base', 'Sazba / Rate',
        '200 Kč', '42 Kč', '50 Kč', '6 Kč', '298 Kč', 'Celkem k úhradě / Total due', 'Dlouhá 1',
    ] as $particular) {
        expect(str_contains($html, $particular) ? $particular : 'missing: '.$particular)->toBe($particular);
    }
});

it('rounds VAT to the haléř half away from zero (§ 37) — also when it is extracted from a received amount', function () {
    expect(VatRounding::taxFromNet(10, '21'))->toBe(2) // 2.1
        ->and(VatRounding::taxFromNet(-10, '21'))->toBe(-2)
        ->and(VatRounding::taxFromNet(250, '12'))->toBe(30)
        ->and(VatRounding::taxFromNet(5, '10'))->toBe(1) // 0.5 → 1
        ->and(VatRounding::taxFromGross(100006, '21'))->toBe(17356) // 17 356.41, never the 17 357 that truncating the base gave
        ->and(VatRounding::taxFromGross(121, '21'))->toBe(21);

    [, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    $receipt = app(InvoiceService::class)->issueReceipt($org, Money::minor(100006, 'CZK'), 'card', g2Ctx(), 'pi_g2_round');
    expect($receipt->type)->toBe('receipt')->and((int) $receipt->tax_minor)->toBe(17356)->and((int) $receipt->subtotal_minor)->toBe(82650)
        ->and($receipt->meta['rounding'] ?? null)->toBe(VatRounding::METHOD);
    expect(g2Html($receipt))->toContain('zaokrouhlena na haléře');
});

it('states the VAT of a payer\'s EUR invoice in CZK at the ČNB rate of the DUZP; a non-payer\'s EUR invoice has no CZK VAT recap', function () {
    g2EurRate('2026-10-14', 24_335_000);
    g2EurRate('2026-10-15', 24_500_000);
    [, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    $payer = g2Invoice($org, 'EUR');
    expect($payer->meta['czk']['valid_on'])->toBe('2026-10-15')->and($payer->meta['czk']['tax_minor'])->toBe(102900 + 14700) // 42 € · 24,5 + 6 € · 24,5
        ->and(g2Html($payer))->toContain('Kurz ČNB');

    g2SellerNotPayer();
    $nonPayer = g2Invoice($org, 'EUR', [['sku' => 'vps', 'description' => 'VPS', 'qty' => 1, 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '0', 'tax_category' => 'E', 'tax' => 0, 'total' => 10000]]);
    expect($nonPayer->meta)->not->toHaveKey('czk')->and($nonPayer->meta)->not->toHaveKey('czk_pending')
        ->and(app(CzkTaxStatement::class)->concerns($nonPayer))->toBeFalse()
        ->and(g2Html($nonPayer))->not->toContain('Kurz ČNB');
});

it('reverse-charges an EU business with a valid VAT ID and prints the legal note with both VAT IDs', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Odberateľ s.r.o.']);
    $org = g2SkPayer($org);
    $decision = app(TaxEngine::class)->calculate(['country' => 'SK', 'customer_class' => 'b2b', 'vat_id' => 'SK1234567890', 'vat_status' => 'valid'], [['key' => 'a', 'net' => Money::minor(10000, 'CZK')]], 'CZK');
    expect($decision['lines'][0]['category'])->toBe(TaxEngine::CAT_REVERSE_CHARGE)->and($decision['lines'][0]['rate'])->toBe('0');

    $invoice = g2Invoice($org, 'CZK', [['sku' => 'vps', 'description' => 'VPS Compute 4', 'qty' => 1, 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '0', 'tax_category' => 'AE', 'tax' => 0, 'total' => 10000]]);
    $html = g2Html($invoice);
    expect($html)->toContain('Daň odvede zákazník')->and($html)->toContain('reverse charge')->and($html)->toContain('SK1234567890')->and($html)->toContain('CZ00000000');
});

it('keeps a non-payer\'s invoice to the particulars of an ordinary invoice: no DUZP, no VAT, no CZK recap, no reverse charge', function () {
    g2SellerNotPayer();
    [, $org] = $this->customerWithOrganization();
    $org = g2SkPayer($org);
    $decision = app(TaxEngine::class)->calculate(['country' => 'SK', 'customer_class' => 'b2b', 'vat_id' => 'SK1234567890', 'vat_status' => 'valid'], [['key' => 'a', 'net' => Money::minor(10000, 'CZK')]], 'CZK');
    expect($decision['lines'][0]['category'])->toBe(TaxEngine::CAT_EXEMPT); // a non-payer has nothing to reverse-charge

    $invoice = g2Invoice($org, 'CZK', [['sku' => 'vps', 'description' => 'VPS Compute 4', 'qty' => 1, 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '0', 'tax_category' => 'E', 'tax' => 0, 'total' => 10000]]);
    $html = g2Html($invoice);
    expect($invoice->number)->toStartWith('FV-')->and($html)->toContain('Faktura / Invoice')->and($html)->toContain('Neplátce DPH')
        ->and($html)->toContain('není daňovým dokladem')->and($html)->not->toContain('Datum uskutečnění zdanitelného plnění')
        ->and($html)->not->toContain('Rekapitulace DPH')->and($html)->not->toContain('Daň odvede zákazník');
});

// ── 3. proforma → payment → tax document → final invoice ────────────────────────────────────────────────────────────────────

it('runs a payer\'s advance: proforma (no tax document) → tax document for the received payment → final invoice deducting it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    ['order' => $order, 'proforma' => $proforma] = g2BankOrderPaid($this, $org, $owner);

    expect($proforma->type)->toBe('proforma')->and($proforma->number)->toStartWith('PF-')->and($proforma->state)->toBe(Invoice::PAID)
        ->and(g2Html($proforma))->toContain('není daňový doklad')->and(g2Html($proforma))->not->toContain('Datum uskutečnění zdanitelného plnění');

    $receipt = Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->sole();
    $orderTax = (int) $order->items()->sum('tax_minor');
    expect($receipt->number)->toStartWith('PP-')
        ->and($receipt->supply_date?->format('Y-m-d'))->toBe('2026-10-14') // the day the money was received, not the day it was matched
        ->and((int) $receipt->total_minor)->toBe((int) $order->total_minor)
        ->and((int) $receipt->tax_minor)->toBe($orderTax) // the VAT of the order per rate, not one rate on the gross
        ->and($receipt->meta['advance_for']['proforma_number'] ?? null)->toBe($proforma->number)
        ->and(g2Html($receipt))->toContain('Daňový doklad k přijaté platbě')->and(g2Html($receipt))->toContain($proforma->number);

    $final = Invoice::query()->findOrFail($order->invoice_id);
    expect($final->type)->toBe('invoice')->and($final->number)->toStartWith('FV-')->and($final->state)->toBe(Invoice::PAID)
        ->and((int) $final->total_minor)->toBe((int) $order->total_minor)
        ->and(collect($final->meta['advances'])->pluck('number')->all())->toBe([$receipt->number])
        ->and($final->meta['postpaid'] ?? false)->toBeFalse();
    $html = g2Html($final);
    expect($html)->toContain('Zúčtování zálohy / Advance settlement')->and($html)->toContain($receipt->number)->and($html)->toContain('Zbývá uhradit / Amount due: 0 Kč');

    // two tax documents, the VAT of the sale once: the receipt states it, the final invoice states the difference — zero
    expect(g2TaxDocuments($org)->pluck('type')->all())->toBe(['receipt', 'invoice']);
    $kh = app(VatReports::class)->kh('2026-10');
    expect(collect($kh['a5'])->sum('tax_minor'))->toBe($orderTax);
});

it('gives a non-payer\'s advance a payment confirmation and keeps the order\'s statement: no tax document at all', function () {
    g2SellerNotPayer();
    [$owner, $org] = $this->customerWithOrganization();
    ['order' => $order, 'proforma' => $proforma] = g2BankOrderPaid($this, $org, $owner);

    expect($proforma->number)->toStartWith('PF-')->and(Invoice::query()->where('organization_id', $org->id)->where('type', InvoiceService::PAYMENT_CONFIRMATION)->count())->toBe(1)
        ->and(Invoice::query()->findOrFail($order->invoice_id)->type)->toBe('statement')
        ->and(Invoice::query()->where('organization_id', $org->id)->whereIn('type', CzkTaxStatement::TYPES)->count())->toBe(0);
});

it('corrects an advance tax document with a credit note of its own, at the advance\'s rate and VAT', function () {
    [, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    $receipt = app(InvoiceService::class)->issueReceipt($org, Money::minor(12100, 'CZK'), 'card', g2Ctx(), 'pi_g2_credit');
    $note = app(InvoiceService::class)->creditNote($receipt, 'Záloha vrácena', g2Ctx());

    expect($note->type)->toBe('credit_note')->and($note->number)->toStartWith('DK-')->and($note->corrects_invoice_id)->toBe($receipt->id)
        ->and((int) $note->total_minor)->toBe(-12100)->and((int) $note->tax_minor)->toBe(-2100)
        ->and($receipt->refresh()->state)->toBe(Invoice::CREDITED)
        ->and(g2Html($note))->toContain($receipt->number)->and(g2Html($note))->toContain('Opravný daňový doklad');
});

// ── 4. KH and SH drafts ──────────────────────────────────────────────────────────────────────────────────────────────────────

it('drafts the control statement (KH) and the EC sales list (SH) of a period for the accountant, read only', function () {
    [, $payer] = $this->customerWithOrganization([], ['name' => 'Plátce s.r.o.']);
    $payer = g2CzPayer($payer);
    [, $consumer] = $this->customerWithOrganization([], ['name' => 'Jana Nováková', 'type' => 'person']);
    [, $sk] = $this->customerWithOrganization([], ['name' => 'Odberateľ s.r.o.']);
    $sk = g2SkPayer($sk);

    $big = g2Invoice($payer); // 298 Kč incl. VAT — under the 10 000 Kč threshold: A.5
    $large = g2Invoice($payer, 'CZK', [['sku' => 'vps', 'description' => 'VPS Compute 16', 'qty' => 1, 'unit_net' => 1000000, 'discount' => 0, 'net' => 1000000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 210000, 'total' => 1210000]]);
    $creditLarge = app(InvoiceService::class)->creditNote($large, 'Sleva', g2Ctx(), null, null, [(string) $large->lines()->sole()->id => 121000]);
    g2Invoice($consumer, 'CZK', [['sku' => 'web', 'description' => 'Web', 'qty' => 1, 'unit_net' => 1000, 'discount' => 0, 'net' => 1000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 210, 'total' => 1210]]);
    $reverse = g2Invoice($sk, 'CZK', [['sku' => 'vps', 'description' => 'VPS', 'qty' => 1, 'unit_net' => 500000, 'discount' => 0, 'net' => 500000, 'tax_rate' => '0', 'tax_category' => 'AE', 'tax' => 0, 'total' => 500000]]);
    $this->travelTo(Carbon::parse('2026-09-30 10:00:00', 'Europe/Prague'));
    g2Invoice($payer); // September: another period
    $this->travelTo(Carbon::parse('2026-10-16 10:00:00', 'Europe/Prague'));
    g2SellerNotPayer();
    g2Invoice($consumer, 'CZK', [['sku' => 'web', 'description' => 'Web', 'qty' => 1, 'unit_net' => 1000, 'discount' => 0, 'net' => 1000, 'tax_rate' => '0', 'tax_category' => 'E', 'tax' => 0, 'total' => 1000]]); // a non-payer's document: in no report

    $kh = app(VatReports::class)->kh('2026-10');
    expect(collect($kh['a4'])->pluck('document')->all())->toBe([$large->number, $creditLarge->number])
        ->and($kh['a4'][0])->toMatchArray(['buyer_vat_id' => 'CZ12345678', 'supply_date' => '2026-10-15', 'rate' => '21', 'base_minor' => 1000000, 'tax_minor' => 210000])
        ->and($kh['a4'][1])->toMatchArray(['base_minor' => -100000, 'tax_minor' => -21000])
        ->and(collect($kh['a5'])->firstWhere('rate', '21'))->toMatchArray(['base_minor' => 20000 + 1000, 'tax_minor' => 4200 + 210])
        ->and(collect($kh['a5'])->firstWhere('rate', '12'))->toMatchArray(['base_minor' => 5000, 'tax_minor' => 600]);

    $sh = app(VatReports::class)->sh('2026-10');
    expect($sh['rows'])->toBe([['country' => 'SK', 'vat_number' => '1234567890', 'code' => '3', 'count' => 1, 'value_czk' => 5000]]);

    $before = Invoice::query()->count();
    expect(Artisan::call('onhost:vat:export', ['report' => 'kh', '--period' => '2026-10', '--format' => 'xml']))->toBe(0);
    $xml = Artisan::output();
    expect($xml)->toContain('<DPHKH1')->and($xml)->toContain('rok="2026"')->and($xml)->toContain('mesic="10"')
        ->and($xml)->toContain('<VetaA4')->and($xml)->toContain('dic_odb="12345678"')->and($xml)->toContain('c_evid_dd="'.$large->number.'"')->and($xml)->toContain('dppd="15.10.2026"')
        ->and($xml)->toContain('zakl_dane1="10000.00"')->and($xml)->toContain('<VetaA5')->and($xml)->toContain('zakl_dane2="50.00"');
    expect(simplexml_load_string($xml))->not->toBeFalse();
    expect(Artisan::call('onhost:vat:export', ['report' => 'kh', '--period' => '2026-10', '--format' => 'csv']))->toBe(0);
    $csv = array_map('str_getcsv', array_filter(explode("\n", trim(Artisan::output()))));
    expect($csv[0])->toBe(['section', 'buyer_vat_id', 'document', 'document_type', 'supply_date', 'rate', 'base_czk', 'tax_czk'])
        ->and($csv[1])->toBe(['A.4', 'CZ12345678', $large->number, 'invoice', '2026-10-15', '21', '10000.00', '2100.00']);
    expect(Artisan::call('onhost:vat:export', ['report' => 'sh', '--period' => '2026-10', '--format' => 'xml']))->toBe(0);
    $shXml = Artisan::output();
    expect($shXml)->toContain('<DPHSHV')->and($shXml)->toContain('k_stat="SK"')->and($shXml)->toContain('c_vat="1234567890"')->and($shXml)->toContain('k_pln_eu="3"')->and($shXml)->toContain('pln_hodnota="5000"');
    expect(Artisan::call('onhost:vat:export', ['report' => 'sh', '--period' => '2026-Q4', '--format' => 'csv']))->toBe(0)
        ->and(Artisan::output())->toContain('SK,1234567890,3,1,5000');
    expect(Artisan::call('onhost:vat:export', ['report' => 'kh', '--period' => 'říjen']))->toBe(1) // a period that is no month or quarter is refused, never guessed
        ->and(Invoice::query()->count())->toBe($before); // read only
    expect($reverse->number)->not->toBeEmpty();
});

// ── 5. security review of #106 ───────────────────────────────────────────────────────────────────────────────────────────────

it('H1: switches the mode only through the staff API with a step-up and a second person; the CLI and the seeder never switch it', function () {
    $url = '/v1/staff/tax/vat-payer-mode';
    $body = ['payer' => false, 'reason' => 'Zrušení registrace k DPH od 1. 11. 2026'];

    $this->actingAs($this->staff('support_l2'), 'sanctum');
    $this->postJson($url, $body)->assertForbidden();

    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    $this->getJson($url)->assertOk()->assertJsonPath('in_force', true);
    $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $approval = (string) $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeTrue(); // nothing without the second person
    // an approval binds the request it was asked for: another body is refused with it
    $this->postJson($url, ['payer' => false, 'reason' => 'Jiný důvod, jiný požadavek', 'approval_ids' => [secondPersonApproves($approval)]])->assertForbidden();
    expect(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeTrue();
    $this->postJson($url, $body + ['approval_ids' => [$approval]])->assertOk()->assertJsonPath('payer', false)->assertJsonPath('changed', true);
    expect(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeFalse();
    // the approval was consumed: the same request again changes nothing a second time and needs a new approval
    $this->postJson($url, ['payer' => true, 'reason' => 'Zpět jako plátce DPH od 1. 12. 2026', 'approval_ids' => [$approval]])->assertForbidden();
    expect(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeFalse();

    // the command line only shows; --apply is refused and writes nothing
    config(['vat.payer' => true]);
    expect(Artisan::call('onhost:vat:payer-mode', ['--apply' => true]))->toBe(1)
        ->and(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeFalse();
    // the seeder writes the declared mode only into a legal entity it creates, never over an existing one
    $this->seed(LegalEntitySeeder::class);
    expect(LegalEntity::query()->findOrFail('onhost-cz')->vat_payer)->toBeFalse();
});

it('H2: deducts one advance per order; a second payment of the same order is a prepayment, never a second deduction', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    ['order' => $order, 'proforma' => $proforma] = g2BankOrderPaid($this, $org, $owner);
    // the customer pays the proforma again by card: the same amount, a second time
    $again = PaymentIntent::query()->create([
        'organization_id' => $org->id, 'provider' => 'comgate', 'provider_id' => 'G2-'.bin2hex(random_bytes(4)), 'purpose' => 'invoice', 'reference_type' => 'invoice', 'reference_id' => $proforma->id,
        'amount_minor' => $order->total_minor, 'currency' => 'CZK', 'state' => S::PENDING_CUSTOMER, 'method' => 'card', 'idempotency_key' => 'g2:'.bin2hex(random_bytes(6)), 'created_by' => 'test',
    ]);
    app(PaymentService::class)->settle($again, CommandContext::system('webhook:comgate'), 'card');

    $receipts = Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->orderBy('created_at')->get();
    expect($receipts)->toHaveCount(2)
        ->and($receipts[0]->meta['advance_for']['order_id'] ?? null)->toBe($order->id)
        ->and($receipts[1]->meta)->not->toHaveKey('advance_for'); // a prepayment (credit), not the advance of the order
    $final = Invoice::query()->findOrFail($order->refresh()->invoice_id);
    expect(collect($final->meta['advances'])->pluck('id')->all())->toBe([$receipts[0]->id]);
    // a retried final invoice decision deducts nothing more and makes no second final invoice
    $retry = app(InvoiceService::class)->issueForOrder($order->refresh(), g2Ctx(), 'bank');
    expect($retry->id)->toBe($final->id)->and(Invoice::query()->where('order_id', $order->id)->whereIn('type', ['invoice', 'statement'])->count())->toBe(1);
});

it('H2: never deducts more than the final invoice states, in total or per rate — PDF, UBL and KH stay at zero, never below', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    ['order' => $order, 'proforma' => $proforma] = g2BankOrderPlaced($org, $owner);
    $service = app(InvoiceService::class);
    // the order's own payment is documented first (the advance); a second payment of the same order is a prepayment …
    $first = $service->issueReceipt($org, Money::minor((int) $order->total_minor, 'CZK'), 'bank', g2Ctx(), (string) $order->payment_intent_id);
    $other = PaymentIntent::query()->create([
        'organization_id' => $org->id, 'provider' => 'comgate', 'provider_id' => 'G2-'.bin2hex(random_bytes(4)), 'purpose' => 'order', 'reference_type' => 'order', 'reference_id' => $order->id,
        'amount_minor' => $order->total_minor, 'currency' => 'CZK', 'state' => S::PENDING_CUSTOMER, 'method' => 'card', 'idempotency_key' => 'g2:'.bin2hex(random_bytes(6)), 'created_by' => 'test',
    ]);
    $second = $service->issueReceipt($org, Money::minor((int) $order->total_minor, 'CZK'), 'card', g2Ctx(), $other->id);
    expect($first->meta['advance_for']['order_id'] ?? null)->toBe($order->id)->and($second->meta)->not->toHaveKey('advance_for');
    // … and even a second advance tax document that claims the same order (as an old row could) does not fit beside the first
    $second->forceFill(['meta' => array_merge((array) $second->meta, ['advance_for' => $first->meta['advance_for']])])->save();
    ['order' => $order] = g2PayBankOrder($order, $proforma);
    $final = Invoice::query()->findOrFail($order->invoice_id);
    expect($final->type)->toBe('invoice')->and(collect($final->meta['advances'])->pluck('id')->all())->toBe([$first->id]);

    // a document whose advances claim more than it states (forged) still shows nothing negative anywhere
    $forged = g2Invoice($org);
    $forged->forceFill(['meta' => array_merge((array) $forged->meta, ['advances' => [
        ['id' => 'inv_forged_advance', 'number' => 'PP-X', 'supply_date' => '2026-10-15', 'total_minor' => 99999, 'net_minor' => 82644, 'tax_minor' => 17355, 'summary' => [['rate' => '21', 'category' => 'S', 'net' => 82644, 'tax' => 17355]], 'czk' => null],
    ]])])->save();
    $html = g2Html($forged);
    expect($html)->toContain('Zbývá uhradit / Amount due: 0 Kč')->and($html)->not->toContain('Rozdíl k vyúčtování / Difference</td><td class="num">21 %</td><td class="num">-');
    $ubl = app(UblExporter::class)->structure($forged->refresh());
    expect((float) $ubl['LegalMonetaryTotal']['PayableAmount'])->toBeGreaterThanOrEqual(0.0)
        ->and((float) $ubl['LegalMonetaryTotal']['PrepaidAmount'])->toBeLessThanOrEqual((float) $ubl['LegalMonetaryTotal']['TaxInclusiveAmount']);
    $kh = app(VatReports::class)->kh('2026-10');
    expect(collect($kh['a5'])->every(fn ($r) => $r['base_minor'] >= 0 && $r['tax_minor'] >= 0))->toBeTrue()
        ->and(implode(' ', $kh['warnings']))->toContain($forged->number);
});

it('M1: issues one document per payment however often it is asked, whichever kind is asked for', function () {
    [, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    $intent = PaymentIntent::query()->create([
        'organization_id' => $org->id, 'provider' => 'comgate', 'provider_id' => 'G2M1', 'purpose' => 'topup', 'reference_type' => 'wallet', 'reference_id' => $org->id,
        'amount_minor' => 12100, 'currency' => 'CZK', 'state' => S::PENDING_CUSTOMER, 'method' => 'card', 'idempotency_key' => 'g2m1', 'created_by' => 'test',
    ]);
    $service = app(InvoiceService::class);
    $a = $service->issueReceipt($org, Money::minor(12100, 'CZK'), 'card', g2Ctx(), $intent->id);
    $b = $service->issueTopupDocument($org, Money::minor(12100, 'CZK'), 'card', g2Ctx(), $intent->id);
    $c = $service->issueReceipt($org, Money::minor(12100, 'CZK'), 'card', g2Ctx(), $intent->id);
    expect([$b->id, $c->id])->toBe([$a->id, $a->id])
        ->and(Invoice::query()->whereIn('type', ['receipt', InvoiceService::PAYMENT_CONFIRMATION])->where('meta->payment_intent_id', $intent->id)->count())->toBe(1);
});

it('M2: a retried final invoice decision returns the document already made, even when the advance changed meanwhile', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    ['order' => $order] = g2BankOrderPaid($this, $org, $owner);
    $final = Invoice::query()->findOrFail($order->invoice_id);
    $receipt = Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->sole();
    app(InvoiceService::class)->creditNote($receipt, 'Záloha opravena', g2Ctx()); // the advance is gone now: a fresh decision would say "statement"

    $retry = app(InvoiceService::class)->issueForOrder($order->refresh(), g2Ctx(), 'bank');
    expect($retry->id)->toBe($final->id)->and(Invoice::query()->where('order_id', $order->id)->whereIn('type', ['invoice', 'statement'])->count())->toBe(1);
});

it('M3: leaves a final invoice out of the KH while its advance has no CZK rate yet, and reads the advance\'s rate from its receipt', function () {
    g2EurRate('2026-10-15', 24_500_000); // only today's list: yesterday's advance waits for its rate
    [, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    $service = app(InvoiceService::class);
    $line = ['sku' => 'vps', 'description' => 'VPS', 'qty' => 1, 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 2100, 'total' => 12100];
    $receipt = $service->issue($service->draft($org, 'receipt', 'EUR', [$line], g2Ctx(), null, ['payment_method' => 'bank']), g2Ctx(), 0, now()->subDay());
    expect($receipt->meta['czk_pending'] ?? false)->toBeTrue();
    $final = $service->issue($service->draft($org, 'invoice', 'EUR', [$line], g2Ctx(), null, ['postpaid' => false, 'payment_method' => 'bank', 'advances' => [
        ['id' => $receipt->id, 'number' => $receipt->number, 'supply_date' => '2026-10-14', 'total_minor' => 12100, 'net_minor' => 10000, 'tax_minor' => 2100, 'summary' => $receipt->tax_summary, 'czk' => null],
    ]]), g2Ctx(), 0);
    expect($final->meta['czk'] ?? null)->not->toBeNull();

    $kh = app(VatReports::class)->kh('2026-10');
    expect(implode(' ', $kh['warnings']))->toContain($final->number)->and($kh['a5'])->toBe([]); // neither the receipt nor the final invoice

    g2EurRate('2026-10-14', 24_400_000);
    $service->completeCzkStatements();
    $kh = app(VatReports::class)->kh('2026-10');
    // the receipt at 24,40 (2 100 · 24,4 = 512,40 Kč) and the final invoice's difference at 24,50 less the receipt's 24,40 (2,10 Kč)
    expect(collect($kh['a5'])->firstWhere('rate', '21'))->toMatchArray(['base_minor' => 244000 + 1000, 'tax_minor' => 51240 + 210])
        ->and($kh['warnings'])->toBe([]);
});

it('M4: flags a tax document whose DUZP falls in a period already filed, and warns in the report of that period', function () {
    config(['vat.filed_through' => '2026-10']);
    [$owner, $org] = $this->customerWithOrganization();
    $org = g2CzPayer($org);
    $this->travelTo(Carbon::parse('2026-11-01 09:00:00', 'Europe/Prague')); // the October 31 transfer is matched on November 1
    ['order' => $order] = g2BankOrderPaid($this, $org, $owner);
    $receipt = Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->sole();

    expect($receipt->supply_date?->format('Y-m-d'))->toBe('2026-10-31')->and($receipt->meta['filed_period'] ?? null)->toBe('2026-10')
        ->and(AuditEvent::query()->where('action', 'invoice.duzp_in_filed_period')->exists())->toBeTrue()
        ->and(Invoice::query()->findOrFail($order->invoice_id)->meta)->not->toHaveKey('filed_period'); // November is open
    expect(implode(' ', app(VatReports::class)->kh('2026-10')['warnings']))->toContain('2026-10')->toContain($receipt->number);
});

it('M6: reads the legal entity\'s mode once per request and again after it changes', function () {
    VatPayerMode::forget();
    DB::enableQueryLog();
    foreach (range(1, 5) as $i) {
        VatPayerMode::legalEntityIsPayer();
    }
    $reads = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'legal_entities'))->count();
    DB::disableQueryLog();
    expect($reads)->toBe(1);
    g2SellerNotPayer();
    expect(VatPayerMode::legalEntityIsPayer())->toBeFalse();
});
