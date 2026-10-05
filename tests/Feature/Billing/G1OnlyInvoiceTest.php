<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\CzkTaxStatement;
use Onhost\Domain\Invoicing\InvoicePdfRenderer;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as S;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * G1 (owner decision G-R1): only the invoice is a tax document. Paying an invoice that was already issued — by card, by bank
 * transfer or from the credit — settles it; it does not also issue a receipt with VAT, so the same sale is never on two tax
 * documents (both counted in CzkTaxStatement::TYPES, both in the VAT return). A top-up of credit for a customer who is not a VAT
 * payer is confirmed by a payment confirmation that is not a tax document. VAT is booked once in the ledger and stated once on
 * the tax documents.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

/** A Czech company registered for VAT: its number was checked in VIES today. */
function g1VatPayer(Organization $org): Organization
{
    $org->forceFill(['vat_id' => 'CZ12345678', 'vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_number' => 'CZ12345678', 'vat_checked_at' => now()])->save();

    return $org->refresh();
}

/** An issued postpaid renewal invoice: 89 Kč + 21 % VAT, booked (receivable, revenue, VAT) when it is issued. */
function g1IssuedInvoice(User $owner, Organization $org, object $test): Invoice
{
    $service = app(InvoiceService::class);
    $ctx = new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'test-session');
    $draft = $service->draft($org, 'invoice', 'CZK', [
        ['sku' => 'web-hosting-start', 'description' => 'Webhosting Start — říjen 2026', 'qty' => 1, 'unit_net' => 8900, 'discount' => 0, 'net' => 8900, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 1869, 'total' => 10769, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31'],
    ], $ctx, null, ['postpaid' => true, 'payment_method' => 'bank']);

    return $service->issue($draft, $ctx);
}

/** A card payment the gateway confirmed, for an invoice or a top-up (the gateway itself is not asked: settle() is what its callback leads to). */
function g1CardIntent(Organization $org, int $amount, string $purpose, ?string $referenceType, ?string $referenceId): PaymentIntent
{
    return PaymentIntent::query()->create([
        'organization_id' => $org->id, 'provider' => 'comgate', 'provider_id' => 'G1-'.bin2hex(random_bytes(4)), 'purpose' => $purpose, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
        'amount_minor' => $amount, 'currency' => 'CZK', 'state' => S::PENDING_CUSTOMER, 'method' => 'card', 'idempotency_key' => 'g1:'.bin2hex(random_bytes(6)), 'created_by' => 'test',
    ]);
}

/** The organization's tax documents (what the VAT return and the CZK recap count). @return \Illuminate\Support\Collection<int, Invoice> */
function g1TaxDocuments(Organization $org): Collection
{
    return Invoice::query()->where('organization_id', $org->id)->whereIn('type', CzkTaxStatement::TYPES)->where('state', '!=', Invoice::DRAFT)->get();
}

/** VAT booked once: in the ledger, and on the tax documents, both exactly the invoice's VAT. */
function g1AssertVatOnce(Organization $org, int $vat): void
{
    $ledger = app(LedgerService::class);
    expect($ledger->verifyInvariant()['balanced'])->toBeTrue()
        ->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe($vat)
        ->and((int) g1TaxDocuments($org)->sum('tax_minor'))->toBe($vat)
        ->and($ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0);
}

it('settles an issued invoice paid by card without a second tax document', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $org = g1VatPayer($org); // even a VAT payer, who gets a receipt for a top-up, gets none for paying an invoice
    $invoice = g1IssuedInvoice($owner, $org, $this);

    expect(app(PaymentService::class)->settle(g1CardIntent($org, 10769, 'invoice', 'invoice', $invoice->id), CommandContext::system('webhook:comgate'), 'card'))->toBeTrue();

    expect($invoice->refresh()->state)->toBe(Invoice::PAID)->and((int) $invoice->paid_minor)->toBe(10769)
        ->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->count())->toBe(0)
        ->and(g1TaxDocuments($org)->pluck('id')->all())->toBe([$invoice->id])
        ->and(app(LedgerService::class)->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(0);
    g1AssertVatOnce($org, 1869);
});

