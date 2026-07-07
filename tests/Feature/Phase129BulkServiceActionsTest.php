<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('admin can view bulk operations page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.bulk.index'))
         ->assertOk()
         ->assertSee('Hromadné operace');
});

it('admin can extend service due date in bulk', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'status'        => ServiceStatus::Active,
        'next_due_date' => now()->addDays(10),
    ]);

    $originalDue = $service->next_due_date->format('Y-m-d');

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-extend'), [
             'ids'  => [$service->id],
             'days' => 30,
         ])
         ->assertRedirect();

    expect($service->fresh()->next_due_date->format('Y-m-d'))
        ->not->toBe($originalDue);
});

it('admin can bulk suspend active services', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-suspend'), [
             'ids'    => [$service->id],
             'reason' => 'Bulk test suspend',
         ])
         ->assertRedirect();
});

it('admin can bulk resume suspended services', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create([
        'customer_id'  => $customer->customer->id,
        'status'       => ServiceStatus::Suspended,
        'suspended_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-resume'), [
             'ids' => [$service->id],
         ])
         ->assertRedirect();
});

it('admin can bulk enable auto-renew on services', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => ServiceStatus::Active,
        'auto_renew'  => false,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-auto-renew'), [
             'ids'        => [$service->id],
             'auto_renew' => 1,
         ])
         ->assertRedirect();

    expect($service->fresh()->auto_renew)->toBeTrue();
});

it('admin can bulk disable auto-renew on services', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => ServiceStatus::Active,
        'auto_renew'  => true,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-auto-renew'), [
             'ids'        => [$service->id],
             'auto_renew' => 0,
         ])
         ->assertRedirect();

    expect($service->fresh()->auto_renew)->toBeFalse();
});

it('customer cannot access bulk operations', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.bulk.index'))
         ->assertForbidden();
});
