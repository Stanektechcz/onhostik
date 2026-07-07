<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeServiceForAutoRenew(): array
{
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
        'auto_renew'  => true,
        'label'       => 'Test Hosting',
    ]);
    return compact('user', 'service');
}

// ── View shows auto-renew state ───────────────────────────────────────────────

it('service show page renders auto-renew toggle section', function (): void {
    ['user' => $user, 'service' => $service] = makeServiceForAutoRenew();

    $this->actingAs($user)
         ->get(route('panel.services.show', $service))
         ->assertOk()
         ->assertSee('Automatická obnova')
         ->assertSee('Zapnuto');
});

it('service show page shows auto-renew as disabled when auto_renew is false', function (): void {
    ['user' => $user, 'service' => $service] = makeServiceForAutoRenew();
    $service->update(['auto_renew' => false]);

    $this->actingAs($user)
         ->get(route('panel.services.show', $service))
         ->assertOk()
         ->assertSee('Vypnuto');
});

// ── Toggle endpoint ───────────────────────────────────────────────────────────

it('customer can toggle auto-renew off', function (): void {
    ['user' => $user, 'service' => $service] = makeServiceForAutoRenew();

    expect($service->auto_renew)->toBeTrue();

    $this->actingAs($user)
         ->post(route('panel.services.toggle-auto-renew', $service))
         ->assertRedirect();

    expect($service->fresh()->auto_renew)->toBeFalse();
});

it('customer can toggle auto-renew back on', function (): void {
    ['user' => $user, 'service' => $service] = makeServiceForAutoRenew();
    $service->update(['auto_renew' => false]);

    $this->actingAs($user)
         ->post(route('panel.services.toggle-auto-renew', $service))
         ->assertRedirect();

    expect($service->fresh()->auto_renew)->toBeTrue();
});

it('other customer cannot toggle another customer service auto-renew', function (): void {
    ['service' => $service] = makeServiceForAutoRenew();
    $otherUser = customerUser();

    $this->actingAs($otherUser)
         ->post(route('panel.services.toggle-auto-renew', $service))
         ->assertForbidden();
});

it('guest cannot toggle auto-renew', function (): void {
    ['service' => $service] = makeServiceForAutoRenew();

    $this->post(route('panel.services.toggle-auto-renew', $service))
         ->assertRedirect(route('login'));
});

it('toggle auto-renew route is POST method', function (): void {
    ['user' => $user, 'service' => $service] = makeServiceForAutoRenew();

    $this->actingAs($user)
         ->get(route('panel.services.toggle-auto-renew', $service))
         ->assertMethodNotAllowed();
});
