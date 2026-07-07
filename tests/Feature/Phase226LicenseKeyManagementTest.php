<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view license keys', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.license-keys.index'))
        ->assertOk()
        ->assertViewHas('keys');
});

it('admin can create a license key', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.license-keys.store'), [
            'product_name' => 'Test Product',
            'license_key'  => 'XXXX-YYYY-ZZZZ',
            'status'       => 'available',
        ])
        ->assertRedirect();

    expect(\App\Models\LicenseKey::count())->toBe(1);
});

it('admin can update license key status', function (): void {
    $key = \App\Models\LicenseKey::create([
        'product_name' => 'P',
        'license_key'  => 'KEY123',
        'status'       => 'available',
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.license-keys.update', $key), [
            'status' => 'assigned',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('license_keys', ['status' => 'assigned']);
});

it('guest cannot access license keys', function (): void {
    $this->get(route('admin.license-keys.index'))
        ->assertRedirect();
});

it('store rejects duplicate license key', function (): void {
    \App\Models\LicenseKey::create([
        'product_name' => 'P',
        'license_key'  => 'DUP-KEY',
        'status'       => 'available',
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.license-keys.store'), [
            'product_name' => 'Another Product',
            'license_key'  => 'DUP-KEY',
            'status'       => 'available',
        ])
        ->assertSessionHasErrors('license_key');
});
