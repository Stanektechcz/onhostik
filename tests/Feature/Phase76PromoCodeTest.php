<?php

declare(strict_types=1);

use App\Domains\Billing\Services\PromoCodeService;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── DiscountCode model ─────────────────────────────────────────────────────────

it('isUsableByCustomer returns true when no per-customer limit', function (): void {
    $code = DiscountCode::create([
        'code'       => 'NOLIMIT',
        'type'       => 'percent',
        'value'      => 10,
        'is_active'  => true,
        'max_uses_per_customer' => null,
    ]);

    expect($code->isUsableByCustomer(999))->toBeTrue();
});

it('isUsableByCustomer returns false when customer exceeded limit', function (): void {
    $customer = customerUser()->customer;
    $code = DiscountCode::create([
        'code'                  => 'LIMITED1',
        'type'                  => 'percent',
        'value'                 => 10,
        'is_active'             => true,
        'max_uses_per_customer' => 1,
    ]);

    DiscountCodeUsage::create([
        'discount_code_id' => $code->id,
        'customer_id'      => $customer->id,
        'order_id'         => null,
        'saved_haler'      => 0,
    ]);

    expect($code->isUsableByCustomer($customer->id))->toBeFalse();
});

it('isUsableByCustomer returns true when under per-customer limit', function (): void {
    $customer = customerUser()->customer;
    $code = DiscountCode::create([
        'code'                  => 'MULTI2',
        'type'                  => 'percent',
        'value'                 => 5,
        'is_active'             => true,
        'max_uses_per_customer' => 3,
    ]);

    DiscountCodeUsage::create([
        'discount_code_id' => $code->id,
        'customer_id'      => $customer->id,
        'order_id'         => null,
        'saved_haler'      => 0,
    ]);

    expect($code->isUsableByCustomer($customer->id))->toBeTrue();
});

it('isMinOrderMet returns true when above threshold', function (): void {
    $code = DiscountCode::create([
        'code'            => 'MINORDER',
        'type'            => 'percent',
        'value'           => 10,
        'is_active'       => true,
        'min_order_haler' => 5000,
    ]);

    expect($code->isMinOrderMet(5000))->toBeTrue()
        ->and($code->isMinOrderMet(10000))->toBeTrue();
});

it('isMinOrderMet returns false when below threshold', function (): void {
    $code = DiscountCode::create([
        'code'            => 'MINORDER2',
        'type'            => 'percent',
        'value'           => 10,
        'is_active'       => true,
        'min_order_haler' => 5000,
    ]);

    expect($code->isMinOrderMet(4999))->toBeFalse();
});

it('usageStats returns correct structure', function (): void {
    $code = DiscountCode::create([
        'code'      => 'STATS',
        'type'      => 'percent',
        'value'     => 10,
        'is_active' => true,
    ]);

    $stats = $code->usageStats();

    expect($stats)->toHaveKey('used_count')
        ->and($stats)->toHaveKey('max_uses')
        ->and($stats)->toHaveKey('unique_customers')
        ->and($stats)->toHaveKey('total_saved_haler');
});

// ── PromoCodeService ───────────────────────────────────────────────────────────

it('validate returns valid for a valid code', function (): void {
    $customer = customerUser()->customer;
    DiscountCode::create([
        'code'      => 'VALID10',
        'type'      => 'percent',
        'value'     => 10,
        'is_active' => true,
    ]);

    $result = app(PromoCodeService::class)->validate('VALID10', $customer->id, 10000);

    expect($result['valid'])->toBeTrue()
        ->and($result['reason'])->toBeNull()
        ->and($result['code'])->not->toBeNull();
});

it('validate returns invalid for non-existent code', function (): void {
    $result = app(PromoCodeService::class)->validate('DOESNOTEXIST', 1, 10000);

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toContain('neexistuje');
});

it('validate returns invalid for inactive code', function (): void {
    $customer = customerUser()->customer;
    DiscountCode::create([
        'code'      => 'INACTIVE',
        'type'      => 'percent',
        'value'     => 10,
        'is_active' => false,
    ]);

    $result = app(PromoCodeService::class)->validate('INACTIVE', $customer->id, 10000);

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toContain('neaktivní');
});

