<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;
use App\Domains\Reporting\ReportBuilder;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── ReportBuilder service ─────────────────────────────────────────────────────

it('TYPES constant has expected keys', function (): void {
    expect(ReportBuilder::TYPES)->toHaveKey('revenue')
        ->and(ReportBuilder::TYPES)->toHaveKey('churn')
        ->and(ReportBuilder::TYPES)->toHaveKey('cohort_revenue')
        ->and(ReportBuilder::TYPES)->toHaveKey('new_customers')
        ->and(ReportBuilder::TYPES)->toHaveKey('services')
        ->and(ReportBuilder::TYPES)->toHaveKey('invoices');
});

it('build revenue report returns correct structure', function (): void {
    $report = app(ReportBuilder::class)->build([
        'type'     => 'revenue',
        'from'     => now()->startOfMonth()->format('Y-m-d'),
        'to'       => now()->format('Y-m-d'),
        'group_by' => 'month',
    ]);

    expect($report)->toHaveKey('title')
        ->and($report)->toHaveKey('headers')
        ->and($report)->toHaveKey('rows')
        ->and($report['title'])->toBe('Přehled tržeb');
});

it('build churn report returns correct structure', function (): void {
    $report = app(ReportBuilder::class)->build([
        'type' => 'churn',
        'from' => now()->startOfMonth()->format('Y-m-d'),
        'to'   => now()->format('Y-m-d'),
    ]);

    expect($report['title'])->toBe('Odchod zákazníků')
        ->and($report['headers'])->toContain('Zákazník')
        ->and($report['headers'])->toContain('Ukončeno');
});

it('build cohort_revenue report returns correct structure', function (): void {
    customerUser(); // create a customer in the date range

    $report = app(ReportBuilder::class)->build([
        'type' => 'cohort_revenue',
        'from' => now()->subYear()->format('Y-m-d'),
        'to'   => now()->format('Y-m-d'),
    ]);

    expect($report['title'])->toBe('Kohortní analýza tržeb')
        ->and($report['headers'])->toContain('Kohorta (měsíc)');
});

it('build new_customers report returns correct structure', function (): void {
    customerUser();

    $report = app(ReportBuilder::class)->build([
        'type'     => 'new_customers',
        'from'     => now()->subYear()->format('Y-m-d'),
        'to'       => now()->format('Y-m-d'),
        'group_by' => 'month',
    ]);

    expect($report['title'])->toBe('Noví zákazníci')
        ->and($report['headers'])->toContain('Zákazníků')
        ->and($report['rows'])->not->toBeEmpty();
});

it('build services report returns correct structure', function (): void {
    Service::factory()->create();

    $report = app(ReportBuilder::class)->build([
        'type'   => 'services',
        'from'   => now()->subYear()->format('Y-m-d'),
        'to'     => now()->format('Y-m-d'),
        'status' => '',
    ]);

    expect($report['title'])->toBe('Přehled služeb')
        ->and($report['headers'])->toContain('Status');
});

it('build invoices report returns correct structure', function (): void {
    $report = app(ReportBuilder::class)->build([
        'type'   => 'invoices',
        'from'   => now()->startOfMonth()->format('Y-m-d'),
        'to'     => now()->format('Y-m-d'),
        'status' => '',
    ]);

    expect($report['title'])->toBe('Přehled faktur')
        ->and($report['headers'])->toContain('Číslo');
});

it('churn report includes terminated services', function (): void {
    $service = Service::factory()->create([
        'terminated_at'       => now()->subDay(),
        'cancellation_reason' => 'Too expensive',
    ]);

    $report = app(ReportBuilder::class)->build([
        'type' => 'churn',
        'from' => now()->subWeek()->format('Y-m-d'),
        'to'   => now()->format('Y-m-d'),
    ]);

    $serviceIds = collect($report['rows'])->pluck('Služba')->toArray();
    expect(in_array($service->label ?? "Service #{$service->id}", $serviceIds))->toBeTrue();
});

it('toCsv generates valid CSV with headers', function (): void {
    $report = app(ReportBuilder::class)->build([
        'type'     => 'new_customers',
        'from'     => now()->subYear()->format('Y-m-d'),
        'to'       => now()->format('Y-m-d'),
        'group_by' => 'month',
    ]);

    $csv = app(ReportBuilder::class)->toCsv($report);

    expect($csv)->toBeString()
        ->and($csv)->toContain('"Období"')
        ->and($csv)->toContain('"Zákazníků"');
});

it('toCsv handles special characters correctly', function (): void {
    $report = [
        'title'   => 'Test',
        'headers' => ['Name'],
        'rows'    => collect([['Name' => 'Company "Test" s.r.o.']]),
    ];

    $csv = app(ReportBuilder::class)->toCsv($report);
    expect($csv)->toContain('""Test""'); // escaped quotes
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view report builder page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Report Builder');
});

it('admin can build a revenue report', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'type'     => 'revenue',
            'from'     => now()->startOfMonth()->format('Y-m-d'),
            'to'       => now()->format('Y-m-d'),
            'group_by' => 'month',
        ]))
        ->assertOk()
        ->assertSee('Přehled tržeb');
});

it('admin can build a new customers report', function (): void {
    $admin = adminUser();
    customerUser();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'type'     => 'new_customers',
            'from'     => now()->subYear()->format('Y-m-d'),
            'to'       => now()->format('Y-m-d'),
            'group_by' => 'month',
        ]))
        ->assertOk()
        ->assertSee('Noví zákazníci');
});

it('admin can download a CSV report', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.download', [
            'type'     => 'revenue',
            'from'     => now()->startOfMonth()->format('Y-m-d'),
            'to'       => now()->format('Y-m-d'),
            'group_by' => 'month',
        ]))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('report_revenue_');
});

it('download validates required fields', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.reports.download', ['type' => 'revenue']))
        ->assertSessionHasErrors(['from', 'to']);
});

it('download rejects invalid report type', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.reports.download', [
            'type' => 'invalid_type',
            'from' => now()->startOfMonth()->format('Y-m-d'),
            'to'   => now()->format('Y-m-d'),
        ]))
        ->assertSessionHasErrors('type');
});

it('non-admin cannot access report builder', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.reports.index'))
        ->assertStatus(403);
});
