<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\ExchangeRateService;
use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Aging report page ─────────────────────────────────────────────────────────

it('admin can view aging report page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.financial-report.aging'))
        ->assertOk()
        ->assertViewIs('admin.financial-report.aging')
        ->assertViewHas('buckets')
        ->assertViewHas('totals');
});

it('customer cannot access aging report', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.financial-report.aging'))
        ->assertForbidden();
});

it('aging report page shows overdue invoices in correct buckets', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    // 1-30 days overdue
    Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => InvoiceStatus::Overdue,
        'due_date'    => now()->subDays(10)->toDateString(),
        'total'       => Money::of(500, 'CZK'),
        'subtotal'    => Money::of(500, 'CZK'),
        'tax_amount'  => Money::of(0, 'CZK'),
        'currency'    => 'CZK',
    ]);

    // 90+ days overdue
    Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => InvoiceStatus::Overdue,
        'due_date'    => now()->subDays(100)->toDateString(),
        'total'       => Money::of(999, 'CZK'),
        'subtotal'    => Money::of(999, 'CZK'),
        'tax_amount'  => Money::of(0, 'CZK'),
        'currency'    => 'CZK',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.financial-report.aging'))
        ->assertOk();

    $buckets = $response->viewData('buckets');
    $totals  = $response->viewData('totals');

    expect(count($buckets['d1_30']))->toBe(1);
    expect(count($buckets['d90plus']))->toBe(1);
    expect($totals['d1_30'])->toBe(50000);   // 500 CZK = 50000 minor
    expect($totals['d90plus'])->toBe(99900); // 999 CZK = 99900 minor
});

it('aging report current bucket contains non-overdue sent invoices', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    // Not yet overdue — due in future
    Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => InvoiceStatus::Sent,
        'due_date'    => now()->addDays(5)->toDateString(),
        'total'       => Money::of(200, 'CZK'),
        'subtotal'    => Money::of(200, 'CZK'),
        'tax_amount'  => Money::of(0, 'CZK'),
        'currency'    => 'CZK',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.financial-report.aging'))
        ->assertOk();

    $buckets = $response->viewData('buckets');
    expect(count($buckets['current']))->toBe(1);
});

// ── Revenue CSV export ────────────────────────────────────────────────────────

it('admin can download revenue CSV export', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => InvoiceStatus::Paid,
        'paid_at'     => now(),
        'purpose'     => 'order',
        'currency'    => 'CZK',
        'total'       => Money::of(999, 'CZK'),
        'subtotal'    => Money::of(999, 'CZK'),
        'tax_amount'  => Money::of(0, 'CZK'),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.financial-report.revenue-csv', [
            'from' => now()->startOfYear()->toDateString(),
            'to'   => now()->toDateString(),
        ]))
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('rok');
    expect($content)->toContain('CZK');
});

it('revenue CSV requires from and to date parameters', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.financial-report.revenue-csv'))
        ->assertSessionHasErrors(['from', 'to']);
});

it('revenue CSV validates that to is after from', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.financial-report.revenue-csv', [
            'from' => '2026-12-31',
            'to'   => '2026-01-01',
        ]))
        ->assertSessionHasErrors('to');
});

it('revenue CSV includes CZK equivalent when exchange rate exists', function (): void {
    $admin    = adminUser();
    $customer = customerUser(['preferred_currency' => 'EUR']);

    app(ExchangeRateService::class)->setRate(Currency::EUR, 25.0);

    Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => InvoiceStatus::Paid,
        'paid_at'     => now(),
        'purpose'     => 'order',
        'currency'    => 'EUR',
        'total'       => Money::of(10, 'EUR'),
        'subtotal'    => Money::of(10, 'EUR'),
        'tax_amount'  => Money::of(0, 'EUR'),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.financial-report.revenue-csv', [
            'from' => now()->startOfYear()->toDateString(),
            'to'   => now()->toDateString(),
        ]))
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('EUR');
    // 10 EUR = 1000 minor EUR * 25.0 = 25000 minor CZK
    expect($content)->toContain('25000');
});

it('customer cannot download revenue CSV', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.financial-report.revenue-csv', [
            'from' => now()->startOfYear()->toDateString(),
            'to'   => now()->toDateString(),
        ]))
        ->assertForbidden();
});
