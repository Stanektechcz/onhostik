<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandContext;

/*
 * F12b: the bus key of a staff credit note and of a "mark paid" named the operation and the Idempotency-Key header, not the
 * invoice (invoice.pay got the invoice in TASK-0063). The bus keeps its keys per organization and person, so the same header
 * on a second invoice of the organization met the first invoice's key: refused as a reused key at best, and the right answer
 * only by the luck of the HTTP layer's own reservation. The key names the invoice, as invoice.pay's does.
 */

/** Two postpaid invoices of one organization, and a finance admin signed in with a fresh step-up. */
beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ']);
    $ctx = $this->contextFor($owner, $org);
    $this->f12bInvoices = [f12bPostpaidInvoice($org, $ctx), f12bPostpaidInvoice($org, $ctx)];
    $finance = $this->staff('billing_finance_admin');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $this->actingAs($finance, 'sanctum');
});

function f12bPostpaidInvoice(Organization $org, CommandContext $ctx): Invoice
{
    $service = app(InvoiceService::class);

    return $service->issue($service->draft($org, 'invoice', 'CZK', [
        ['sku' => 'vps', 'description' => 'VPS Compute 4', 'qty' => 1, 'unit_net' => 100000, 'discount' => 0, 'net' => 100000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 21000, 'total' => 121000],
    ], $ctx, null, ['postpaid' => true, 'payment_method' => 'bank']), $ctx);
}

it('keeps the invoice in the bus key of a credit note', function () {
    [$first, $second] = $this->f12bInvoices;

    $this->withHeader('Idempotency-Key', 'f12b-credit-1')->postJson("/v1/invoices/{$first->id}/credit-note", ['reason' => 'Služba nebyla dodána'])->assertCreated();
    $this->withHeader('Idempotency-Key', 'f12b-credit-2')->postJson("/v1/invoices/{$second->id}/credit-note", ['reason' => 'Služba nebyla dodána'])->assertCreated();
    $this->flushHeaders();

    $busKeys = DB::table('idempotency_keys')->where('key', 'like', 'invoice.credit:%')->pluck('key')->sort()->values();
    expect($busKeys->all())->toBe(["invoice.credit:{$first->id}:f12b-credit-1", "invoice.credit:{$second->id}:f12b-credit-2"])
        ->and($second->refresh()->state)->toBe(Invoice::CREDITED);
});

it('keeps the invoice in the bus key of mark paid', function () {
    [$first, $second] = $this->f12bInvoices;
    $body = ['method' => 'bank', 'reference' => 'VS-2026-1', 'reason' => 'Platba dohledána na výpisu'];

    $this->withHeader('Idempotency-Key', 'f12b-paid-1')->postJson("/v1/invoices/{$first->id}/mark-paid", $body)->assertOk();
    $this->withHeader('Idempotency-Key', 'f12b-paid-2')->postJson("/v1/invoices/{$second->id}/mark-paid", $body)->assertOk();
    $this->flushHeaders();

    $busKeys = DB::table('idempotency_keys')->where('key', 'like', 'invoice.markpaid:%')->pluck('key')->sort()->values();
    expect($busKeys->all())->toBe(["invoice.markpaid:{$first->id}:f12b-paid-1", "invoice.markpaid:{$second->id}:f12b-paid-2"])
        ->and($first->refresh()->state)->toBe(Invoice::PAID)->and($second->refresh()->state)->toBe(Invoice::PAID);
});
