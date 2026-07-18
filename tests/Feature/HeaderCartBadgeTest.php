<?php

declare(strict_types=1);

use App\Domains\Products\Models\PricingPlan;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * The navbar cart box (Cuba .cart-box) with its live item counter.
 *
 * Regression guard for "Košík chybí v navbaru": the header must render a
 * cart link with a badge whose count comes from the panel_cart session and
 * only carries the `.show` class when the cart is non-empty.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('renders the navbar cart box for a customer', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.cart.index'))
        ->assertOk()
        ->assertSee('cart-box', false)
        ->assertSee('id="cart-nav-count"', false);
});

it('shows an empty (hidden) cart badge when the cart is empty', function (): void {
    $user = customerUser();

    $html = $this->actingAs($user)->get(route('panel.cart.index'))->getContent();

    // The badge exists but must not carry the `.show` class while empty.
    expect($html)->toContain('id="cart-nav-count"')
        ->and($html)->not->toMatch('/badge[^"]*\bshow\b[^"]*"\s+id="cart-nav-count"/');
});

it('shows the item count on the navbar badge once the cart has lines', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $html = $this->actingAs($user)->get(route('panel.cart.index'))->getContent();

    // Non-empty cart → the badge is shown and reflects the count.
    expect($html)->toMatch('/badge[^"]*\bshow\b[^"]*"\s+id="cart-nav-count">1</');
});
