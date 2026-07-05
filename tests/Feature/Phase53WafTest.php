<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Security\Enums\WafRuleType;
use App\Domains\Security\Models\WafEvent;
use App\Domains\Security\Models\WafRule;
use App\Models\User;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Enum helpers ───────────────────────────────────────────────────────────────

it('WafRuleType label and color are defined for all cases', function (): void {
    foreach (WafRuleType::cases() as $type) {
        expect($type->label())->toBeString()->not->toBeEmpty();
        expect($type->color())->toBeIn(['danger', 'success', 'warning', 'info']);
        expect($type->valuePlaceholder())->toBeString()->not->toBeEmpty();
    }
});

it('WafRuleType IpAllow has allow action, others have block', function (): void {
    expect(WafRuleType::IpAllow->value)->toBe('ip_allow');
    expect(WafRuleType::IpBlock->value)->toBe('ip_block');
    expect(WafRuleType::CountryBlock->value)->toBe('country_block');
    expect(WafRuleType::RateLimit->value)->toBe('rate_limit');
});

// ── Model helpers ──────────────────────────────────────────────────────────────

it('WafRule isGlobal returns true when service_id is null', function (): void {
    $rule = new WafRule(['service_id' => null]);
    expect($rule->isGlobal())->toBeTrue();
});

it('WafRule isGlobal returns false when service_id is set', function (): void {
    $rule = new WafRule(['service_id' => 1]);
    expect($rule->isGlobal())->toBeFalse();
});

it('WafEvent has no UPDATED_AT timestamp', function (): void {
    expect(WafEvent::UPDATED_AT)->toBeNull();
});

// ── Panel WAF — index ──────────────────────────────────────────────────────────

it('panel waf index requires auth', function (): void {
    $service = createServiceForTest();
    $this->get(route('panel.waf.index', $service))
         ->assertRedirect(route('login'));
});

it('panel waf index is accessible to owner', function (): void {
    [$user, $service] = createUserAndService();
    $this->actingAs($user)
         ->get(route('panel.waf.index', $service))
         ->assertOk()
         ->assertViewIs('panel.waf.index');
});

it('panel waf index is forbidden for other users', function (): void {
    [, $service] = createUserAndService();
    $other = User::factory()->create();
    $this->actingAs($other)
         ->get(route('panel.waf.index', $service))
         ->assertForbidden();
});

// ── Panel WAF — store ──────────────────────────────────────────────────────────

it('owner can add WAF rule for their service', function (): void {
    [$user, $service] = createUserAndService();
    $this->actingAs($user)
         ->post(route('panel.waf.store', $service), [
             'type'  => 'ip_block',
             'value' => '192.168.1.100',
             'notes' => 'Blocked scanner',
         ])
         ->assertRedirect();

    $this->assertDatabaseHas('waf_rules', [
        'service_id' => $service->id,
        'type'       => 'ip_block',
        'value'      => '192.168.1.100',
        'action'     => 'block',
        'is_active'  => 1,
    ]);
});

it('ip_allow rule gets action=allow', function (): void {
    [$user, $service] = createUserAndService();
    $this->actingAs($user)
         ->post(route('panel.waf.store', $service), [
             'type'  => 'ip_allow',
             'value' => '10.0.0.0/8',
         ])
         ->assertRedirect();

    $this->assertDatabaseHas('waf_rules', [
        'service_id' => $service->id,
        'action'     => 'allow',
    ]);
});

it('store validates type must be valid enum value', function (): void {
    [$user, $service] = createUserAndService();
    $this->actingAs($user)
         ->post(route('panel.waf.store', $service), [
             'type'  => 'invalid_type',
             'value' => '1.2.3.4',
         ])
         ->assertSessionHasErrors('type');
});

it('store requires value field', function (): void {
    [$user, $service] = createUserAndService();
    $this->actingAs($user)
         ->post(route('panel.waf.store', $service), [
             'type'  => 'ip_block',
             'value' => '',
         ])
         ->assertSessionHasErrors('value');
});

// ── Panel WAF — destroy ────────────────────────────────────────────────────────

it('owner can delete their WAF rule', function (): void {
    [$user, $service] = createUserAndService();
    $rule = WafRule::create([
        'service_id' => $service->id,
        'type'       => WafRuleType::IpBlock,
        'value'      => '1.2.3.4',
        'action'     => 'block',
        'is_active'  => true,
    ]);

    $this->actingAs($user)
         ->delete(route('panel.waf.destroy', [$service, $rule]))
         ->assertRedirect();

    $this->assertDatabaseMissing('waf_rules', ['id' => $rule->id]);
});

