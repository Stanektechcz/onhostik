<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as S;

/* Every document can be paid by bank transfer: the panel asks for instructions, finance matches the statement line, the invoice closes. */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

it('pays an issued invoice by bank transfer: instructions with a symbol, listed as pending, settled by the statement line', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'ico' => '12345678']);
    $service = app(InvoiceService::class);
    $ctx = $this->contextFor($owner, $org);
    $draft = $service->draft($org, 'invoice', 'CZK', [
        ['sku' => 'web-hosting-start', 'description' => 'Webhosting Start — září 2026', 'qty' => 1, 'unit_net' => 8900, 'discount' => 0, 'net' => 8900, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 1869, 'total' => 10769, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30'],
    ], $ctx, null, ['postpaid' => true, 'payment_method' => 'bank']);
    $invoice = $service->issue($draft, $ctx);

    $this->actingAs($owner, 'sanctum');
    $bank = $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'bank'], ['Idempotency-Key' => 'inv-bank-1'])->assertOk();
    expect($bank->json('provider'))->toBe('bank')->and($bank->json('amount.minor'))->toBe(10769)->and($bank->json('instructions.variable_symbol'))->not->toBeEmpty()->and($bank->json('instructions.message'))->toContain($invoice->number);
    $vs = (string) $bank->json('instructions.variable_symbol');
    expect($vs)->toBe($invoice->payment_reference);

    // asking twice returns the same pending transfer, and the panel lists it with its instructions
    $again = $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'bank'], ['Idempotency-Key' => 'inv-bank-2'])->assertOk();
    expect($again->json('payment_intent_id'))->toBe($bank->json('payment_intent_id'))->and(PaymentIntent::query()->where('purpose', 'invoice')->count())->toBe(1);
    $list = $this->getJson('/v1/payments')->assertOk();
    expect($list->json('data.0.purpose'))->toBe('invoice')->and($list->json('data.0.instructions.variable_symbol'))->toBe($vs)->and($list->json('data.0.state'))->toBe(S::PENDING_CUSTOMER);

    // the money arrives: finance records the statement line, the invoice is paid and the wallet is back at zero
    $this->actingAs($this->staff('billing_operator'), 'sanctum');
    $this->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => $vs, 'amount' => '107.69', 'currency' => 'CZK', 'external_id' => 'stmt-inv-1'], ['Idempotency-Key' => 'inv-line-1'])->assertCreated()->assertJsonPath('result', 'matched')->assertJsonPath('purpose', 'invoice');
    $paid = Invoice::query()->findOrFail($invoice->id);
    expect($paid->state)->toBe(Invoice::PAID)->and((int) $paid->paid_minor)->toBe(10769)->and($paid->payment_method)->toBe('bank');
    $this->actingAs($owner, 'sanctum');
    $this->getJson('/v1/wallet')->assertOk()->assertJsonPath('data.spendable.minor', 0);
    $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'bank'], ['Idempotency-Key' => 'inv-bank-3'])->assertStatus(409)->assertJsonPath('error', 'invoice_not_payable');
});

it('returns the order transfer for a proforma and rejects unknown methods', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 1, null, $org);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $placed = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'inv-bank-proforma', $this->contextFor($owner, $org));
    $proforma = Invoice::query()->findOrFail($placed['order']->invoice_id);

    $response = $this->postJson("/v1/invoices/{$proforma->id}/pay", ['method' => 'bank'], ['Idempotency-Key' => 'pf-bank-1'])->assertOk();
    expect($response->json('instructions.variable_symbol'))->toBe($placed['bank_instructions']['variable_symbol'])->and(PaymentIntent::query()->where('organization_id', $org->id)->count())->toBe(1); // no second symbol for the same order
    $this->postJson("/v1/invoices/{$proforma->id}/pay", ['method' => 'crypto'], ['Idempotency-Key' => 'pf-bank-2'])->assertUnprocessable();
});
