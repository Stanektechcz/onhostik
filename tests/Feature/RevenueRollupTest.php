<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Reporting\Models\RevenueDaily;
use Brick\Money\Money;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Materialized daily-revenue rollup (audit 500 #11).
 */

it('rolls up paid invoices per day and currency', function (): void {
    $date     = now()->subDays(2)->startOfDay();
    $invoices = collect();

    for ($i = 0; $i < 3; $i++) {
        $invoices->push(Invoice::factory()->create([
            'status'   => InvoiceStatus::Paid,
            'paid_at'  => $date->copy()->addHours($i),
            'total'    => Money::of(1000, 'CZK'),
            'subtotal' => Money::of(826, 'CZK'),
        ]));
    }

    // Unpaid invoice must be excluded from revenue.
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'paid_at' => null]);

    $this->artisan('reporting:rollup-revenue')->assertExitCode(0);

    $row = RevenueDaily::where('date', $date->toDateString())->where('currency', 'CZK')->first();

    expect($row)->not->toBeNull()
        ->and($row->invoices_count)->toBe(3)
        ->and($row->gross_minor)->toBe($invoices->sum(fn (Invoice $i): int => $i->total->getMinorAmount()->toInt()))
        ->and($row->net_minor)->toBe($invoices->sum(fn (Invoice $i): int => $i->subtotal->getMinorAmount()->toInt()));
});

it('is idempotent — re-running does not duplicate rows', function (): void {
    $date = now()->subDay()->startOfDay();
    Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'paid_at' => $date, 'total' => Money::of(500, 'CZK'), 'subtotal' => Money::of(413, 'CZK')]);

    $this->artisan('reporting:rollup-revenue');
    $this->artisan('reporting:rollup-revenue');

    expect(RevenueDaily::where('date', $date->toDateString())->where('currency', 'CZK')->count())->toBe(1);
});

it('excludes invoices outside the trailing window', function (): void {
    Invoice::factory()->create([
        'status'  => InvoiceStatus::Paid,
        'paid_at' => now()->subDays(400),
        'total'   => Money::of(9999, 'CZK'),
    ]);

    $this->artisan('reporting:rollup-revenue'); // default 35-day window

    expect((int) RevenueDaily::sum('gross_minor'))->toBe(0);
});
