<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('customer can rename their own service', function (): void {
    $user = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'label'       => 'Původní název',
    ]);

    $this->actingAs($user)
         ->patch(route('panel.services.rename', $service), ['label' => 'Nový název'])
         ->assertRedirect();

    expect($service->fresh()->label)->toBe('Nový název');
});

it('rename redirects back with success message', function (): void {
    $user = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'label'       => 'Stará služba',
    ]);

    $this->actingAs($user)
         ->patch(route('panel.services.rename', $service), ['label' => 'Nová služba'])
         ->assertRedirect()
         ->assertSessionHas('status');
});

it('rename validates label min length', function (): void {
    $user = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'label'       => 'Validní název',
    ]);

    $this->actingAs($user)
         ->patch(route('panel.services.rename', $service), ['label' => 'A'])
         ->assertSessionHasErrors(['label']);

    expect($service->fresh()->label)->toBe('Validní název');
});

it('rename validates label max length', function (): void {
    $user = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'label'       => 'Validní název',
    ]);

    $this->actingAs($user)
         ->patch(route('panel.services.rename', $service), ['label' => str_repeat('x', 101)])
         ->assertSessionHasErrors(['label']);
});

it('customer cannot rename another customers service', function (): void {
    adminUser();
    $owner = customerUser();
    $other = customerUser();

    $service = Service::factory()->create([
        'customer_id' => $owner->customer->id,
        'label'       => 'Cizí služba',
    ]);

    $this->actingAs($other)
         ->patch(route('panel.services.rename', $service), ['label' => 'Ukradnutý název'])
         ->assertForbidden();

    expect($service->fresh()->label)->toBe('Cizí služba');
});

it('guest is redirected from rename route', function (): void {
    $user = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'label'       => 'Chráněná služba',
    ]);

    $this->patch(route('panel.services.rename', $service), ['label' => 'Pokus'])
         ->assertRedirect(route('login'));
});
