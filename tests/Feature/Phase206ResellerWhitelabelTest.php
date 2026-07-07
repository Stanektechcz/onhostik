<?php

use App\Domains\Reseller\Models\ResellerProfile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view reseller whitelabel index', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.reseller-whitelabel.index'))
        ->assertOk()
        ->assertViewHas('resellers');
});

it('admin can view edit form for a reseller', function (): void {
    $reseller = ResellerProfile::factory()->active()->create();

    $this->actingAs(adminUser())
        ->get(route('admin.reseller-whitelabel.edit', $reseller))
        ->assertOk()
        ->assertViewHas('reseller');
});

it('admin can update reseller whitelabel settings', function (): void {
    $reseller = ResellerProfile::factory()->active()->create();

    $this->actingAs(adminUser())
        ->patch(route('admin.reseller-whitelabel.update', $reseller), [
            'panel_title'   => 'My Hosting Panel',
            'support_email' => 'support@myreseller.cz',
            'support_phone' => '+420123456789',
        ])
        ->assertRedirect();

    expect($reseller->fresh()->panel_title)->toBe('My Hosting Panel');
    expect($reseller->fresh()->support_email)->toBe('support@myreseller.cz');
});

it('invalid primary color hex is rejected', function (): void {
    $reseller = ResellerProfile::factory()->active()->create();

    $this->actingAs(adminUser())
        ->patch(route('admin.reseller-whitelabel.update', $reseller), [
            'branding' => ['primary_color' => 'notacolor'],
        ])
        ->assertSessionHasErrors('branding.primary_color');
});

it('unauthenticated user cannot access reseller whitelabel', function (): void {
    $this->get(route('admin.reseller-whitelabel.index'))
        ->assertRedirect();
});
