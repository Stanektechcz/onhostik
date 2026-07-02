<?php

declare(strict_types=1);

use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function makeCode(array $overrides = []): DiscountCode
{
    return DiscountCode::create(array_merge([
        'code'      => 'TEST10',
        'type'      => 'percent',
        'value'     => '10.00',
        'is_active' => true,
    ], $overrides));
}

// ── Admin CRUD ────────────────────────────────────────────────────────────────

it('admin can create a percent discount code', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.discount-codes.store'), [
            'code'        => 'SUMMER20',
            'type'        => 'percent',
            'value'       => '20',
            'description' => 'Letní sleva 20 %',
        ])
        ->assertRedirect();

    $code = DiscountCode::where('code', 'SUMMER20')->firstOrFail();
    expect($code->type)->toBe('percent')
        ->and((float) $code->value)->toBe(20.0)
        ->and($code->is_active)->toBeTrue();
});

it('admin can create a fixed CZK discount code with max uses', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.discount-codes.store'), [
            'type'     => 'fixed',
            'value'    => '100',
            'currency' => 'CZK',
            'max_uses' => '5',
        ])
        ->assertRedirect();

    $code = DiscountCode::latest()->firstOrFail();
    expect($code->type)->toBe('fixed')
        ->and($code->max_uses)->toBe(5)
        ->and($code->currency)->toBe('CZK');
});

it('admin cannot create percent discount over 100', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.discount-codes.store'), [
            'code'  => 'BAD',
            'type'  => 'percent',
            'value' => '150',
        ])
        ->assertSessionHasErrors('value');
});

it('admin can toggle discount code status', function (): void {
    $admin = adminUser();
    $code  = makeCode(['code' => 'TOGGLEME', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.discount-codes.toggle', $code))
        ->assertRedirect();

    expect($code->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->post(route('admin.discount-codes.toggle', $code))
        ->assertRedirect();

    expect($code->fresh()->is_active)->toBeTrue();
});

it('admin can delete a discount code', function (): void {
    $admin = adminUser();
    $code  = makeCode(['code' => 'DELETEME']);

    $this->actingAs($admin)
        ->delete(route('admin.discount-codes.destroy', $code))
        ->assertRedirect();

    expect(DiscountCode::where('code', 'DELETEME')->exists())->toBeFalse();
});

it('customer cannot access discount code admin routes', function (): void {
    $this->actingAs(customerUser())
        ->get(route('admin.discount-codes.index'))
        ->assertForbidden();
});

// ── Customer validation AJAX endpoint ────────────────────────────────────────

it('customer validates a valid percent code', function (): void {
    makeCode(['code' => 'VALID10', 'type' => 'percent', 'value' => '10.00']);

    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.discount.validate'), ['code' => 'VALID10'])
        ->assertOk()
        ->assertJson([
            'valid' => true,
            'code'  => 'VALID10',
            'type'  => 'percent',
        ]);
});

it('customer gets 422 for invalid code', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.discount.validate'), ['code' => 'DOESNOTEXIST'])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);
});

it('customer gets 422 for expired code', function (): void {
    makeCode(['code' => 'EXPIRED', 'expires_at' => now()->subDay()]);

    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.discount.validate'), ['code' => 'EXPIRED'])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);
});

it('customer gets 422 for exhausted code', function (): void {
    makeCode(['code' => 'USED5', 'max_uses' => 5, 'used_count' => 5]);

    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.discount.validate'), ['code' => 'USED5'])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);
});

it('customer cannot reuse a code they already used', function (): void {
    $code = makeCode(['code' => 'ONETIME']);
    $user = customerUser();

    DiscountCodeUsage::create([
        'discount_code_id' => $code->id,
        'customer_id'      => $user->customer->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('panel.discount.validate'), ['code' => 'ONETIME'])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);
});

// ── DiscountCode model logic ──────────────────────────────────────────────────

it('calculateDiscount returns correct percent amount', function (): void {
    $code  = makeCode(['type' => 'percent', 'value' => '15.00']);
    $price = Money::ofMinor(10_000, 'CZK');

    $discount = $code->calculateDiscount($price);

    expect($discount->getMinorAmount()->toInt())->toBe(1_500);
});

it('calculateDiscount returns correct fixed amount', function (): void {
    $code  = makeCode(['type' => 'fixed', 'value' => '50.00', 'currency' => 'CZK']);
    $price = Money::ofMinor(20_000, 'CZK');

    $discount = $code->calculateDiscount($price);

    expect($discount->getMinorAmount()->toInt())->toBe(5_000);
});

it('calculateDiscount never exceeds the full price', function (): void {
    $code  = makeCode(['type' => 'fixed', 'value' => '500.00', 'currency' => 'CZK']);
    $price = Money::ofMinor(1_000, 'CZK');

    $discount = $code->calculateDiscount($price);

    expect($discount->getMinorAmount()->toInt())->toBe(1_000);
});

it('formattedValue returns percent correctly', function (): void {
    $code = makeCode(['type' => 'percent', 'value' => '10.00']);

    expect($code->formattedValue())->toContain('%');
});

it('formattedValue returns fixed amount correctly', function (): void {
    $code = makeCode(['type' => 'fixed', 'value' => '100.00', 'currency' => 'CZK']);

    expect($code->formattedValue())->toContain('CZK');
});
