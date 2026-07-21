<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Invoice numbering continuity + duplicate protection (audit D66) and
 * total/rounding consistency (D65).
 *
 * Numbering is legally significant in CZ: the series must be continuous per
 * year and a number must never be reused.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── D66: numbering ────────────────────────────────────────────────────────────

it('numbers invoices sequentially within a series and year', function (): void {
    $gen = app(InvoiceNumberGenerator::class);

    $numbers = collect(range(1, 5))
        ->map(fn (): string => $gen->next(InvoiceSeries::Czech, 2026))
        ->all();

    expect($numbers)->toBe([
        'CZ-2026-000001',
        'CZ-2026-000002',
        'CZ-2026-000003',
        'CZ-2026-000004',
        'CZ-2026-000005',
    ]);
});

it('keeps each series on its own independent counter', function (): void {
    $gen = app(InvoiceNumberGenerator::class);

    $gen->next(InvoiceSeries::Czech, 2026);
    $gen->next(InvoiceSeries::Czech, 2026);

    expect($gen->next(InvoiceSeries::Eu, 2026))->toBe('EU-2026-000001')
        ->and($gen->next(InvoiceSeries::CreditNote, 2026))->toBe('CN-2026-000001')
        ->and($gen->next(InvoiceSeries::Czech, 2026))->toBe('CZ-2026-000003');
});

it('restarts the counter at 1 for a new year', function (): void {
    $gen = app(InvoiceNumberGenerator::class);

    $gen->next(InvoiceSeries::Czech, 2026);
    $gen->next(InvoiceSeries::Czech, 2026);

    expect($gen->next(InvoiceSeries::Czech, 2027))->toBe('CZ-2027-000001')
        // …and the old year continues undisturbed.
        ->and($gen->next(InvoiceSeries::Czech, 2026))->toBe('CZ-2026-000003');
});

it('never issues the same number twice', function (): void {
    $gen = app(InvoiceNumberGenerator::class);

    $numbers = collect(range(1, 50))->map(fn (): string => $gen->next(InvoiceSeries::Czech, 2026));

    expect($numbers->unique())->toHaveCount(50);
});

it('guards the sequence table with a unique key per series and year', function (): void {
    app(InvoiceNumberGenerator::class)->next(InvoiceSeries::Czech, 2026);

    // A second row for the same series+year must be impossible — without this
    // a concurrent first-issue could hand out CZ-2026-000001 twice.
    expect(fn () => DB::table('invoice_number_sequences')->insert([
        'series'      => InvoiceSeries::Czech->value,
        'year'        => 2026,
        'last_number' => 1,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects a duplicate invoice number at the database level', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    // Clone the real row rather than hand-rolling columns, then try to reuse
    // the number — the UNIQUE index is the last line of defence if the
    // generator is ever bypassed.
    $row         = (array) DB::table('invoices')->where('id', $invoice->id)->first();
    $row['uuid'] = (string) \Illuminate\Support\Str::uuid();
    unset($row['id']);

    expect(fn () => DB::table('invoices')->insert($row))
        ->toThrow(\Illuminate\Database\QueryException::class);

    expect(Invoice::where('number', $invoice->number)->count())->toBe(1);
});

// ── D65: totals and rounding ──────────────────────────────────────────────────

it('keeps subtotal plus tax exactly equal to the invoice total', function (): void {
    $invoice = placeOrder(customerUser())['invoice'];

    $subtotal = $invoice->subtotal->getMinorAmount()->toInt();
    $tax      = $invoice->tax_amount->getMinorAmount()->toInt();
    $total    = $invoice->total->getMinorAmount()->toInt();

    // No stray haléř may appear or vanish between the parts and the total.
    expect($subtotal + $tax)->toBe($total);
});

it('keeps order and invoice totals identical to the haléř', function (): void {
    ['order' => $order, 'invoice' => $invoice] = placeOrder(customerUser());

    expect($invoice->total->getMinorAmount()->toInt())
        ->toBe($order->total->getMinorAmount()->toInt());
});

it('sums the order item lines exactly to the order subtotal', function (): void {
    $order = placeOrder(customerUser())['order'];

    $lineSum = $order->items->sum(fn ($item): int => $item->total->getMinorAmount()->toInt());

    expect($lineSum)->toBe($order->subtotal->getMinorAmount()->toInt());
});
