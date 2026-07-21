<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CalculatePlanChangeProrationAction;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Products\Enums\BillingCycle;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Pro-rata on a mid-period plan change (audit D60).
 *
 * The customer already paid for the whole period, so the unused remainder of
 * the OLD plan is credited and the NEW plan is charged only for the days that
 * remain. Only the difference changes hands.
 *
 * The previous implementation credited the NEW plan's price for the days
 * already ELAPSED, which meant an upgrade could come out cheaper than a
 * downgrade.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** Service on a known plan, exactly half-way through a 30-day period. */
function serviceMidPeriod(int $oldPriceCzk, int $newPriceCzk): array
{
    $user    = customerUser();
    $product = Product::factory()->create();

    $oldPlan = PricingPlan::factory()->for($product)->create([
        'billing_cycle' => BillingCycle::Monthly,
        'price_czk'     => $oldPriceCzk,
    ]);
    $newPlan = PricingPlan::factory()->for($product)->create([
        'billing_cycle' => BillingCycle::Monthly,
        'price_czk'     => $newPriceCzk,
    ]);

    ['order' => $order] = placeOrder($user);
    $item = $order->items->first();
    $item->update(['pricing_plan_id' => $oldPlan->id]);

    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'product_id'    => $product->id,
        'order_item_id' => $item->id,
        'status'        => ServiceStatus::Active,
        // Half of a ~30 day month still to run.
        'next_due_date' => now()->addDays(15),
    ]);

    return ['user' => $user, 'service' => $service, 'oldPlan' => $oldPlan, 'newPlan' => $newPlan];
}

// ── The calculation ───────────────────────────────────────────────────────────

