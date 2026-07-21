<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Phase N (N176): dark mode is a persisted per-user preference, not a
 * client-only toggle that resets.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('persists the dark-mode choice to the database', function (): void {
    $user = customerUser();

    expect($user->dark_mode)->toBeFalsy();

    $this->actingAs($user)
        ->postJson(route('panel.account.dark-mode.toggle'))
        ->assertOk()
        ->assertJsonPath('dark_mode', true);

    expect($user->refresh()->dark_mode)->toBeTrue();
});

it('applies the saved preference server-side so the page does not flash', function (): void {
    $user = customerUser();
    $user->update(['dark_mode' => true]);

    // Rendered on the server: the body carries dark-only on first paint, so a
    // dark-mode user never sees a flash of the light theme.
    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('class="dark-only"', false);
});

it('does not mark the body dark for a light-mode user', function (): void {
    $user = customerUser();
    $user->update(['dark_mode' => false]);

    $html = $this->actingAs($user)->get(route('panel.dashboard'))->content();

    expect($html)->not->toContain('class="dark-only"');
});

it('toggles back to light on a second call', function (): void {
    $user = customerUser();
    $user->update(['dark_mode' => true]);

    $this->actingAs($user)
        ->postJson(route('panel.account.dark-mode.toggle'))
        ->assertOk()
        ->assertJsonPath('dark_mode', false);

    expect($user->refresh()->dark_mode)->toBeFalse();
});

it('still works without JavaScript via a normal redirect', function (): void {
    $user = customerUser();

    // No-JS fallback: a plain form POST gets a redirect + flash, not JSON.
    $this->actingAs($user)
        ->post(route('panel.account.dark-mode.toggle'))
        ->assertRedirect();

    expect($user->refresh()->dark_mode)->toBeTrue();
});
