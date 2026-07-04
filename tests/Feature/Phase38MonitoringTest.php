<?php

declare(strict_types=1);

use App\Console\Commands\EvaluateMonitorAlertsCommand;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorAlert;
use App\Domains\Monitoring\Models\MonitorCheck;
use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Notifications\MonitorThresholdAlertNotification;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function p38MakeMonitor(array $overrides = []): Monitor
{
    return Monitor::create(array_merge([
        'name'      => 'Test monitor ' . uniqid(),
        'type'      => 'http',
        'target'    => 'https://example.com',
        'provider'  => 'internal_mock',
        'status'    => MonitorStatus::Up,
        'is_active' => true,
    ], $overrides));
}

function p38AddCheck(Monitor $monitor, int $responseMs, string $status = 'up'): MonitorCheck
{
    return MonitorCheck::create([
        'monitor_id'  => $monitor->id,
        'status'      => $status,
        'response_ms' => $responseMs,
        'checked_at'  => now(),
    ]);
}

// ── MonitorAlert model ────────────────────────────────────────────────────────

it('MonitorAlert::isOpen returns true when resolved_at is null', function (): void {
    $monitor = p38MakeMonitor();
    $alert = MonitorAlert::create([
        'monitor_id'      => $monitor->id,
        'type'            => 'uptime',
        'threshold_value' => 99.0,
        'current_value'   => 95.0,
        'triggered_at'    => now(),
    ]);

    expect($alert->isOpen())->toBeTrue();
});

it('MonitorAlert::isOpen returns false when resolved_at is set', function (): void {
    $monitor = p38MakeMonitor();
    $alert = MonitorAlert::create([
        'monitor_id'      => $monitor->id,
        'type'            => 'uptime',
        'threshold_value' => 99.0,
        'current_value'   => 95.0,
        'triggered_at'    => now(),
        'resolved_at'     => now(),
    ]);

    expect($alert->isOpen())->toBeFalse();
});

it('Monitor::hasOpenAlert returns false when no open alert of that type', function (): void {
    $monitor = p38MakeMonitor();
    expect($monitor->hasOpenAlert('uptime'))->toBeFalse();
});

it('Monitor::hasOpenAlert returns true when open alert exists', function (): void {
    $monitor = p38MakeMonitor();
    MonitorAlert::create([
        'monitor_id'      => $monitor->id,
        'type'            => 'uptime',
        'threshold_value' => 99.0,
        'current_value'   => 95.0,
        'triggered_at'    => now(),
    ]);

    expect($monitor->hasOpenAlert('uptime'))->toBeTrue();
    expect($monitor->hasOpenAlert('ssl_expiry'))->toBeFalse();
});

// ── EvaluateMonitorAlertsCommand — response_time threshold ───────────────────

it('triggers response_time alert when check exceeds threshold', function (): void {
    Notification::fake();

    $admin   = adminUser();
    $monitor = p38MakeMonitor(['response_time_threshold_ms' => 500]);
    p38AddCheck($monitor, 1500);

    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    $alert = MonitorAlert::where('monitor_id', $monitor->id)->where('type', 'response_time')->first();
    expect($alert)->not->toBeNull();
    expect($alert->isOpen())->toBeTrue();
    expect((int) $alert->current_value)->toBe(1500);

    Notification::assertSentTo($admin, MonitorThresholdAlertNotification::class);
});

