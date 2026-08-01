<?php

declare(strict_types=1);

use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Loyalty\Listeners\AwardLoyaltyPointsOnInvoicePaid;
use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Domains\Loyalty\Services\LoyaltyPointsService;
use Brick\Money\Money;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function pointsSvc(): LoyaltyPointsService
{
    return app(LoyaltyPointsService::class);
}

function reward(int $cost, int $creditHalere = 5000, bool $active = true): LoyaltyReward
{
    return LoyaltyReward::create([
        'name'                => 'Reward ' . uniqid(),
        'points_cost'         => $cost,
        'reward_type'         => 'credit_czk',
        'reward_value_halere' => $creditHalere,
        'is_active'           => $active,
    ]);
}

// ── service ─────────────────────────────────────────────────────────────────────

it('computes points from an amount using the configured rate', function (): void {
    config(['loyalty.czk_per_point' => 10]);

    expect(pointsSvc()->pointsForAmount(Money::of(100, 'CZK')))->toBe(10)
        ->and(pointsSvc()->pointsForAmount(Money::of(5, 'CZK')))->toBe(0);
});

it('tracks the points balance from the ledger', function (): void {
    $customer = customerUser()->customer;

    expect(pointsSvc()->balance($customer))->toBe(0);

    pointsSvc()->award($customer, 120, 'earn');
    pointsSvc()->award($customer, -20, 'spend');

    expect(pointsSvc()->balance($customer))->toBe(100);
});

it('redeems points for credit', function (): void {
    $customer = customerUser()->customer;
    pointsSvc()->award($customer, 500, 'seed');
    $r = reward(cost: 300, creditHalere: 5000);

    expect(pointsSvc()->redeem($customer, $r))->toBeTrue()
        ->and(pointsSvc()->balance($customer))->toBe(200)
        ->and(app(CreditLedger::class)->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(5000);
});

it('refuses redemption with insufficient points — no change', function (): void {
    $customer = customerUser()->customer;
    pointsSvc()->award($customer, 100, 'seed');
    $r = reward(cost: 300);

    expect(pointsSvc()->redeem($customer, $r))->toBeFalse()
        ->and(pointsSvc()->balance($customer))->toBe(100)
        ->and(app(CreditLedger::class)->getBalance($customer->fresh())->getMinorAmount()->toInt())->toBe(0);
});

it('refuses an inactive reward', function (): void {
    $customer = customerUser()->customer;
    pointsSvc()->award($customer, 500, 'seed');

    expect(pointsSvc()->redeem($customer, reward(cost: 100, active: false)))->toBeFalse()
        ->and(pointsSvc()->balance($customer))->toBe(500);
});

// ── earning on invoice paid ───────────────────────────────────────────────────────

it('awards points when an invoice is paid, matching the configured rate', function (): void {
    config(['loyalty.czk_per_point' => 1]);
    $user    = customerUser();
    $invoice = Invoice::factory()->create(['customer_id' => $user->customer->id]);

    // The listener must award exactly pointsForAmount(total) — the earning contract.
    $expected = pointsSvc()->pointsForAmount($invoice->total);

    (new AwardLoyaltyPointsOnInvoicePaid(pointsSvc()))->handle(new InvoicePaid($invoice, new Payment()));

    expect(pointsSvc()->balance($user->customer->fresh()))->toBe($expected);
});

// ── panel ─────────────────────────────────────────────────────────────────────────

it('shows the points balance and catalog on the loyalty page', function (): void {
    $user = customerUser();
    pointsSvc()->award($user->customer, 250, 'seed');
    reward(cost: 100);

    $this->actingAs($user)->get(route('panel.loyalty.index'))->assertOk()->assertSee('250');
});

it('lets the customer redeem a reward from the panel', function (): void {
    $user = customerUser();
    pointsSvc()->award($user->customer, 500, 'seed');
    $r = reward(cost: 300, creditHalere: 5000);

    $this->actingAs($user)->post(route('panel.loyalty.redeem', $r))->assertRedirect()->assertSessionHasNoErrors();

    expect(pointsSvc()->balance($user->customer->fresh()))->toBe(200);
});

it('errors when redeeming from the panel without enough points', function (): void {
    $user = customerUser();
    pointsSvc()->award($user->customer, 100, 'seed');

    $this->actingAs($user)->post(route('panel.loyalty.redeem', reward(cost: 300)))->assertSessionHasErrors('redeem');
});

// ── admin catalog ──────────────────────────────────────────────────────────────────

it('lets an admin add and remove a catalog reward', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.loyalty-rewards.store'), [
        'name'        => 'Sleva 40 Kč',
        'points_cost' => 200,
        'reward_czk'  => '40',
        'is_active'   => 1,
    ])->assertRedirect();

    $r = LoyaltyReward::where('name', 'Sleva 40 Kč')->first();
    expect($r)->not->toBeNull()->and($r->reward_value_halere)->toBe(4000);

    $this->actingAs($admin)->delete(route('admin.loyalty-rewards.destroy', $r))->assertRedirect();
    expect(LoyaltyReward::find($r->id))->toBeNull();
});
