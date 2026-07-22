<?php

declare(strict_types=1);

use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * First-login guided panel tour — shows once, on the dashboard, then never again.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('shows the tour on the dashboard for a user who has not seen it', function (): void {
    $user = customerUser();
    expect($user->onboarding_tour_completed_at)->toBeNull();

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('onhost-tour', false);
});

it('does not show the tour once completed', function (): void {
    $user = customerUser();
    $user->forceFill(['onboarding_tour_completed_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk()
        ->assertDontSee('id="onhost-tour"', false);
});

it('does not show the tour off the dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.services.index'))
        ->assertOk()
        ->assertDontSee('id="onhost-tour"', false);
});

it('marks the tour complete via the endpoint', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.tour.complete'))
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($user->fresh()->onboarding_tour_completed_at)->not->toBeNull();
});

it('does not re-stamp the completion time on a repeat call', function (): void {
    $user = customerUser();
    $user->forceFill(['onboarding_tour_completed_at' => now()->subDays(3)])->save();
    $original = $user->fresh()->onboarding_tour_completed_at;

    $this->actingAs($user)->post(route('panel.tour.complete'))->assertOk();

    // Idempotent — the first completion time stands.
    expect($user->fresh()->onboarding_tour_completed_at->equalTo($original))->toBeTrue();
});