it('settles an issued invoice paid by bank transfer without a second tax document', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $invoice = g1IssuedInvoice($owner, $org, $this);
    $this->actingAs($owner, 'sanctum');
    $vs = (string) $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'bank'], ['Idempotency-Key' => 'g1-bank-1'])->assertOk()->json('instructions.variable_symbol');

    $this->actingAs($this->staff('billing_operator'), 'sanctum');
    $this->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => $vs, 'amount' => '107.69', 'currency' => 'CZK', 'external_id' => 'g1-stmt-1'], ['Idempotency-Key' => 'g1-line-1'])->assertCreated()->assertJsonPath('result', 'matched');

    expect($invoice->refresh()->state)->toBe(Invoice::PAID)
        ->and(Invoice::query()->where('organization_id', $org->id)->whereNotIn('type', ['invoice'])->count())->toBe(0) // no receipt, nothing else either
        ->and(g1TaxDocuments($org)->pluck('id')->all())->toBe([$invoice->id]);
    g1AssertVatOnce($org, 1869);
});

it('pays an issued invoice from credit without a tax document for the payment, and a non-payer\'s top-up is no tax document either', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $invoice = g1IssuedInvoice($owner, $org, $this);
    app(PaymentService::class)->settle(g1CardIntent($org, 50000, 'topup', 'wallet', $org->id), CommandContext::system('webhook:comgate'), 'card');

    $confirmation = Invoice::query()->where('organization_id', $org->id)->where('type', InvoiceService::PAYMENT_CONFIRMATION)->sole();
    expect($confirmation->state)->toBe(Invoice::PAID)->and((int) $confirmation->total_minor)->toBe(50000)->and((int) $confirmation->tax_minor)->toBe(0)
        ->and((int) $confirmation->subtotal_minor)->toBe(50000)->and($confirmation->tax_summary)->toBe([])
        ->and(in_array($confirmation->type, CzkTaxStatement::TYPES, true))->toBeFalse()
        ->and($confirmation->lines()->sole()->tax_minor)->toBe(0)
        ->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->count())->toBe(0);

    $this->actingAs($owner, 'sanctum');
    $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'], ['Idempotency-Key' => 'g1-wallet-1'])->assertOk()->assertJsonPath('state', Invoice::PAID);

    expect(g1TaxDocuments($org)->pluck('id')->all())->toBe([$invoice->id])
        ->and(Invoice::query()->where('organization_id', $org->id)->count())->toBe(2) // the invoice and the confirmation of the top-up, nothing for the payment
        ->and(app(LedgerService::class)->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(50000 - 10769);
    g1AssertVatOnce($org, 1869);
});

it('issues a tax receipt for a VAT payer\'s top-up, and only one document per payment, however often the callback comes', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $org = g1VatPayer($org);
    $intent = g1CardIntent($org, 12100, 'topup', 'wallet', $org->id);
    $payments = app(PaymentService::class);
    $payments->settle($intent, CommandContext::system('webhook:comgate'), 'card');
    $payments->settle($intent->refresh(), CommandContext::system('webhook:comgate'), 'card');

    $receipt = Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->sole();
    expect((int) $receipt->total_minor)->toBe(12100)->and((int) $receipt->tax_minor)->toBe(2100)->and($receipt->meta['payment_intent_id'])->toBe($intent->id)
        ->and(Invoice::query()->where('organization_id', $org->id)->count())->toBe(1)
        ->and(app(InvoiceService::class)->issueTopupDocument($org, Money::minor(12100, 'CZK'), 'card', CommandContext::system('test'), $intent->id)->id)->toBe($receipt->id); // asked again: the same document
});

