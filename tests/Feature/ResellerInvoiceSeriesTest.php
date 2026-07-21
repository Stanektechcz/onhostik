<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Services\InvoiceNumberGenerator;
use App\Domains\Reseller\Models\ResellerProfile;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

/**
 * Audit 111 — a reseller acting as the invoicing entity numbers its customers'
 * invoices under its own series prefix.
 */

// ── the generator ──────────────────────────────────────────────────────────────

it('numbers an arbitrary series key independently of the global series', function (): void {
    $gen = app(InvoiceNumberGenerator::class);

    $global = $gen->next(InvoiceSeries::Czech, 2026);
    $rs     = $gen->nextForKey('RS1', 2026);
    $rs2    = $gen->nextForKey('RS1', 2026);

    expect($global)->toBe('CZ-2026-000001')
        ->and($rs)->toBe('RS1-2026-000001')     // own counter, starts at 1
        ->and($rs2)->toBe('RS1-2026-000002');   // independent sequence
});

it('keeps the global series continuous regardless of reseller series', function (): void {
    $gen = app(InvoiceNumberGenerator::class);

    $gen->next(InvoiceSeries::Czech, 2026);   // CZ-...-000001
    $gen->nextForKey('RS1', 2026);            // RS1-...-000001
    $second = $gen->next(InvoiceSeries::Czech, 2026);

    expect($second)->toBe('CZ-2026-000002');
});

// ── the reseller prefix accessor ────────────────────────────────────────────────

it('sanitises the reseller prefix to a valid series key', function (): void {
    $reseller = ResellerProfile::factory()->create(['branding' => ['invoice_series' => 'rs-1 x']]);

    expect($reseller->invoiceSeriesPrefix())->toBe('RS1X');
});

it('returns null when the reseller has no series configured', function (): void {
    $reseller = ResellerProfile::factory()->create(['branding' => []]);

    expect($reseller->invoiceSeriesPrefix())->toBeNull();
});

// ── end-to-end through invoice issuance ─────────────────────────────────────────

it('issues a reseller customer’s proforma under the reseller series', function (): void {
    $reseller = ResellerProfile::factory()->create(['branding' => ['invoice_series' => 'RS1']]);
    $user     = customerUser();
    $user->customer->update(['reseller_id' => $reseller->id]);

    ['invoice' => $invoice] = placeOrder($user->fresh());

    expect($invoice->number)->toStartWith('RS1-')
        ->and($invoice->series)->toBe('RS1');
});

it('issues a non-reseller customer’s proforma under the global series (unchanged)', function (): void {
    $user = customerUser();

    ['invoice' => $invoice] = placeOrder($user);

    // Default behaviour is untouched — no reseller, global series.
    expect($invoice->series)->not->toBe('RS1')
        ->and($invoice->number)->toMatch('/^(CZ|EU|INT)-/');
});
