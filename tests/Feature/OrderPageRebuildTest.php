<?php

declare(strict_types=1);

use App\Domains\Products\Models\PricingPlan;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * The order page (panel/orders/create) after the Cuba-native rebuild.
 *
 * Regression guards for the reported breakage:
 *  - the whole page was wrapped in one <form> and cart buttons were nested
 *    <form>s inside it (invalid HTML → add-to-cart silently dead),
 *  - every plan showed "vybráno",
 *  - parameters printed raw keys (disk_mb: 5120).
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('renders the order page with plan cards', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.orders.create'))
        ->assertOk()
        ->assertSee('plan-card', false)
        ->assertSee('Do košíku');
});

it('does not wrap the page in a single order form (no nested forms)', function (): void {
    $user = customerUser();

    $html = $this->actingAs($user)->get(route('panel.orders.create'))->getContent();

    // The old page had <form id="order-form"> wrapping everything.
    expect($html)->not->toContain('id="order-form"');

    // No <form> may contain another <form>.
    expect(preg_match('/<form\b[^>]*>(?:(?!<\/form>).)*<form\b/is', $html))->toBe(0);
});

it('shows a visible cart link with the current item count', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.orders.create'))
        ->assertOk()
        ->assertSee('id="cart-count"', false)
        ->assertSee(route('panel.cart.index'));
});

it('marks nothing as "in cart" when the cart is empty', function (): void {
    $user = customerUser();

    $html = $this->actingAs($user)->get(route('panel.orders.create'))->getContent();

    // "V košíku" also appears in the fetch-success JS string, so assert on
    // the rendered success button class instead: none should be present.
    expect($html)->not->toContain('btn btn-success w-full');
});

it('marks a plan as "in cart" only after it was added', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $html = $this->actingAs($user)->get(route('panel.orders.create'))->getContent();

    // The added plan's button renders with the success class server-side.
    expect($html)->toContain('btn btn-success w-full');
});

it('renders humanised parameter labels and values, not raw keys', function (): void {
    $user = customerUser();

    $html = $this->actingAs($user)->get(route('panel.orders.create'))->getContent();

    // Human labels from the resources translation, formatted values.
    expect($html)->toContain('DATABÁZE')
        ->and($html)->toContain('GB')      // ResourceFormatter turns disk_mb → "5 GB"
        ->and($html)->not->toContain('disk_mb');
});

it('add-to-cart is a standalone POST form per plan', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)
        ->get(route('panel.orders.create'))
        ->assertSee(route('panel.cart.add', $plan->id));
});
