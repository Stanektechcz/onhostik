<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceCancellation;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Model ─────────────────────────────────────────────────────────────────────

it('ServiceCancellation::reasonLabel returns Czech label', function (): void {
    $sc = new ServiceCancellation(['reason' => 'too_expensive']);
    expect($sc->reasonLabel())->toBe('Příliš drahé');
});

it('ServiceCancellation::reasonLabel falls back to raw reason for unknown', function (): void {
    $sc = new ServiceCancellation(['reason' => 'unknown_xyz']);
    expect($sc->reasonLabel())->toBe('unknown_xyz');
});

// ── Panel: cancellation form saves survey ─────────────────────────────────────

it('customer can cancel service without reason (backwards compat)', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.request-cancel', $service))
        ->assertRedirect();

    $this->assertDatabaseCount('service_cancellations', 0);
});

it('customer cancellation saves cancellation reason survey', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.request-cancel', $service), [
            'cancellation_reason'   => 'too_expensive',
            'cancellation_feedback' => 'Cena je vysoká pro moje potřeby.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('service_cancellations', [
        'service_id' => $service->id,
        'user_id'    => $user->id,
        'reason'     => 'too_expensive',
        'feedback'   => 'Cena je vysoká pro moje potřeby.',
    ]);
});

it('validates cancellation_reason must be a valid enum value', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.request-cancel', $service), [
            'cancellation_reason' => 'invalid_reason',
        ])
        ->assertSessionHasErrors(['cancellation_reason']);
});

it('feedback is optional even when reason is provided', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.request-cancel', $service), [
            'cancellation_reason' => 'not_needed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('service_cancellations', [
        'service_id' => $service->id,
        'reason'     => 'not_needed',
        'feedback'   => null,
    ]);
});

// ── Admin: cancellation survey page ──────────────────────────────────────────

it('admin can view the cancellation survey page with no data', function (): void {
    ServiceCancellation::query()->delete();

    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.cancellation-survey'))
        ->assertOk()
        ->assertSee('Důvody rušení služeb');
});

it('admin cancellation survey shows reason breakdown', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    ServiceCancellation::create([
        'service_id' => $service->id,
        'user_id'    => $user->id,
        'reason'     => 'switching_provider',
        'feedback'   => null,
    ]);

    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.cancellation-survey'))
        ->assertOk()
        ->assertSee('Přecházím ke konkurenci');
});

it('customer cannot access admin cancellation survey', function (): void {
    $customer = customerUser();
    $this->actingAs($customer)
        ->get(route('admin.cancellation-survey'))
        ->assertForbidden();
});