it('credits the unused old plan and charges the new plan for the remaining days', function (): void {
    ['service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(10000, 20000); // 100 → 200 CZK

    $result = app(CalculatePlanChangeProrationAction::class)->execute($service, $newPlan);

    // ~half the period remains, so ~half of each price.
    expect($result['unused_credit']->getMinorAmount()->toInt())->toBeGreaterThan(4000)
        ->and($result['unused_credit']->getMinorAmount()->toInt())->toBeLessThan(6000)
        ->and($result['prorated_charge']->getMinorAmount()->toInt())->toBeGreaterThan(9000)
        ->and($result['prorated_charge']->getMinorAmount()->toInt())->toBeLessThan(11000);
});

it('charges the difference on an upgrade', function (): void {
    ['service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(10000, 20000);

    $result = app(CalculatePlanChangeProrationAction::class)->execute($service, $newPlan);

    // charge − credit ≈ half of the 100 CZK price gap.
    expect($result['is_upgrade'])->toBeTrue()
        ->and($result['difference']->getMinorAmount()->toInt())->toBeGreaterThan(4000)
        ->and($result['difference']->getMinorAmount()->toInt())->toBeLessThan(6000);
});

it('produces a negative difference on a downgrade', function (): void {
    ['service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(20000, 10000); // 200 → 100 CZK

    $result = app(CalculatePlanChangeProrationAction::class)->execute($service, $newPlan);

    // A downgrade must never cost the customer money.
    expect($result['is_upgrade'])->toBeFalse()
        ->and($result['difference']->isNegative())->toBeTrue();
});

it('makes an upgrade cost more than a downgrade', function (): void {
    // The exact regression: the old maths could invert these.
    ['service' => $up,   'newPlan' => $upPlan]   = serviceMidPeriod(10000, 20000);
    ['service' => $down, 'newPlan' => $downPlan] = serviceMidPeriod(20000, 10000);

    $action = app(CalculatePlanChangeProrationAction::class);

    expect($action->execute($up, $upPlan)['difference']->getMinorAmount()->toInt())
        ->toBeGreaterThan($action->execute($down, $downPlan)['difference']->getMinorAmount()->toInt());
});

it('settles nothing when the price is unchanged', function (): void {
    ['service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(15000, 15000);

    $result = app(CalculatePlanChangeProrationAction::class)->execute($service, $newPlan);

    expect($result['difference']->isZero())->toBeTrue();
});

it('charges nothing extra when the period has already ended', function (): void {
    ['service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(10000, 20000);
    $service->update(['next_due_date' => now()->subDay()]);

    $result = app(CalculatePlanChangeProrationAction::class)->execute($service, $newPlan);

    // Nothing left to prorate — the new price simply applies next cycle.
    expect($result['days_remaining'])->toBe(0)
        ->and($result['difference']->isZero())->toBeTrue();
});

it('prorates an annual plan over its own period, not a month', function (): void {
    $user    = customerUser();
    $product = Product::factory()->create();

    $annual = PricingPlan::factory()->for($product)->create([
        'billing_cycle' => BillingCycle::Annually,
        'price_czk'     => 120000, // 1200 CZK / year
    ]);
    $newAnnual = PricingPlan::factory()->for($product)->create([
        'billing_cycle' => BillingCycle::Annually,
        'price_czk'     => 240000,
    ]);

    ['order' => $order] = placeOrder($user);
    $item = $order->items->first();
    $item->update(['pricing_plan_id' => $annual->id]);

    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $item->id,
        'next_due_date' => now()->addDays(182), // ~half a year left
    ]);

    $result = app(CalculatePlanChangeProrationAction::class)->execute($service, $newAnnual);

    expect($result['days_in_period'])->toBeGreaterThan(300)
        ->and($result['unused_credit']->getMinorAmount()->toInt())->toBeGreaterThan(50000);
});

// ── Preview endpoint ──────────────────────────────────────────────────────────

it('previews the proration for the customer', function (): void {
    ['user' => $user, 'service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(10000, 20000);

    $response = $this->actingAs($user)
        ->getJson(route('panel.services.prorate.calculate', [$service, 'new_plan_id' => $newPlan->id]));

    $response->assertOk();

    expect($response->json('is_upgrade'))->toBeTrue()
        ->and($response->json('amount_due_now'))->toBeGreaterThan(0)
        ->and($response->json('prorated_credit'))->toBeGreaterThan(0);
});

it('forbids previewing proration on someone else\'s service', function (): void {
    ['service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(10000, 20000);

    $this->actingAs(customerUser())
        ->getJson(route('panel.services.prorate.calculate', [$service, 'new_plan_id' => $newPlan->id]))
        ->assertForbidden();
});

// ── Settlement ────────────────────────────────────────────────────────────────

it('invoices the difference when upgrading', function (): void {
    ['user' => $user, 'service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(10000, 20000);

    $this->actingAs($user)
        ->post(route('panel.services.apply-change-plan', $service), ['plan_id' => $newPlan->id])
        ->assertRedirect();

    $adhoc = Invoice::where('customer_id', $user->customer->id)
        ->where('type', InvoiceType::Invoice->value)
        ->latest('id')
        ->first();

    expect($adhoc)->not->toBeNull()
        ->and($adhoc->total->getMinorAmount()->toInt())->toBeGreaterThan(0);
});

it('returns the overpayment to credit when downgrading', function (): void {
    ['user' => $user, 'service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(20000, 10000);

    $before = app(CreditLedger::class)->getBalance($user->customer)->getMinorAmount()->toInt();

    $this->actingAs($user)
        ->post(route('panel.services.apply-change-plan', $service), ['plan_id' => $newPlan->id])
        ->assertRedirect();

    $after = app(CreditLedger::class)->getBalance($user->customer->fresh())->getMinorAmount()->toInt();

    expect($after)->toBeGreaterThan($before);
});

it('records the proration figures in the audit log', function (): void {
    ['user' => $user, 'service' => $service, 'newPlan' => $newPlan] = serviceMidPeriod(10000, 20000);

    $this->actingAs($user)
        ->post(route('panel.services.apply-change-plan', $service), ['plan_id' => $newPlan->id]);

    $entry = \Spatie\Activitylog\Models\Activity::where('description', 'service.plan_change_requested')->firstOrFail();

    expect($entry->properties)->toHaveKeys(['unused_credit', 'prorated_charge', 'difference']);
});
