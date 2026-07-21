<?php

declare(strict_types=1);

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Reseller\Models\ResellerPricingOverride;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Domains\Reseller\Services\ResellerPriceResolver;
use App\Domains\Shared\Enums\Currency;
use App\Models\ResellerPayoutRequest;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Phase K — reseller / partner / affiliate.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── K146: individual per-plan pricing ─────────────────────────────────────────

it('falls back to the plan base price when no reseller is involved', function (): void {
    $plan = PricingPlan::query()->firstOrFail();

    $price = app(ResellerPriceResolver::class)->unitPrice(null, $plan, Currency::CZK);

    expect($price->getMinorAmount()->toInt())->toBe($plan->price_czk);
});

it('applies the reseller markup when there is no override', function (): void {
    $plan     = PricingPlan::query()->firstOrFail();
    $reseller = ResellerProfile::factory()->create(['markup_percent' => 20.0]);

    $price = app(ResellerPriceResolver::class)->unitPrice($reseller, $plan, Currency::CZK);

    expect($price->getMinorAmount()->toInt())
        ->toBe((int) round($plan->price_czk * 1.20));
});

it('lets an individual override win over the markup', function (): void {
    /*
     | Regression: ResellerPricingOverride had admin CRUD but was never read at
     | pricing time, so a negotiated individual price was configured and then
     | silently ignored while the customer paid the markup price.
     */
    $plan     = PricingPlan::query()->firstOrFail();
    $reseller = ResellerProfile::factory()->create(['markup_percent' => 50.0]);

    ResellerPricingOverride::create([
        'reseller_id'     => $reseller->id,
        'pricing_plan_id' => $plan->id,
        'price_czk'       => 12345,
        'is_active'       => true,
    ]);

    $price = app(ResellerPriceResolver::class)->unitPrice($reseller, $plan, Currency::CZK);

    // The fixed price, NOT the fixed price plus 50%.
    expect($price->getMinorAmount()->toInt())->toBe(12345);
});

it('ignores an inactive override', function (): void {
    $plan     = PricingPlan::query()->firstOrFail();
    $reseller = ResellerProfile::factory()->create(['markup_percent' => 10.0]);

    ResellerPricingOverride::create([
        'reseller_id'     => $reseller->id,
        'pricing_plan_id' => $plan->id,
        'price_czk'       => 999,
        'is_active'       => false,
    ]);

    $price = app(ResellerPriceResolver::class)->unitPrice($reseller, $plan, Currency::CZK);

    expect($price->getMinorAmount()->toInt())->toBe((int) round($plan->price_czk * 1.10));
});

it('does not treat a missing-currency override as a free plan', function (): void {
    // Override set for CZK only; a EUR order must not become 0.
    $plan     = PricingPlan::query()->whereNotNull('price_eur')->firstOrFail();
    $reseller = ResellerProfile::factory()->create(['markup_percent' => 15.0]);

    ResellerPricingOverride::create([
        'reseller_id'     => $reseller->id,
        'pricing_plan_id' => $plan->id,
        'price_czk'       => 5000,
        'price_eur'       => null,
        'is_active'       => true,
    ]);

    $price = app(ResellerPriceResolver::class)->unitPrice($reseller, $plan, Currency::EUR);

    expect($price->getMinorAmount()->toInt())
        ->toBe((int) round($plan->price_eur * 1.15))
        ->toBeGreaterThan(0);
});

// ── K145: payout state machine ────────────────────────────────────────────────

it('allows a legal payout transition', function (): void {
    $reseller = ResellerProfile::factory()->create();
    $request  = ResellerPayoutRequest::create([
        'reseller_profile_id' => $reseller->id,
        'amount'              => 100000,
        'currency'            => 'CZK',
        'status'              => 'pending',
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.reseller-payout-requests.update', $request), ['status' => 'approved'])
        ->assertRedirect();

    expect($request->refresh()->status)->toBe('approved');
});

it('refuses to re-pay an already paid request', function (): void {
    /*
     | This is the money bug: the old controller let any status become any
     | other, so a `paid` request could be flipped to `approved` and paid a
     | second time. `paid` is terminal.
     */
    $reseller = ResellerProfile::factory()->create();
    $request  = ResellerPayoutRequest::create([
        'reseller_profile_id' => $reseller->id,
        'amount'              => 100000,
        'currency'            => 'CZK',
        'status'              => 'paid',
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.reseller-payout-requests.update', $request), ['status' => 'approved'])
        ->assertSessionHasErrors('status');

    expect($request->refresh()->status)->toBe('paid');
});

it('cannot pay a request that was never approved', function (): void {
    $reseller = ResellerProfile::factory()->create();
    $request  = ResellerPayoutRequest::create([
        'reseller_profile_id' => $reseller->id,
        'amount'              => 100000,
        'currency'            => 'CZK',
        'status'              => 'pending',
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.reseller-payout-requests.update', $request), ['status' => 'paid'])
        ->assertSessionHasErrors('status');

    expect($request->refresh()->status)->toBe('pending');
});

it('records an activity entry when a payout status changes', function (): void {
    $reseller = ResellerProfile::factory()->create();
    $request  = ResellerPayoutRequest::create([
        'reseller_profile_id' => $reseller->id,
        'amount'              => 250000,
        'currency'            => 'CZK',
        'status'              => 'approved',
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.reseller-payout-requests.update', $request), ['status' => 'paid'])
        ->assertRedirect();

    // Money moved — there must be a trail.
    $this->assertDatabaseHas('activity_log', [
        'log_name'    => 'default',
        'description' => 'reseller_payout.status_changed',
        'subject_id'  => $request->id,
    ]);
});
