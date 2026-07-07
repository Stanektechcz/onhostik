<?php

declare(strict_types=1);

use App\Console\Commands\CheckServiceQuotaBreachesCommand;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceResourceService;
use App\Notifications\ServiceQuotaBreachNotification;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── ServiceResourceService::checkAlerts ──────────────────────────────────────

it('checkAlerts returns empty array when no limits are set', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);
    expect($alerts)->toBeEmpty();
});

it('checkAlerts flags disk when usage exceeds threshold', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'        => $user->customer->id,
        'status'             => ServiceStatus::Active,
        'disk_limit_gb'      => 100,
        'disk_usage_gb'      => 85,
        'resource_alert_threshold' => 80,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);

    expect($alerts)->toHaveKey('disk')
        ->and($alerts['disk']['percent_used'])->toBe(85.0);
});

it('checkAlerts does not flag disk when usage is under threshold', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'        => $user->customer->id,
        'disk_limit_gb'      => 100,
        'disk_usage_gb'      => 70,
        'resource_alert_threshold' => 80,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);
    expect($alerts)->not->toHaveKey('disk');
});

// ── Command tests ─────────────────────────────────────────────────────────────

it('command does nothing when no services exceed quota', function (): void {
    Notification::fake();

    $user = customerUser();
    Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
        'disk_limit_gb'  => 100,
        'disk_usage_gb'  => 50,
        'last_resource_check_at' => now(),
        'resource_alert_threshold' => 80,
    ]);

    $this->artisan(CheckServiceQuotaBreachesCommand::class)
         ->expectsOutput('No quota breaches to report.')
         ->assertExitCode(0);

    Notification::assertNothingSent();
});

it('command notifies admins when service exceeds disk quota', function (): void {
    Notification::fake();
    Config::set('provisioning.quota_breach_cooldown_hours', 24);

    $admin = adminUser();
    $user  = customerUser();

    $service = Service::factory()->create([
        'customer_id'      => $user->customer->id,
        'status'           => ServiceStatus::Active,
        'disk_limit_gb'    => 100,
        'disk_usage_gb'    => 95,
        'last_resource_check_at'   => now(),
        'resource_alert_threshold' => 80,
        'quota_breach_alerted_at'  => null,
    ]);

    $this->artisan(CheckServiceQuotaBreachesCommand::class)->assertExitCode(0);

    $service->refresh();
    expect($service->quota_breach_alerted_at)->not->toBeNull();

    Notification::assertSentTo(
        $admin,
        ServiceQuotaBreachNotification::class,
        fn ($n) => $n->service->id === $service->id && isset($n->alerts['disk']),
    );
});

it('command respects cooldown and does not re-alert within window', function (): void {
    Notification::fake();
    Config::set('provisioning.quota_breach_cooldown_hours', 24);

    $admin = adminUser();
    $user  = customerUser();

    Service::factory()->create([
        'customer_id'      => $user->customer->id,
        'status'           => ServiceStatus::Active,
        'disk_limit_gb'    => 100,
        'disk_usage_gb'    => 95,
        'last_resource_check_at'   => now(),
        'resource_alert_threshold' => 80,
        'quota_breach_alerted_at'  => now()->subHours(12),
    ]);

    $this->artisan(CheckServiceQuotaBreachesCommand::class)
         ->expectsOutput('No quota breaches to report.')
         ->assertExitCode(0);

    Notification::assertNothingSent();
});

it('command re-alerts after cooldown period has passed', function (): void {
    Notification::fake();
    Config::set('provisioning.quota_breach_cooldown_hours', 24);

    $admin = adminUser();
    $user  = customerUser();

    $service = Service::factory()->create([
        'customer_id'      => $user->customer->id,
        'status'           => ServiceStatus::Active,
        'disk_limit_gb'    => 100,
        'disk_usage_gb'    => 95,
        'last_resource_check_at'   => now(),
        'resource_alert_threshold' => 80,
        'quota_breach_alerted_at'  => now()->subHours(25),
    ]);

    $this->artisan(CheckServiceQuotaBreachesCommand::class)->assertExitCode(0);

    Notification::assertSentTo($admin, ServiceQuotaBreachNotification::class);
});
