<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Models\KpiAlert;
use App\Notifications\KpiAlertTriggeredNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model ─────────────────────────────────────────────────────────────────────

it('KpiAlert breaches when gte and value is at threshold', function (): void {
    $alert = new KpiAlert(['operator' => 'gte', 'threshold' => 5.0]);

    expect($alert->breaches(5.0))->toBeTrue()
        ->and($alert->breaches(6.0))->toBeTrue()
        ->and($alert->breaches(4.9))->toBeFalse();
});

it('KpiAlert breaches when lte and value is at threshold', function (): void {
    $alert = new KpiAlert(['operator' => 'lte', 'threshold' => 100.0]);

    expect($alert->breaches(100.0))->toBeTrue()
        ->and($alert->breaches(50.0))->toBeTrue()
        ->and($alert->breaches(101.0))->toBeFalse();
});

it('KpiAlert metricLabel returns Czech string', function (): void {
    $alert = new KpiAlert(['metric' => 'overdue_invoices_count']);
    expect($alert->metricLabel())->toBe('Počet po splatnosti');

    $alert->metric = 'failed_backups_24h';
    expect($alert->metricLabel())->toBe('Neúspěšné zálohy (24 h)');
});

it('KpiAlert isTriggered returns true only when triggered_at is set', function (): void {
    $alert = new KpiAlert(['triggered_at' => null]);
    expect($alert->isTriggered())->toBeFalse();

    $alert->triggered_at = now();
    expect($alert->isTriggered())->toBeTrue();
});

// ── Command: triggers alert on breach ────────────────────────────────────────

it('command triggers alert and notifies admin when threshold is breached', function (): void {
    Notification::fake();

    $admin = adminUser();

    // Create an overdue invoice so the metric returns >= 1
    $user = customerUser();
    $result = placeOrder($user);
    $result['invoice']->update(['status' => InvoiceStatus::Overdue]);

    $alert = KpiAlert::create([
        'metric'    => 'overdue_invoices_count',
        'operator'  => 'gte',
        'threshold' => 1.0,
        'is_active' => true,
    ]);

    $this->artisan('admin:check-kpi-alerts')->assertExitCode(0);

    $alert->refresh();

    expect($alert->triggered_at)->not->toBeNull()
        ->and($alert->last_value)->toBeGreaterThanOrEqual(1.0);

    Notification::assertSentTo($admin, KpiAlertTriggeredNotification::class);
});

it('command does not trigger when metric is below threshold', function (): void {
    Notification::fake();

    adminUser();

    // No overdue invoices exist
    KpiAlert::create([
        'metric'    => 'overdue_invoices_count',
        'operator'  => 'gte',
        'threshold' => 99.0, // very high
        'is_active' => true,
    ]);

    $this->artisan('admin:check-kpi-alerts')->assertExitCode(0);

    Notification::assertNothingSent();
});

it('command resolves alert when metric drops below threshold', function (): void {
    Notification::fake();

    adminUser();

    // Start with alert already triggered
    $alert = KpiAlert::create([
        'metric'      => 'overdue_invoices_count',
        'operator'    => 'gte',
        'threshold'   => 99.0, // no overdue invoices → not breaching
        'is_active'   => true,
        'triggered_at'=> now(), // previously triggered
    ]);

    $this->artisan('admin:check-kpi-alerts')->assertExitCode(0);

    $alert->refresh();
    expect($alert->triggered_at)->toBeNull();
});

it('command does not send notification when alert is already triggered', function (): void {
    $admin = adminUser();
    $user  = customerUser();
    placeOrder($user)['invoice']->update(['status' => InvoiceStatus::Overdue]);

    KpiAlert::create([
        'metric'      => 'overdue_invoices_count',
        'operator'    => 'gte',
        'threshold'   => 1.0,
        'is_active'   => true,
        'triggered_at'=> now(), // already triggered — no duplicate notification
    ]);

    Notification::fake(); // fake AFTER placeOrder to exclude InvoiceIssuedNotification

    $this->artisan('admin:check-kpi-alerts')->assertExitCode(0);

    Notification::assertNotSentTo($admin, KpiAlertTriggeredNotification::class);
});

it('command skips inactive alerts', function (): void {
    $admin = adminUser();
    $user  = customerUser();
    placeOrder($user)['invoice']->update(['status' => InvoiceStatus::Overdue]);

    KpiAlert::create([
        'metric'    => 'overdue_invoices_count',
        'operator'  => 'gte',
        'threshold' => 1.0,
        'is_active' => false, // inactive
    ]);

    Notification::fake(); // fake AFTER placeOrder to exclude InvoiceIssuedNotification

    $this->artisan('admin:check-kpi-alerts')->assertExitCode(0);

    Notification::assertNotSentTo($admin, KpiAlertTriggeredNotification::class);
});

// ── Admin CRUD routes ─────────────────────────────────────────────────────────

it('admin can view kpi alerts index', function (): void {
    $admin = adminUser();

    KpiAlert::create([
        'metric'    => 'suspended_services_count',
        'operator'  => 'gte',
        'threshold' => 5.0,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.kpi-alerts.index'))
        ->assertOk()
        ->assertSee('KPI')
        ->assertSee('Pozastavené služby');
});

it('admin can create a kpi alert', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.kpi-alerts.store'), [
            'metric'    => 'open_tickets_count',
            'operator'  => 'gte',
            'threshold' => '20',
        ])
        ->assertRedirect();

    expect(KpiAlert::where('metric', 'open_tickets_count')->exists())->toBeTrue();
});

it('admin can delete a kpi alert', function (): void {
    $admin = adminUser();

    $alert = KpiAlert::create([
        'metric'    => 'failed_backups_24h',
        'operator'  => 'gte',
        'threshold' => 3.0,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.kpi-alerts.destroy', $alert))
        ->assertRedirect();

    expect(KpiAlert::find($alert->id))->toBeNull();
});
