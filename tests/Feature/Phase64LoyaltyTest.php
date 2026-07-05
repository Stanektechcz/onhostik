<?php

declare(strict_types=1);

use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Customer\Models\Customer;
use App\Domains\Loyalty\Models\CustomerLoyaltyReward;
use App\Domains\Loyalty\Models\LoyaltyMilestone;
use App\Domains\Loyalty\Services\LoyaltyService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── LoyaltyMilestone model ────────────────────────────────────────────────────

it('LoyaltyMilestone triggerLabel formats account_age_days', function (): void {
    $m = new LoyaltyMilestone(['trigger_type' => 'account_age_days', 'trigger_value' => 365]);
    expect($m->triggerLabel())->toContain('365');
});

it('LoyaltyMilestone triggerLabel formats order_count', function (): void {
    $m = new LoyaltyMilestone(['trigger_type' => 'order_count', 'trigger_value' => 10]);
    expect($m->triggerLabel())->toContain('10');
});

it('LoyaltyMilestone triggerLabel formats total_spent_czk in CZK', function (): void {
    $m = new LoyaltyMilestone(['trigger_type' => 'total_spent_czk', 'trigger_value' => 500000]);
    expect($m->triggerLabel())->toContain('5');
});

it('LoyaltyMilestone rewardLabel formats credit_czk', function (): void {
    $m = new LoyaltyMilestone(['reward_type' => 'credit_czk', 'reward_value' => 10000]);
    expect($m->rewardLabel())->toContain('100');
});

it('LoyaltyMilestone rewardLabel formats discount_percent', function (): void {
    $m = new LoyaltyMilestone(['reward_type' => 'discount_percent', 'reward_value' => 10]);
    expect($m->rewardLabel())->toContain('10');
});

// ── CustomerLoyaltyReward model ───────────────────────────────────────────────

it('CustomerLoyaltyReward has no updated_at column', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $milestone = LoyaltyMilestone::create([
        'name'          => 'Test',
        'slug'          => 'test-no-updated',
        'trigger_type'  => 'order_count',
        'trigger_value' => 1,
        'reward_type'   => 'badge',
        'reward_value'  => 1,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $reward = CustomerLoyaltyReward::create([
        'customer_id'          => $customer->id,
        'loyalty_milestone_id' => $milestone->id,
        'awarded_at'           => now(),
    ]);

    expect($reward->updated_at)->toBeNull();
});

// ── Customer relation ─────────────────────────────────────────────────────────

it('Customer has loyaltyRewards HasMany relation', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $milestone = LoyaltyMilestone::create([
        'name'          => 'First Order',
        'slug'          => 'first-order',
        'trigger_type'  => 'order_count',
        'trigger_value' => 1,
        'reward_type'   => 'credit_czk',
        'reward_value'  => 5000,
        'is_active'     => true,
        'sort_order'    => 1,
    ]);
    CustomerLoyaltyReward::create([
        'customer_id'          => $customer->id,
        'loyalty_milestone_id' => $milestone->id,
        'awarded_at'           => now(),
    ]);

    expect($customer->loyaltyRewards()->count())->toBe(1);
});

// ── LoyaltyService: checkAndAward ─────────────────────────────────────────────

