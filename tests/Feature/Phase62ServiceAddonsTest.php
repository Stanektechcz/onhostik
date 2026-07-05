<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceAddon;
use App\Domains\Provisioning\Models\ServiceAddonSubscription;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── ServiceAddon model ─────────────────────────────────────────────────────────

it('ServiceAddon priceFormatted includes price and unit', function (): void {
    $addon = new ServiceAddon(['price_czk' => 5000]);
    expect($addon->priceFormatted())->toContain('50')
        ->and($addon->priceFormatted())->toContain('Kč/měs.');
});

it('ServiceAddon free addon shows 0', function (): void {
    $addon = new ServiceAddon(['price_czk' => 0]);
    expect($addon->priceFormatted())->toContain('0');
});

// ── ServiceAddonSubscription model ────────────────────────────────────────────

it('ServiceAddonSubscription isActive returns true when not cancelled', function (): void {
    $sub = new ServiceAddonSubscription(['cancelled_at' => null]);
    expect($sub->isActive())->toBeTrue();
});

it('ServiceAddonSubscription isActive returns false when cancelled', function (): void {
    $sub = new ServiceAddonSubscription(['cancelled_at' => now()]);
    expect($sub->isActive())->toBeFalse();
});

// ── Service has addonSubscriptions relation ───────────────────────────────────

it('Service has addonSubscriptions HasMany relation', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);
    $addon   = ServiceAddon::create(['name' => 'Extra Backup', 'slug' => 'extra-backup', 'price_czk' => 5000, 'is_active' => true]);

    ServiceAddonSubscription::create([
        'service_id'       => $service->id,
        'service_addon_id' => $addon->id,
        'activated_at'     => now(),
    ]);

    expect($service->addonSubscriptions()->count())->toBe(1);
});

// ── Panel: activate addon ─────────────────────────────────────────────────────

it('customer can activate an addon on active service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);
    $addon   = ServiceAddon::create(['name' => 'SSL', 'slug' => 'ssl', 'price_czk' => 10000, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('panel.services.addons.activate', $service), ['addon_id' => $addon->id])
        ->assertRedirect();

    expect(ServiceAddonSubscription::where('service_id', $service->id)->where('service_addon_id', $addon->id)->whereNull('cancelled_at')->exists())->toBeTrue();
});

it('cannot activate addon on non-active service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Suspended]);
    $addon   = ServiceAddon::create(['name' => 'SSL', 'slug' => 'ssl2', 'price_czk' => 10000, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('panel.services.addons.activate', $service), ['addon_id' => $addon->id])
        ->assertSessionHasErrors('addon');
});

it('cannot activate the same addon twice', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);
    $addon   = ServiceAddon::create(['name' => 'SSL', 'slug' => 'ssl3', 'price_czk' => 10000, 'is_active' => true]);

    ServiceAddonSubscription::create(['service_id' => $service->id, 'service_addon_id' => $addon->id, 'activated_at' => now()]);

    $this->actingAs($user)
        ->post(route('panel.services.addons.activate', $service), ['addon_id' => $addon->id])
        ->assertSessionHasErrors('addon');
});

it('cannot activate inactive addon', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);
    $addon   = ServiceAddon::create(['name' => 'Old addon', 'slug' => 'old-addon', 'price_czk' => 0, 'is_active' => false]);

    $this->actingAs($user)
        ->post(route('panel.services.addons.activate', $service), ['addon_id' => $addon->id])
        ->assertSessionHasErrors('addon');
});

it('customer cannot activate addon on another customer service', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $service = Service::factory()->for($user1->customer)->create(['status' => ServiceStatus::Active]);
    $addon   = ServiceAddon::create(['name' => 'SSL', 'slug' => 'ssl4', 'price_czk' => 10000, 'is_active' => true]);

    $this->actingAs($user2)
        ->post(route('panel.services.addons.activate', $service), ['addon_id' => $addon->id])
        ->assertStatus(403);
});

// ── Panel: cancel addon ────────────────────────────────────────────────────────

it('customer can cancel an active addon subscription', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);
    $addon   = ServiceAddon::create(['name' => 'SSL', 'slug' => 'ssl5', 'price_czk' => 10000, 'is_active' => true]);
    $sub     = ServiceAddonSubscription::create(['service_id' => $service->id, 'service_addon_id' => $addon->id, 'activated_at' => now()]);

    $this->actingAs($user)
        ->delete(route('panel.services.addons.cancel', [$service, $sub]))
        ->assertRedirect();

    expect($sub->fresh()->cancelled_at)->not->toBeNull();
});

// ── Panel: addons index ────────────────────────────────────────────────────────

it('customer can view addons page for their service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);
    ServiceAddon::create(['name' => 'Geo Backup', 'slug' => 'geo-backup', 'price_czk' => 20000, 'is_active' => true]);

    $this->actingAs($user)
        ->get(route('panel.services.addons.index', $service))
        ->assertOk()
        ->assertSee('Geo Backup');
});

// ── Admin CRUD ─────────────────────────────────────────────────────────────────

it('admin can list service addons', function (): void {
    $admin = adminUser();
    ServiceAddon::create(['name' => 'Extra IP', 'slug' => 'extra-ip', 'price_czk' => 15000, 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('admin.service-addons.index'))
        ->assertOk()
        ->assertSee('Extra IP');
});

it('admin can create a service addon', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.service-addons.store'), [
            'name'       => 'CDN Boost',
            'slug'       => 'cdn-boost',
            'price_czk'  => 25000,
            'is_active'  => '1',
            'sort_order' => 5,
        ])
        ->assertRedirect(route('admin.service-addons.index'));

    expect(ServiceAddon::where('slug', 'cdn-boost')->exists())->toBeTrue();
});

it('admin can update a service addon', function (): void {
    $admin = adminUser();
    $addon = ServiceAddon::create(['name' => 'Old name', 'slug' => 'old-name', 'price_czk' => 0, 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.service-addons.update', $addon), [
            'name'      => 'New name',
            'slug'      => 'old-name',
            'price_czk' => 9900,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.service-addons.index'));

    expect($addon->fresh()->name)->toBe('New name');
});

it('admin can delete a service addon', function (): void {
    $admin = adminUser();
    $addon = ServiceAddon::create(['name' => 'Delete me', 'slug' => 'delete-me', 'price_czk' => 0, 'is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.service-addons.destroy', $addon))
        ->assertRedirect(route('admin.service-addons.index'));

    expect(ServiceAddon::find($addon->id))->toBeNull();
});

it('non-admin cannot manage addons', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.service-addons.index'))
        ->assertStatus(403);
});
