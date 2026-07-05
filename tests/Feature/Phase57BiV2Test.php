<?php

declare(strict_types=1);

use App\Domains\Bi\Services\CohortAnalyser;
use App\Domains\Bi\Services\MrrService;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── MrrService ─────────────────────────────────────────────────────────────────

it('MrrService::summary() returns expected keys', function (): void {
    $summary = app(MrrService::class)->summary();

    expect($summary)->toHaveKeys(['mrr', 'arr', 'active_services', 'churn_rate', 'growth_rate'])
        ->and($summary['mrr'])->toBeFloat()
        ->and($summary['arr'])->toBeFloat()
        ->and($summary['active_services'])->toBeInt()
        ->and($summary['churn_rate'])->toBeFloat()
        ->and($summary['growth_rate'])->toBeFloat();
});

it('MrrService::summary() ARR is MRR × 12', function (): void {
    $summary = app(MrrService::class)->summary();

    expect(round($summary['arr'], 2))->toBe(round($summary['mrr'] * 12, 2));
});

it('MrrService::mrrTrend() returns 6 monthly entries by default', function (): void {
    $trend = app(MrrService::class)->mrrTrend(6);

    expect($trend)->toHaveCount(6);
    foreach ($trend as $label => $value) {
        expect($label)->toMatch('/^\d{4}-\d{2}$/')
            ->and($value)->toBeFloat();
    }
});

it('MrrService::mrrTrend() counts paid invoices in correct month', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $product = \App\Domains\Products\Models\Product::first();

    $invoice = Invoice::factory()->for($customer)->create([
        'status'  => InvoiceStatus::Paid,
        'total'   => Money::ofMinor(50000, 'CZK'),
        'paid_at' => now()->startOfMonth(),
    ]);

    $trend = app(MrrService::class)->mrrTrend(1);
    $thisMonth = now()->format('Y-m');

    expect($trend[$thisMonth])->toBeGreaterThanOrEqual(500.0);
});

it('MrrService::clvBySegment() returns array keyed by segment', function (): void {
    $clv = app(MrrService::class)->clvBySegment();

    expect($clv)->toBeArray();
    foreach ($clv as $segment => $value) {
        expect($segment)->toBeString()
            ->and($value)->toBeFloat();
    }
});

// ── CohortAnalyser ─────────────────────────────────────────────────────────────

it('CohortAnalyser::analyse() returns 6 cohorts × 4 periods', function (): void {
    $data = app(CohortAnalyser::class)->analyse(6, 4);

    expect($data)->toHaveCount(6);
    foreach ($data as $label => $periods) {
        expect($label)->toMatch('/^\d{4}-\d{2}$/')
            ->and($periods)->toHaveCount(4);
    }
});

it('CohortAnalyser::newCustomersPerMonth() returns 6 monthly entries', function (): void {
    $result = app(CohortAnalyser::class)->newCustomersPerMonth(6);

    expect($result)->toHaveCount(6);
    foreach ($result as $label => $count) {
        expect($label)->toMatch('/^\d{4}-\d{2}$/')
            ->and($count)->toBeInt();
    }
});

it('CohortAnalyser::newCustomersPerMonth() counts newly created customers', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    // Touch created_at to current month
    $customer->update(['created_at' => now()->startOfMonth()->addDays(1)]);

    $result   = app(CohortAnalyser::class)->newCustomersPerMonth(1);
    $thisMonth = now()->format('Y-m');

    expect($result[$thisMonth])->toBeGreaterThanOrEqual(1);
});

// ── BiV2Controller / admin route ───────────────────────────────────────────────

it('admin.bi-v2.index page is accessible to admin users', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.bi-v2.index'))
        ->assertOk()
        ->assertSee('BI 2.0');
});

it('admin.bi-v2.index is not accessible to guests', function (): void {
    $this->get(route('admin.bi-v2.index'))
        ->assertRedirect();
});

it('admin.bi-v2.index is not accessible to regular customers', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.bi-v2.index'))
        ->assertForbidden();
});

it('admin.bi-v2.index renders MRR card', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.bi-v2.index'))
        ->assertOk()
        ->assertSee('MRR')
        ->assertSee('ARR')
        ->assertSee('Churn rate');
});

it('admin.bi-v2.index renders cohort analysis section', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.bi-v2.index'))
        ->assertOk()
        ->assertSee('Kohortní analýza');
});

it('admin.bi-v2.index renders CLV by segment table', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.bi-v2.index'))
        ->assertOk()
        ->assertSee('Customer Lifetime Value');
});

it('admin.bi-v2.index renders revenue trend table', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.bi-v2.index'))
        ->assertOk()
        ->assertSee('Příjmy posledních 6 měsíců');
});

it('admin.bi-v2.index renders forecast row', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.bi-v2.index'))
        ->assertOk()
        ->assertSee('Forecast');
});
