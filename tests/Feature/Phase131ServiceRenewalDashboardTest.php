<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('admin can view service renewal dashboard', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.services.renewal-dashboard'))
         ->assertOk()
         ->assertSee('Dashboard obnov');
});

it('renewal dashboard shows overdue services', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'status'        => ServiceStatus::Active,
        'label'         => 'Overdue Service',
        'next_due_date' => now()->subDays(5),
    ]);

    $this->actingAs($admin)
         ->get(route('admin.services.renewal-dashboard'))
         ->assertOk()
         ->assertSee('Po splatnosti');
});

it('renewal dashboard shows services due soon', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'status'        => ServiceStatus::Active,
        'label'         => 'Renewal Soon Service',
        'next_due_date' => now()->addDays(10),
    ]);

    $this->actingAs($admin)
         ->get(route('admin.services.renewal-dashboard', ['days' => 30]))
         ->assertOk()
         ->assertSee('Renewal Soon Service');
});

it('renewal dashboard respects days filter', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'status'        => ServiceStatus::Active,
        'label'         => 'Far Future Service',
        'next_due_date' => now()->addDays(45),
    ]);

    // 30-day window: should NOT appear
    $content30 = $this->actingAs($admin)
         ->get(route('admin.services.renewal-dashboard', ['days' => 30]))
         ->getContent();

    // 60-day window: should appear
    $content60 = $this->actingAs($admin)
         ->get(route('admin.services.renewal-dashboard', ['days' => 60]))
         ->getContent();

    expect($content30)->not->toContain('Far Future Service');
    expect($content60)->toContain('Far Future Service');
});

it('renewal dashboard shows count of services without auto-renew', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'status'        => ServiceStatus::Active,
        'next_due_date' => now()->addDays(5),
        'auto_renew'    => false,
    ]);

    $this->actingAs($admin)
         ->get(route('admin.services.renewal-dashboard'))
         ->assertOk()
         ->assertSee('Bez auto-obnovy');
});

it('customer cannot access renewal dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.services.renewal-dashboard'))
         ->assertForbidden();
});

it('renewal dashboard excludes terminated services', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    Service::factory()->create([
        'customer_id'    => $customer->customer->id,
        'status'         => ServiceStatus::Terminated,
        'label'          => 'Terminated Service',
        'next_due_date'  => now()->addDays(5),
        'terminated_at'  => now()->subDay(),
    ]);

    $this->actingAs($admin)
         ->get(route('admin.services.renewal-dashboard'))
         ->assertOk()
         ->assertDontSee('Terminated Service');
});
