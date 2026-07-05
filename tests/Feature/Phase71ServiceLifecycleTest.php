<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use Brick\Money\Money;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceLifecycleService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
    Bus::fake();
});

function makeActiveService(): Service
{
    return Service::factory()->create(['status' => ServiceStatus::Active]);
}

function makeSuspendedService(): Service
{
    return Service::factory()->create(['status' => ServiceStatus::Suspended]);
}

// ── ServiceStatus enum ────────────────────────────────────────────────────────

it('ServiceStatus badgeClass returns expected CSS classes', function (): void {
    expect(ServiceStatus::Active->badgeClass())->toContain('success')
        ->and(ServiceStatus::Suspended->badgeClass())->toContain('danger')
        ->and(ServiceStatus::Terminated->badgeClass())->toContain('secondary')
        ->and(ServiceStatus::Pending->badgeClass())->toContain('warning');
});

// ── ServiceLifecycleService ───────────────────────────────────────────────────

it('suspend dispatches ChangeServiceStateJob', function (): void {
    $service = makeActiveService();

    app(ServiceLifecycleService::class)->suspend($service, 'manual_test');

    Bus::assertDispatched(ChangeServiceStateJob::class, fn ($job) =>
        $job->serviceId === $service->id &&
        $job->operation === 'suspend' &&
        $job->reason    === 'manual_test'
    );
});

it('suspend is no-op if already suspended', function (): void {
    $service = makeSuspendedService();

    app(ServiceLifecycleService::class)->suspend($service, 'test');

    Bus::assertNothingDispatched();
});

it('unsuspend dispatches ChangeServiceStateJob', function (): void {
    $service = makeSuspendedService();

    app(ServiceLifecycleService::class)->unsuspend($service, 'manual_admin');

    Bus::assertDispatched(ChangeServiceStateJob::class, fn ($job) =>
        $job->operation === 'unsuspend'
    );
});

it('unsuspend is no-op if not suspended', function (): void {
    $service = makeActiveService();

    app(ServiceLifecycleService::class)->unsuspend($service, 'test');

    Bus::assertNothingDispatched();
});

it('terminate dispatches ChangeServiceStateJob', function (): void {
    $service = makeSuspendedService();

    app(ServiceLifecycleService::class)->terminate($service, 'manual_admin');

    Bus::assertDispatched(ChangeServiceStateJob::class, fn ($job) =>
        $job->operation === 'terminate'
    );
});

it('terminate is no-op if already terminated', function (): void {
    $service = Service::factory()->create(['status' => ServiceStatus::Terminated]);

    app(ServiceLifecycleService::class)->terminate($service, 'test');

    Bus::assertNothingDispatched();
});

it('lifecycleStats returns correct counts', function (): void {
    Service::factory()->create(['status' => ServiceStatus::Suspended]);
    Service::factory()->create([
        'status'        => ServiceStatus::Active,
        'next_due_date' => now()->addDays(7),
    ]);

    $stats = app(ServiceLifecycleService::class)->lifecycleStats();

    expect($stats['suspended'])->toBeGreaterThanOrEqual(1)
        ->and($stats['expiring_soon'])->toBeGreaterThanOrEqual(1);
});

// ── billing:suspend-overdue command ──────────────────────────────────────────

it('billing:suspend-overdue completes without error', function (): void {
    $this->artisan('billing:suspend-overdue')->assertSuccessful();
});

// ── billing:send-renewal-reminders command ───────────────────────────────────

it('billing:send-renewal-reminders runs without error', function (): void {
    $this->artisan('billing:send-renewal-reminders')->assertSuccessful();
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view lifecycle index with suspended tab', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.lifecycle.index'))
        ->assertOk()
        ->assertSee('Životní cyklus');
});

it('admin can view expiring tab', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.lifecycle.index', ['tab' => 'expiring']))
        ->assertOk();
});

it('admin can suspend a service via route', function (): void {
    $admin   = adminUser();
    $service = makeActiveService();

    $this->actingAs($admin)
        ->post(route('admin.lifecycle.suspend', $service), ['reason' => 'nonpayment'])
        ->assertRedirect();

    Bus::assertDispatched(ChangeServiceStateJob::class, fn ($job) =>
        $job->operation === 'suspend' && $job->reason === 'nonpayment'
    );
});

it('admin can unsuspend a service via route', function (): void {
    $admin   = adminUser();
    $service = makeSuspendedService();

    $this->actingAs($admin)
        ->post(route('admin.lifecycle.unsuspend', $service))
        ->assertRedirect();

    Bus::assertDispatched(ChangeServiceStateJob::class, fn ($job) =>
        $job->operation === 'unsuspend'
    );
});

it('admin can terminate a service via route', function (): void {
    $admin   = adminUser();
    $service = makeSuspendedService();

    $this->actingAs($admin)
        ->post(route('admin.lifecycle.terminate', $service))
        ->assertRedirect();

    Bus::assertDispatched(ChangeServiceStateJob::class, fn ($job) =>
        $job->operation === 'terminate'
    );
});

it('non-admin cannot access lifecycle admin', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.lifecycle.index'))
        ->assertStatus(403);
});
