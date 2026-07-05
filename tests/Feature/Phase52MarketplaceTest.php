<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Marketplace\Models\AppInstallation;
use App\Domains\Marketplace\Models\MarketplaceApp;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MarketplaceAppSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, MarketplaceAppSeeder::class]);
});

// ── Model helpers ─────────────────────────────────────────────────────────────

it('MarketplaceApp categoryLabel returns correct string', function (): void {
    $app = new MarketplaceApp(['category' => 'cms']);
    expect($app->categoryLabel())->toBe('CMS');

    $app->category = 'ecommerce';
    expect($app->categoryLabel())->toBe('E-shop');
});

it('AppInstallation statusLabel and statusColor work correctly', function (): void {
    $inst = new AppInstallation(['status' => 'installed']);
    expect($inst->statusLabel())->toBe('Nainstalováno');
    expect($inst->statusColor())->toBe('success');
    expect($inst->isInstalled())->toBeTrue();

    $inst->status = 'failed';
    expect($inst->statusLabel())->toBe('Chyba');
    expect($inst->statusColor())->toBe('danger');
});

// ── MarketplaceAppSeeder ──────────────────────────────────────────────────────

it('seeder creates at least 7 marketplace apps', function (): void {
    expect(MarketplaceApp::count())->toBeGreaterThanOrEqual(7);
});

// ── Customer panel ────────────────────────────────────────────────────────────

it('customer can view marketplace for their service', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->get(route('panel.marketplace.index', $service))
        ->assertOk()
        ->assertViewIs('panel.marketplace.index');
});

it('customer can install an app on their active service', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $app = MarketplaceApp::where('slug', 'wordpress')->first();
    assert($app !== null);

    $this->actingAs($user)
        ->post(route('panel.marketplace.install', [$service, $app]))
        ->assertRedirect();

    expect(
        AppInstallation::where('service_id', $service->id)
            ->where('marketplace_app_id', $app->id)
            ->where('status', 'installed')
            ->exists()
    )->toBeTrue();
});

it('install is idempotent — second install is skipped', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
    ]);
    $app = MarketplaceApp::where('slug', 'wordpress')->first();
    assert($app !== null);

    $this->actingAs($user)->post(route('panel.marketplace.install', [$service, $app]));
    $this->actingAs($user)->post(route('panel.marketplace.install', [$service, $app]));

    expect(
        AppInstallation::where('service_id', $service->id)
            ->where('marketplace_app_id', $app->id)
            ->count()
    )->toBe(1);
});

it('customer can remove an installed app', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
    ]);
    $app = MarketplaceApp::where('slug', 'wordpress')->first();
    assert($app !== null);

    $this->actingAs($user)->post(route('panel.marketplace.install', [$service, $app]));

    $this->actingAs($user)
        ->delete(route('panel.marketplace.remove', [$service, $app]))
        ->assertRedirect();

    expect(
        AppInstallation::where('service_id', $service->id)
            ->where('marketplace_app_id', $app->id)
            ->where('status', 'removed')
            ->exists()
    )->toBeTrue();
});

it('install fails on suspended service', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Suspended,
    ]);
    $app = MarketplaceApp::where('slug', 'phpmyadmin')->first();
    assert($app !== null);

    $this->actingAs($user)
        ->post(route('panel.marketplace.install', [$service, $app]))
        ->assertSessionHasErrors('install');
});

it('customer cannot access marketplace of another customer service', function (): void {
    $owner = customerUser();
    $other = customerUser();

    $service = Service::factory()->create([
        'customer_id' => $owner->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($other)
        ->get(route('panel.marketplace.index', $service))
        ->assertForbidden();
});

// ── Admin marketplace management ──────────────────────────────────────────────

it('admin can view marketplace management page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.marketplace.index'))
        ->assertOk()
        ->assertViewIs('admin.marketplace.index');
});

it('admin can add a new app to marketplace', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.store'), [
            'slug'      => 'ghost-blog',
            'name'      => 'Ghost',
            'category'  => 'cms',
            'is_active' => 1,
        ])
        ->assertRedirect();

    expect(MarketplaceApp::where('slug', 'ghost-blog')->exists())->toBeTrue();
});

it('duplicate slug is rejected', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.marketplace.store'), [
            'slug'      => 'wordpress',
            'name'      => 'Duplicate WP',
            'category'  => 'cms',
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('slug');
});

it('admin can toggle app active status', function (): void {
    $admin = adminUser();
    $app   = MarketplaceApp::where('slug', 'joomla')->first();
    assert($app !== null);
    $original = $app->is_active;

    $this->actingAs($admin)
        ->post(route('admin.marketplace.toggle', $app))
        ->assertRedirect();

    expect($app->fresh()->is_active)->toBe(! $original);
});

it('admin can delete a marketplace app', function (): void {
    $admin = adminUser();
    $app   = MarketplaceApp::create([
        'slug'      => 'to-delete-app',
        'name'      => 'ToDelete',
        'category'  => 'other',
        'icon'      => 'package',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.marketplace.destroy', $app))
        ->assertRedirect();

    expect(MarketplaceApp::find($app->id))->toBeNull();
});
