<?php

declare(strict_types=1);

use App\Models\ServiceConfigProfile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list service config profiles', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.service-config-profiles.index'))
        ->assertOk()
        ->assertViewIs('admin.service-config-profiles.index');
});

it('admin can create a service config profile', function () {
    $admin = adminUser();
    $this->actingAs($admin)->post(route('admin.service-config-profiles.store'), [
        'name'            => 'PHP 8.2 Profile',
        'service_type'    => 'web_hosting',
        'config_data_raw' => '{"php_version": "8.2"}',
        'is_active'       => true,
    ])->assertRedirect();
    $this->assertDatabaseHas('service_config_profiles', ['name' => 'PHP 8.2 Profile', 'service_type' => 'web_hosting']);
});

it('admin can toggle profile active state', function () {
    $admin = adminUser();
    $profile = ServiceConfigProfile::create([
        'name'         => 'Legacy PHP',
        'service_type' => 'web_hosting',
        'config_data'  => [],
        'is_active'    => true,
        'created_by'   => $admin->id,
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.service-config-profiles.update', $profile))
        ->assertRedirect();
    $this->assertDatabaseHas('service_config_profiles', ['id' => $profile->id, 'is_active' => false]);
});

it('admin can delete a service config profile', function () {
    $admin = adminUser();
    $profile = ServiceConfigProfile::create([
        'name'         => 'Old Profile',
        'service_type' => 'vps',
        'config_data'  => [],
        'is_active'    => false,
        'created_by'   => $admin->id,
    ]);
    $this->actingAs($admin)
        ->delete(route('admin.service-config-profiles.destroy', $profile))
        ->assertRedirect();
    $this->assertDatabaseMissing('service_config_profiles', ['id' => $profile->id]);
});

it('admin config profile store fails without name', function () {
    $admin = adminUser();
    $this->actingAs($admin)->post(route('admin.service-config-profiles.store'), [
        'service_type' => 'vps',
    ])->assertSessionHasErrors('name');
});