it('user cannot delete WAF rule belonging to another service', function (): void {
    [$user, $service] = createUserAndService();
    [, $otherService] = createUserAndService();
    $rule = WafRule::create([
        'service_id' => $otherService->id,
        'type'       => WafRuleType::IpBlock,
        'value'      => '5.6.7.8',
        'action'     => 'block',
        'is_active'  => true,
    ]);

    $this->actingAs($user)
         ->delete(route('panel.waf.destroy', [$service, $rule]))
         ->assertForbidden();

    $this->assertDatabaseHas('waf_rules', ['id' => $rule->id]);
});

// ── Panel WAF — toggle ─────────────────────────────────────────────────────────

it('owner can toggle WAF rule active state', function (): void {
    [$user, $service] = createUserAndService();
    $rule = WafRule::create([
        'service_id' => $service->id,
        'type'       => WafRuleType::IpBlock,
        'value'      => '9.8.7.6',
        'action'     => 'block',
        'is_active'  => true,
    ]);

    $this->actingAs($user)
         ->patch(route('panel.waf.toggle', [$service, $rule]))
         ->assertRedirect();

    expect($rule->fresh()->is_active)->toBeFalse();
});

// ── Admin WAF ──────────────────────────────────────────────────────────────────

it('admin waf index is accessible to admin', function (): void {
    $admin = createAdmin();
    $this->actingAs($admin)
         ->get(route('admin.waf.index'))
         ->assertOk()
         ->assertViewIs('admin.waf.index');
});

it('admin waf index is forbidden for regular users', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)
         ->get(route('admin.waf.index'))
         ->assertForbidden();
});

it('admin can create global WAF rule', function (): void {
    $admin = createAdmin();
    $this->actingAs($admin)
         ->post(route('admin.waf.store'), [
             'type'  => 'country_block',
             'value' => 'RU',
             'notes' => 'Geoblocking',
         ])
         ->assertRedirect();

    $this->assertDatabaseHas('waf_rules', [
        'service_id' => null,
        'type'       => 'country_block',
        'value'      => 'RU',
        'action'     => 'block',
    ]);
});

it('admin can delete any WAF rule', function (): void {
    $admin = createAdmin();
    $rule = WafRule::create([
        'service_id' => null,
        'type'       => WafRuleType::CountryBlock,
        'value'      => 'CN',
        'action'     => 'block',
        'is_active'  => true,
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.waf.destroy', $rule))
         ->assertRedirect();

    $this->assertDatabaseMissing('waf_rules', ['id' => $rule->id]);
});

it('admin can toggle any WAF rule', function (): void {
    $admin = createAdmin();
    $rule = WafRule::create([
        'service_id' => null,
        'type'       => WafRuleType::RateLimit,
        'value'      => '200',
        'action'     => 'throttle',
        'is_active'  => true,
    ]);

    $this->actingAs($admin)
         ->patch(route('admin.waf.toggle', $rule))
         ->assertRedirect();

    expect($rule->fresh()->is_active)->toBeFalse();
});

// ── WAF Events ─────────────────────────────────────────────────────────────────

it('WafEvent can be persisted without updated_at', function (): void {
    [, $service] = createUserAndService();

    WafEvent::create([
        'service_id'  => $service->id,
        'ip_address'  => '10.0.0.1',
        'action_taken' => 'block',
        'blocked_at'  => now(),
    ]);

    $this->assertDatabaseHas('waf_events', [
        'service_id'  => $service->id,
        'ip_address'  => '10.0.0.1',
        'action_taken' => 'block',
    ]);
});

// ── Helpers ────────────────────────────────────────────────────────────────────

function createServiceForTest(): Service
{
    $customer = Customer::factory()->create();
    $product  = \App\Domains\Products\Models\Product::first();

    return Service::factory()->create([
        'customer_id' => $customer->id,
        'product_id'  => $product->id,
        'status'      => ServiceStatus::Active,
    ]);
}

function createUserAndService(): array
{
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $product  = \App\Domains\Products\Models\Product::first();

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'product_id'  => $product->id,
        'status'      => ServiceStatus::Active,
    ]);

    return [$user, $service];
}

function createAdmin(): User
{
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');
    return $admin;
}
