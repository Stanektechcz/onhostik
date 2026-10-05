<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * G6: "Nový doklad … · splatnost 14 dní" was said of every issued invoice — also of the final invoice of an order paid on a
 * proforma (G2), which is issued with nothing left to pay (due at once, paid by its advance). A paid document has no payment
 * term to announce; an invoice the customer still owes keeps it.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

function g6IssuedInvoice(Organization $org, int $dueDays, bool $paid, array $meta = []): Invoice
{
    $ctx = CommandContext::system('test')->withScope($org->id);
    $invoices = app(InvoiceService::class);
    $draft = $invoices->draft($org, 'invoice', 'CZK', [['sku' => 'web', 'description' => 'Webhosting', 'qty' => 1, 'unit' => 'ks', 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 2100, 'total' => 12100]], $ctx, null, $meta);
    $invoice = $invoices->issue($draft, $ctx, $dueDays);
    if ($paid) {
        $invoices->markPaid($invoice, $invoice->total(), 'card', $ctx, postLedger: false);
    }
    app(OutboxPublisher::class)->relayPending();

    return $invoice->refresh();
}

function g6IssuedNotice(Invoice $invoice): string
{
    return (string) Notification::query()->where('event', 'invoice.issued')->where('ref_id', $invoice->id)->value('body');
}

it('announces no payment term for a final invoice issued already paid', function () {
    [, $org] = $this->customerWithOrganization();
    $final = g6IssuedInvoice($org, 0, true, ['advances' => [['proforma_number' => 'ZF-1']]]);

    expect(data_get(OutboxMessage::query()->where('name', 'invoice.issued')->where('aggregate_id', $final->id)->sole()->payload, 'due_days'))->toBe(0)
        ->and(g6IssuedNotice($final))->not->toBe('')->not->toContain('splatnost');
});

it('keeps the payment term of an invoice the customer still owes', function () {
    [, $org] = $this->customerWithOrganization();
    $postpaid = g6IssuedInvoice($org, 14, false, ['postpaid' => true]);

    expect(g6IssuedNotice($postpaid))->toContain(' · splatnost 14 dní');
});

it('says nothing of a payment term when the invoice was paid before the notice went out', function () {
    [, $org] = $this->customerWithOrganization();
    $paidMeanwhile = g6IssuedInvoice($org, 14, true, ['postpaid' => true]); // relayed after the payment: the term is over

    expect(g6IssuedNotice($paidMeanwhile))->not->toContain('splatnost');
});