it('validate returns invalid when customer exceeded per-customer limit', function (): void {
    $customer = customerUser()->customer;
    $code = DiscountCode::create([
        'code'                  => 'USED1',
        'type'                  => 'percent',
        'value'                 => 10,
        'is_active'             => true,
        'max_uses_per_customer' => 1,
    ]);
    DiscountCodeUsage::create([
        'discount_code_id' => $code->id,
        'customer_id'      => $customer->id,
        'order_id'         => null,
        'saved_haler'      => 0,
    ]);

    $result = app(PromoCodeService::class)->validate('USED1', $customer->id, 10000);

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toContain('využili');
});

it('validate returns invalid when min order not met', function (): void {
    $customer = customerUser()->customer;
    DiscountCode::create([
        'code'            => 'MINORD',
        'type'            => 'percent',
        'value'           => 10,
        'is_active'       => true,
        'min_order_haler' => 10000,
    ]);

    $result = app(PromoCodeService::class)->validate('MINORD', $customer->id, 5000);

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toContain('Minimální');
});

it('recordUsage creates a usage record and increments counters', function (): void {
    $customer = customerUser()->customer;
    $code = DiscountCode::create([
        'code'      => 'RECORD1',
        'type'      => 'percent',
        'value'     => 10,
        'is_active' => true,
    ]);

    app(PromoCodeService::class)->recordUsage($code, $customer->id, null, 500);

    expect(DiscountCodeUsage::where('discount_code_id', $code->id)->count())->toBe(1);
    expect($code->fresh()->used_count)->toBe(1);
    expect($code->fresh()->total_saved_haler)->toBe(500);
});

it('recordUsage accumulates total_saved_haler', function (): void {
    $c1 = customerUser()->customer;
    $c2 = customerUser()->customer;
    $code = DiscountCode::create([
        'code'                  => 'ACCUM',
        'type'                  => 'percent',
        'value'                 => 10,
        'is_active'             => true,
        'max_uses_per_customer' => 2,
    ]);

    $svc = app(PromoCodeService::class);
    $svc->recordUsage($code, $c1->id, null, 1000);
    $svc->recordUsage($code, $c2->id, null, 2000);

    expect($code->fresh()->total_saved_haler)->toBe(3000);
});

it('stats returns required keys', function (): void {
    $stats = app(PromoCodeService::class)->stats();

    expect($stats)->toHaveKey('topCodes')
        ->and($stats)->toHaveKey('expiringSoon')
        ->and($stats)->toHaveKey('exhausted');
});

it('stats expiringSoon includes codes expiring within 7 days', function (): void {
    DiscountCode::create([
        'code'       => 'EXPIRING',
        'type'       => 'percent',
        'value'      => 5,
        'is_active'  => true,
        'expires_at' => now()->addDays(3),
    ]);

    $stats = app(PromoCodeService::class)->stats();

    $found = $stats['expiringSoon']->firstWhere('code', 'EXPIRING');
    expect($found)->not->toBeNull();
});

// ── Admin routes ───────────────────────────────────────────────────────────────

it('admin discount codes index loads', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.discount-codes.index'))
        ->assertOk()
        ->assertSee('Slevové kódy');
});

it('admin can create code with per-customer limit and min order', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.discount-codes.store'), [
            'type'                  => 'percent',
            'value'                 => 15,
            'max_uses_per_customer' => 2,
            'min_order_haler'       => 5000,
        ])
        ->assertRedirect();

    $code = DiscountCode::latest()->first();
    expect($code->max_uses_per_customer)->toBe(2)
        ->and($code->min_order_haler)->toBe(5000);
});

it('admin can view discount code detail page', function (): void {
    $admin = adminUser();
    $code = DiscountCode::create([
        'code'      => 'SHOWTEST',
        'type'      => 'percent',
        'value'     => 10,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.discount-codes.show', $code))
        ->assertOk()
        ->assertSee('SHOWTEST')
        ->assertSee('Celkem použití');
});

it('non-admin cannot access discount codes', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.discount-codes.index'))
        ->assertStatus(403);
});