it('does not trigger response_time alert when check is within threshold', function (): void {
    Notification::fake();

    $monitor = p38MakeMonitor(['response_time_threshold_ms' => 500]);
    p38AddCheck($monitor, 200);

    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    expect(MonitorAlert::where('monitor_id', $monitor->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('does not open duplicate response_time alert if one already exists', function (): void {
    Notification::fake();

    $monitor = p38MakeMonitor(['response_time_threshold_ms' => 500]);
    p38AddCheck($monitor, 1500);

    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);
    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    expect(MonitorAlert::where('monitor_id', $monitor->id)->where('type', 'response_time')->count())->toBe(1);
    Notification::assertSentToTimes($admin = adminUser(), MonitorThresholdAlertNotification::class, 0);
});

it('resolves response_time alert when check is back within threshold', function (): void {
    $monitor = p38MakeMonitor(['response_time_threshold_ms' => 500]);
    p38AddCheck($monitor, 1500);

    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    $alert = MonitorAlert::where('monitor_id', $monitor->id)->where('type', 'response_time')->firstOrFail();
    expect($alert->isOpen())->toBeTrue();

    p38AddCheck($monitor, 200);
    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    expect($alert->fresh()->isOpen())->toBeFalse();
});

// ── EvaluateMonitorAlertsCommand — uptime threshold ──────────────────────────

it('triggers uptime alert when uptime drops below threshold', function (): void {
    Notification::fake();

    $admin   = adminUser();
    $monitor = p38MakeMonitor(['uptime_threshold_percent' => 99.0, 'uptime_percent' => 95.0]);

    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    $alert = MonitorAlert::where('monitor_id', $monitor->id)->where('type', 'uptime')->first();
    expect($alert)->not->toBeNull()->and($alert->isOpen())->toBeTrue();

    Notification::assertSentTo($admin, MonitorThresholdAlertNotification::class);
});

it('resolves uptime alert when uptime recovers', function (): void {
    $monitor = p38MakeMonitor(['uptime_threshold_percent' => 99.0, 'uptime_percent' => 95.0]);

    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    $alert = MonitorAlert::where('monitor_id', $monitor->id)->where('type', 'uptime')->firstOrFail();
    expect($alert->isOpen())->toBeTrue();

    $monitor->update(['uptime_percent' => 99.5]);
    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    expect($alert->fresh()->isOpen())->toBeFalse();
});

// ── EvaluateMonitorAlertsCommand — ssl_expiry threshold ──────────────────────

it('triggers ssl_expiry alert when SSL expires within warn window', function (): void {
    Notification::fake();

    $admin   = adminUser();
    $monitor = p38MakeMonitor([
        'ssl_warn_days'  => 30,
        'ssl_expires_at' => now()->addDays(10)->toDateString(),
    ]);

    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    $alert = MonitorAlert::where('monitor_id', $monitor->id)->where('type', 'ssl_expiry')->first();
    expect($alert)->not->toBeNull()->and($alert->isOpen())->toBeTrue();

    Notification::assertSentTo($admin, MonitorThresholdAlertNotification::class);
});

it('does not trigger ssl_expiry alert when SSL is outside warn window', function (): void {
    Notification::fake();

    $monitor = p38MakeMonitor([
        'ssl_warn_days'  => 30,
        'ssl_expires_at' => now()->addDays(90)->toDateString(),
    ]);

    $this->artisan(EvaluateMonitorAlertsCommand::class)->assertExitCode(0);

    expect(MonitorAlert::where('monitor_id', $monitor->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

// ── Admin threshold update endpoint ──────────────────────────────────────────

it('admin monitoring index returns 200 with new openAlertCount variable', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.monitoring.index'))
        ->assertOk()
        ->assertViewHas('openAlertCount')
        ->assertViewHas('openAlerts');
});

it('admin can update monitor thresholds via PUT', function (): void {
    $admin   = adminUser();
    $monitor = p38MakeMonitor();

    $this->actingAs($admin)
        ->put(route('admin.monitoring.thresholds', $monitor), [
            'response_time_threshold_ms' => 800,
            'uptime_threshold_percent'   => 99.5,
            'ssl_warn_days'              => 14,
        ])
        ->assertRedirect();

    $monitor->refresh();
    expect($monitor->response_time_threshold_ms)->toBe(800)
        ->and((float) $monitor->uptime_threshold_percent)->toBe(99.5)
        ->and($monitor->ssl_warn_days)->toBe(14);
});

it('threshold update validates ssl_warn_days range', function (): void {
    $admin   = adminUser();
    $monitor = p38MakeMonitor();

    $this->actingAs($admin)
        ->put(route('admin.monitoring.thresholds', $monitor), [
            'ssl_warn_days' => 0,
        ])
        ->assertSessionHasErrors('ssl_warn_days');
});

it('customer cannot update monitor thresholds', function (): void {
    $user    = customerUser();
    $monitor = p38MakeMonitor();

    $this->actingAs($user)
        ->put(route('admin.monitoring.thresholds', $monitor), [
            'ssl_warn_days' => 14,
        ])
        ->assertForbidden();
});
