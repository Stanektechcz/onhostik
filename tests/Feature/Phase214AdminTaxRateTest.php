<?php

use App\Models\TaxRate;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view tax rates index', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.tax-rates.index'))
        ->assertOk()
        ->assertViewHas('rates');
});

it('admin can create a tax rate', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.tax-rates.store'), [
            'country_code' => 'CZ',
            'name'         => 'DPH standardní',
            'rate_percent' => 21.00,
            'type'         => 'standard',
            'is_active'    => true,
        ])
        ->assertRedirect();

    expect(TaxRate::where('country_code', 'CZ')->where('type', 'standard')->exists())->toBeTrue();
});

it('country_code must be exactly 2 characters', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.tax-rates.store'), [
            'country_code' => 'CZE',
            'name'         => 'Test',
            'rate_percent' => 21,
            'type'         => 'standard',
        ])
        ->assertSessionHasErrors('country_code');
});

it('admin can update a tax rate', function (): void {
    $rate = TaxRate::create([
        'country_code' => 'SK',
        'name'         => 'Old',
        'rate_percent' => 20,
        'type'         => 'standard',
        'is_active'    => true,
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.tax-rates.update', $rate), [
            'name'         => 'Updated',
            'rate_percent' => 21,
            'is_active'    => true,
        ])
        ->assertRedirect();

    expect($rate->fresh()->name)->toBe('Updated');
});

it('admin can delete a tax rate', function (): void {
    $rate = TaxRate::create([
        'country_code' => 'DE',
        'name'         => 'MwSt',
        'rate_percent' => 19,
        'type'         => 'standard',
        'is_active'    => true,
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.tax-rates.destroy', $rate))
        ->assertRedirect();

    expect(TaxRate::find($rate->id))->toBeNull();
});