it('turns what an invoice could not take into credit with its own top-up document, never a second tax document of the invoice', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $invoice = g1IssuedInvoice($owner, $org, $this);
    $intent = g1CardIntent($org, 10769, 'invoice', 'invoice', $invoice->id);
    // finance credits part of the invoice while the customer is at the gateway: 50 Kč less is owed than the payment brings
    $note = app(InvoiceService::class)->creditNote($invoice, 'Sleva za výpadek', $this->staffContextFor($this->staff('billing_finance_admin'), $org), null, null, [(string) $invoice->lines()->sole()->id => 5000]);

    app(PaymentService::class)->settle($intent, CommandContext::system('webhook:comgate'), 'card');

    expect($invoice->refresh()->state)->toBe(Invoice::PAID)->and((int) $invoice->paid_minor)->toBe(5769)
        ->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->count())->toBe(0);
    $confirmation = Invoice::query()->where('organization_id', $org->id)->where('type', InvoiceService::PAYMENT_CONFIRMATION)->sole();
    expect((int) $confirmation->total_minor)->toBe(5000)->and($confirmation->meta['payment_intent_id'])->toBe($intent->id)
        ->and(app(LedgerService::class)->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(5000);
    g1AssertVatOnce($org, 1869 + (int) $note->tax_minor); // the invoice's VAT less what the credit note took back
});

it('keeps the receipt of a proforma payment: the proforma is no tax document, the receipt is the only one', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = app(InvoiceService::class);
    $ctx = $this->contextFor($owner, $org);
    $proforma = $service->issue($service->draft($org, 'proforma', 'CZK', [
        ['sku' => 'web-hosting-start', 'description' => 'Webhosting Start', 'qty' => 1, 'unit_net' => 8900, 'discount' => 0, 'net' => 8900, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 1869, 'total' => 10769],
    ], $ctx, null, ['payment_method' => 'bank']), $ctx);

    app(PaymentService::class)->settle(g1CardIntent($org, 10769, 'invoice', 'invoice', $proforma->id), CommandContext::system('webhook:comgate'), 'card');

    expect(g1TaxDocuments($org)->pluck('type')->all())->toBe(['receipt']);
});

it('names a document that is not a tax document as such on its PDF', function () {
    expect(InvoicePdfRenderer::titleFor('receipt'))->toContain('Daňový doklad k přijaté platbě')
        ->and(InvoicePdfRenderer::titleFor(InvoiceService::PAYMENT_CONFIRMATION))->toContain('není daňový doklad')
        ->and(InvoicePdfRenderer::titleFor('statement'))->toContain('není daňový doklad')
        ->and(InvoicePdfRenderer::titleFor('invoice'))->toContain('daňový doklad');
});

it('reports historical invoices that also got a receipt for their own payment, and changes nothing', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $invoice = g1IssuedInvoice($owner, $org, $this);
    $intent = g1CardIntent($org, 10769, 'invoice', 'invoice', $invoice->id);
    $intent->forceFill(['state' => S::SUCCEEDED, 'paid_at' => now()])->save();
    // what the old settlement wrote: a receipt with VAT for the money that paid the invoice
    $receipt = app(InvoiceService::class)->issueReceipt($org, Money::minor(10769, 'CZK'), 'card', CommandContext::system('test'), $intent->id);
    app(InvoiceService::class)->markPaid($invoice, $invoice->total(), 'card', CommandContext::system('test'), postLedger: false);
    $before = Invoice::query()->orderBy('id')->get(['id', 'type', 'state', 'meta', 'updated_at'])->toArray();

    expect(Artisan::call('onhost:billing:double-tax-report', ['--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true);
    expect($report['pairs'])->toHaveCount(1)
        ->and($report['pairs'][0])->toMatchArray(['invoice_number' => $invoice->number, 'receipt_number' => $receipt->number, 'payment_intent_id' => $intent->id, 'receipt_tax_minor' => 1869, 'currency' => 'CZK'])
        ->and($report['totals']['CZK'])->toMatchArray(['pairs' => 1, 'receipt_tax_minor' => 1869]);
    expect(Artisan::call('onhost:billing:double-tax-report'))->toBe(0);
    expect(Artisan::output())->toContain($invoice->number)->toContain($receipt->number);
    expect(Invoice::query()->orderBy('id')->get(['id', 'type', 'state', 'meta', 'updated_at'])->toArray())->toBe($before); // read only
});
