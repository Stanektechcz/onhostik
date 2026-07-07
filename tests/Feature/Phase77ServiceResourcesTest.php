<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceResourceUsage;
use App\Domains\Provisioning\Services\ServiceResourceService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── ServiceResourceService ────────────────────────────────────────────────────

it('recordUsage creates a usage snapshot', function (): void {
    $service = Service::factory()->create();

    app(ServiceResourceService::class)->recordUsage($service, [
        'cpu_percent' => 45,
        'ram_mb'      => 1024,
        'disk_gb'     => 10,
    ]);

    expect(ServiceResourceUsage::where('service_id', $service->id)->count())->toBe(1);
});

it('recordUsage updates live usage columns on service', function (): void {
    $service = Service::factory()->create();

    app(ServiceResourceService::class)->recordUsage($service, [
        'cpu_percent'  => 70,
        'ram_mb'       => 2048,
        'disk_gb'      => 50,
        'bandwidth_gb' => 100,
    ]);

    $fresh = $service->fresh();
    expect($fresh->cpu_usage_percent)->toBe(70)
        ->and($fresh->ram_usage_mb)->toBe(2048)
        ->and($fresh->disk_usage_gb)->toBe(50)
        ->and($fresh->bandwidth_usage_gb)->toBe(100)
        ->and($fresh->last_resource_check_at)->not->toBeNull();
});

it('checkAlerts returns empty when no limits set', function (): void {
    $service = Service::factory()->create([
        'cpu_usage_percent' => 95,
        'cpu_limit_percent' => null,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);
    expect($alerts)->toBeEmpty();
});

it('checkAlerts returns alert when cpu usage exceeds threshold', function (): void {
    $service = Service::factory()->create([
        'cpu_limit_percent'        => 100,
        'cpu_usage_percent'        => 85,
        'resource_alert_threshold' => 80,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);
    expect($alerts)->toHaveKey('cpu')
        ->and($alerts['cpu']['usage'])->toBe(85)
        ->and($alerts['cpu']['limit'])->toBe(100)
        ->and($alerts['cpu']['percent_used'])->toBe(85.0);
});

it('checkAlerts returns no alert when usage below threshold', function (): void {
    $service = Service::factory()->create([
        'cpu_limit_percent'        => 100,
        'cpu_usage_percent'        => 50,
        'resource_alert_threshold' => 80,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);
    expect($alerts)->not->toHaveKey('cpu');
});

it('checkAlerts detects ram alert', function (): void {
    $service = Service::factory()->create([
        'ram_limit_mb'             => 4096,
        'ram_usage_mb'             => 3500,
        'resource_alert_threshold' => 80,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);
    expect($alerts)->toHaveKey('ram');
});

it('checkAlerts detects disk alert', function (): void {
    $service = Service::factory()->create([
        'disk_limit_gb'            => 100,
        'disk_usage_gb'            => 90,
        'resource_alert_threshold' => 80,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);
    expect($alerts)->toHaveKey('disk');
});

it('checkAlerts detects bandwidth alert', function (): void {
    $service = Service::factory()->create([
        'bandwidth_limit_gb'       => 1000,
        'bandwidth_usage_gb'       => 900,
        'resource_alert_threshold' => 80,
    ]);

    $alerts = app(ServiceResourceService::class)->checkAlerts($service);
    expect($alerts)->toHaveKey('bandwidth');
});

it('overThresholdServices returns services with alerts', function (): void {
    $alertService = Service::factory()->create([
        'cpu_limit_percent'        => 100,
        'cpu_usage_percent'        => 90,
        'resource_alert_threshold' => 80,
        'last_resource_check_at'   => now(),
    ]);
    $normalService = Service::factory()->create([
        'cpu_limit_percent'        => 100,
        'cpu_usage_percent'        => 30,
        'resource_alert_threshold' => 80,
        'last_resource_check_at'   => now(),
    ]);

    $results = app(ServiceResourceService::class)->overThresholdServices();
    $ids     = $results->pluck('id')->toArray();

    expect($ids)->toContain($alertService->id)
        ->and($ids)->not->toContain($normalService->id);
});

it('stats returns correct structure', function (): void {
    $stats = app(ServiceResourceService::class)->stats();

    expect($stats)->toHaveKey('total_monitored')
        ->and($stats)->toHaveKey('over_threshold')
        ->and($stats)->toHaveKey('last_checked');
});

it('stats total_monitored counts services with last_resource_check_at', function (): void {
    Service::factory()->create(['last_resource_check_at' => now()]);
    Service::factory()->create(['last_resource_check_at' => now()]);
    Service::factory()->create(['last_resource_check_at' => null]);

    $stats = app(ServiceResourceService::class)->stats();
    expect($stats['total_monitored'])->toBeGreaterThanOrEqual(2);
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view service resources page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.service-resources.index'))
        ->assertOk()
        ->assertSee('Limity zdrojů');
});

it('admin can view alerts filter', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.service-resources.index', ['filter' => 'alerts']))
        ->assertOk();
});

it('admin can update resource limits', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.service-resources.update-limits', $service), [
            'cpu_limit_percent'        => 80,
            'ram_limit_mb'             => 2048,
            'disk_limit_gb'            => 50,
            'bandwidth_limit_gb'       => 500,
            'resource_alert_threshold' => 90,
        ])
        ->assertRedirect();

    $fresh = $service->fresh();
    expect($fresh->cpu_limit_percent)->toBe(80)
        ->and($fresh->ram_limit_mb)->toBe(2048)
        ->and($fresh->disk_limit_gb)->toBe(50)
        ->and($fresh->bandwidth_limit_gb)->toBe(500)
        ->and($fresh->resource_alert_threshold)->toBe(90);
});

it('admin can record usage via API', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.service-resources.record-usage', $service), [
            'cpu_percent' => 60,
            'ram_mb'      => 1500,
            'disk_gb'     => 20,
        ])
        ->assertRedirect();

    expect(ServiceResourceUsage::where('service_id', $service->id)->count())->toBe(1);
    expect($service->fresh()->cpu_usage_percent)->toBe(60);
});

it('non-admin cannot access service resources', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.service-resources.index'))
        ->assertStatus(403);
});