it('LoyaltyService awards order_count milestone when met', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    // Create a paid order directly in the DB
    \DB::table('orders')->insert([
        'uuid'        => (string) \Illuminate\Support\Str::uuid(),
        'customer_id' => $customer->id,
        'status'       => 'active',
        'currency'     => 'CZK',
        'subtotal'     => 10000,
        'tax_amount'   => 2100,
        'total'        => 12100,
        'vat_scenario' => 'cz_b2c',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $milestone = LoyaltyMilestone::create([
        'name'          => '1 Order',
        'slug'          => 'one-order',
        'trigger_type'  => 'order_count',
        'trigger_value' => 1,
        'reward_type'   => 'badge',
        'reward_value'  => 1,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $service = app(LoyaltyService::class);
    $count   = $service->checkAndAward($customer);

    expect($count)->toBe(1);
    expect(CustomerLoyaltyReward::where('customer_id', $customer->id)
        ->where('loyalty_milestone_id', $milestone->id)
        ->exists())->toBeTrue();
});

it('LoyaltyService does not award if condition not met', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    LoyaltyMilestone::create([
        'name'          => '100 Orders',
        'slug'          => 'hundred-orders',
        'trigger_type'  => 'order_count',
        'trigger_value' => 100,
        'reward_type'   => 'badge',
        'reward_value'  => 2,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $service = app(LoyaltyService::class);
    $count   = $service->checkAndAward($customer);

    expect($count)->toBe(0);
});

it('LoyaltyService does not double-award the same milestone', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    \DB::table('orders')->insert([
        'uuid'        => (string) \Illuminate\Support\Str::uuid(),
        'customer_id' => $customer->id,
        'status'       => 'active',
        'currency'     => 'CZK',
        'subtotal'     => 10000,
        'tax_amount'   => 2100,
        'total'        => 12100,
        'vat_scenario' => 'cz_b2c',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $milestone = LoyaltyMilestone::create([
        'name'          => '1 Order Again',
        'slug'          => 'one-order-again',
        'trigger_type'  => 'order_count',
        'trigger_value' => 1,
        'reward_type'   => 'badge',
        'reward_value'  => 1,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $service = app(LoyaltyService::class);
    $service->checkAndAward($customer);
    $count2 = $service->checkAndAward($customer);

    expect($count2)->toBe(0);
    expect(CustomerLoyaltyReward::where('customer_id', $customer->id)->count())->toBe(1);
});

it('LoyaltyService credit_czk reward increments customer credit balance', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    \DB::table('orders')->insert([
        'uuid'        => (string) \Illuminate\Support\Str::uuid(),
        'customer_id' => $customer->id,
        'status'       => 'active',
        'currency'     => 'CZK',
        'subtotal'     => 10000,
        'tax_amount'   => 2100,
        'total'        => 12100,
        'vat_scenario' => 'cz_b2c',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    LoyaltyMilestone::create([
        'name'          => 'Credit reward',
        'slug'          => 'credit-reward',
        'trigger_type'  => 'order_count',
        'trigger_value' => 1,
        'reward_type'   => 'credit_czk',
        'reward_value'  => 5000,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $service = app(LoyaltyService::class);
    $service->checkAndAward($customer);

    expect(CreditTransaction::where('customer_id', $customer->id)->exists())->toBeTrue();
});

it('LoyaltyService skips inactive milestones', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    \DB::table('orders')->insert([
        'uuid'        => (string) \Illuminate\Support\Str::uuid(),
        'customer_id' => $customer->id,
        'status'       => 'active',
        'currency'     => 'CZK',
        'subtotal'     => 10000,
        'tax_amount'   => 2100,
        'total'        => 12100,
        'vat_scenario' => 'cz_b2c',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    LoyaltyMilestone::create([
        'name'          => 'Inactive',
        'slug'          => 'inactive-milestone',
        'trigger_type'  => 'order_count',
        'trigger_value' => 1,
        'reward_type'   => 'badge',
        'reward_value'  => 1,
        'is_active'     => false,
        'sort_order'    => 0,
    ]);

    $service = app(LoyaltyService::class);
    $count   = $service->checkAndAward($customer);

    expect($count)->toBe(0);
});

// ── LoyaltyService: nextMilestone ─────────────────────────────────────────────

it('LoyaltyService nextMilestone returns next unearned milestone', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $milestone = LoyaltyMilestone::create([
        'name'          => 'Next',
        'slug'          => 'next-milestone',
        'trigger_type'  => 'order_count',
        'trigger_value' => 5,
        'reward_type'   => 'badge',
        'reward_value'  => 1,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $service = app(LoyaltyService::class);
    $result  = $service->nextMilestone($customer);

    expect($result)->not->toBeNull();
    expect($result['milestone']->id)->toBe($milestone->id);
    expect($result['target'])->toBe(5);
    expect($result['percent'])->toBeGreaterThanOrEqual(0);
});

it('LoyaltyService nextMilestone returns null when all earned', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $milestone = LoyaltyMilestone::create([
        'name'          => 'Only one',
        'slug'          => 'only-one',
        'trigger_type'  => 'order_count',
        'trigger_value' => 1,
        'reward_type'   => 'badge',
        'reward_value'  => 1,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);
    CustomerLoyaltyReward::create([
        'customer_id'          => $customer->id,
        'loyalty_milestone_id' => $milestone->id,
        'awarded_at'           => now(),
    ]);

    $service = app(LoyaltyService::class);
    expect($service->nextMilestone($customer))->toBeNull();
});

// ── Admin routes ───────────────────────────────────────────────────────────────

it('admin can list loyalty milestones', function (): void {
    $admin = adminUser();
    LoyaltyMilestone::create([
        'name'          => 'Veteran',
        'slug'          => 'veteran',
        'trigger_type'  => 'account_age_days',
        'trigger_value' => 365,
        'reward_type'   => 'credit_czk',
        'reward_value'  => 10000,
        'is_active'     => true,
        'sort_order'    => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loyalty.index'))
        ->assertOk()
        ->assertSee('Veteran');
});

it('admin can create a loyalty milestone', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.loyalty.store'), [
            'name'          => 'First Year',
            'slug'          => 'first-year',
            'trigger_type'  => 'account_age_days',
            'trigger_value' => 365,
            'reward_type'   => 'credit_czk',
            'reward_value'  => 20000,
            'is_active'     => '1',
            'sort_order'    => 1,
        ])
        ->assertRedirect(route('admin.loyalty.index'));

    expect(LoyaltyMilestone::where('slug', 'first-year')->exists())->toBeTrue();
});

it('admin can update a loyalty milestone', function (): void {
    $admin     = adminUser();
    $milestone = LoyaltyMilestone::create([
        'name'          => 'Old Name',
        'slug'          => 'old-name-loy',
        'trigger_type'  => 'order_count',
        'trigger_value' => 5,
        'reward_type'   => 'badge',
        'reward_value'  => 1,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.loyalty.update', $milestone), [
            'name'          => 'New Name',
            'slug'          => 'old-name-loy',
            'trigger_type'  => 'order_count',
            'trigger_value' => 5,
            'reward_type'   => 'badge',
            'reward_value'  => 1,
        ])
        ->assertRedirect(route('admin.loyalty.index'));

    expect($milestone->fresh()->name)->toBe('New Name');
});

it('admin can delete a loyalty milestone', function (): void {
    $admin     = adminUser();
    $milestone = LoyaltyMilestone::create([
        'name'          => 'Delete Me',
        'slug'          => 'delete-me-loy',
        'trigger_type'  => 'order_count',
        'trigger_value' => 1,
        'reward_type'   => 'badge',
        'reward_value'  => 1,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.loyalty.destroy', $milestone))
        ->assertRedirect(route('admin.loyalty.index'));

    expect(LoyaltyMilestone::find($milestone->id))->toBeNull();
});

it('non-admin cannot access loyalty admin', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.loyalty.index'))
        ->assertStatus(403);
});

it('admin can trigger manual check for a customer', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    $this->actingAs($admin)
        ->post(route('admin.loyalty.check'), ['customer_id' => $customer->id])
        ->assertRedirect();
});

// ── Panel routes ───────────────────────────────────────────────────────────────

it('customer can view their loyalty page', function (): void {
    $user = customerUser();

    LoyaltyMilestone::create([
        'name'          => 'Big Spender',
        'slug'          => 'big-spender',
        'trigger_type'  => 'total_spent_czk',
        'trigger_value' => 1000000,
        'reward_type'   => 'discount_percent',
        'reward_value'  => 5,
        'is_active'     => true,
        'sort_order'    => 0,
    ]);

    $this->actingAs($user)
        ->get(route('panel.loyalty.index'))
        ->assertOk()
        ->assertSee('Věrnostní program');
});

it('unauthenticated user cannot view loyalty page', function (): void {
    $this->get(route('panel.loyalty.index'))
        ->assertRedirect();
});
