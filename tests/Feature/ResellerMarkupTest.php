<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CreateOrderAction;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Domains\Shared\Enums\Currency;
use Spatie\Permission\Models\Permission;

// ────────────────────────────────────────────────────────────────────────
// PricingPlan::priceWithMarkup
// ────────────────────────────────────────────────────────────────────────

it('priceWithMarkup returns the base price when markup is zero', function (): void {
    $plan = PricingPlan::factory()->create(['price_czk' => 10000]); // 100.00 CZK

    $base   = $plan->priceFor(Currency::CZK);
    $marked = $plan->priceWithMarkup(Currency::CZK, 0.0);

    expect($marked->isEqualTo($base))->toBeTrue();
});

it('priceWithMarkup applies the percentage correctly', function (): void {
    $plan = PricingPlan::factory()->create(['price_czk' => 10000]); // 100.00 CZK

    $marked = $plan->priceWithMarkup(Currency::CZK, 20.0); // +20%

    // 100 * 1.20 = 120.00 CZK = 12000 minor units
    expect($marked->getMinorAmount()->toInt())->toBe(12000);
});

it('priceWithMarkup returns base price for negative markup', function (): void {
    $plan = PricingPlan::factory()->create(['price_czk' => 10000]);

    $marked = $plan->priceWithMarkup(Currency::CZK, -10.0);

    expect($marked->getMinorAmount()->toInt())->toBe(10000);
});

// ────────────────────────────────────────────────────────────────────────
// CreateOrderAction respects markup_percent config
// ────────────────────────────────────────────────────────────────────────

it('CreateOrderAction applies markup_percent to the order total', function (): void {
    $customer = Customer::factory()->create(['preferred_currency' => Currency::CZK]);
    $plan     = PricingPlan::factory()->create(['price_czk' => 10000]);

    $action = app(CreateOrderAction::class);
    $order  = $action->execute($customer, $plan, ['markup_percent' => 10.0]);

    // Net = 100 * 1.10 = 110.00 CZK
    // VAT depends on customer scenario — just check subtotal is marked up
    expect($order->subtotal->getMinorAmount()->toInt())->toBe(11000);
});

it('CreateOrderAction without markup uses base price', function (): void {
    $customer = Customer::factory()->create(['preferred_currency' => Currency::CZK]);
    $plan     = PricingPlan::factory()->create(['price_czk' => 10000]);

    $action = app(CreateOrderAction::class);
    $order  = $action->execute($customer, $plan);

    expect($order->subtotal->getMinorAmount()->toInt())->toBe(10000);
});

// ────────────────────────────────────────────────────────────────────────
// OrderController applies reseller markup on store
// ────────────────────────────────────────────────────────────────────────

it('ordering as an active reseller applies markup to the order total', function (): void {
    Permission::findOrCreate('access-reseller', 'web');

    $reseller = ResellerProfile::factory()->active()->create(['markup_percent' => 15.0]);
    $user     = $reseller->user;
    $user->givePermissionTo('access-reseller');

    Customer::factory()->create(['user_id' => $user->id, 'preferred_currency' => Currency::CZK]);

    $plan = PricingPlan::factory()->create(['price_czk' => 20000, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('panel.orders.store'), ['pricing_plan_id' => $plan->id])
        ->assertRedirect();

    $order = $user->customer->orders()->latest()->first();
    // 200.00 * 1.15 = 230.00 CZK = 23000 minor units
    expect($order->subtotal->getMinorAmount()->toInt())->toBe(23000);
});

it('ordering as a non-reseller uses base plan price', function (): void {
    $user = \App\Models\User::factory()->create();

    Customer::factory()->create(['user_id' => $user->id, 'preferred_currency' => Currency::CZK]);

    $plan = PricingPlan::factory()->create(['price_czk' => 20000, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('panel.orders.store'), ['pricing_plan_id' => $plan->id])
        ->assertRedirect();

    $order = $user->customer->orders()->latest()->first();
    expect($order->subtotal->getMinorAmount()->toInt())->toBe(20000);
});
