<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Admin editing powers: order line items (G95), roles and permissions (G96),
 * and customer account recovery (G97).
 *
 * Authorization never blocks an admin. Data-integrity rules still do — those
 * protect the books, not permissions.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── G95: order line items ─────────────────────────────────────────────────────

it('lets an admin add a line item and recomputes the order', function (): void {
    ['order' => $order] = placeOrder(customerUser());
    $before = $order->total->getMinorAmount()->toInt();
    $plan   = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs(adminUser())
        ->post(route('admin.orders.items.store', $order), ['pricing_plan_id' => $plan->id, 'quantity' => 1])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($order->fresh()->items)->toHaveCount(2)
        ->and($order->fresh()->total->getMinorAmount()->toInt())->toBeGreaterThan($before);
});

it('lets an admin reprice a line and recomputes the order', function (): void {
    ['order' => $order] = placeOrder(customerUser());
    $item = $order->items->first();

    $this->actingAs(adminUser())
        ->put(route('admin.orders.items.update', [$order, $item]), ['quantity' => 2, 'unit_price' => 100])
        ->assertRedirect();

    // 2 × 100 = 200 major units.
    expect($order->fresh()->subtotal->getMinorAmount()->toInt())->toBe(20000);
});

it('keeps subtotal plus tax equal to total after an edit', function (): void {
    ['order' => $order] = placeOrder(customerUser());
    $item = $order->items->first();

    $this->actingAs(adminUser())
        ->put(route('admin.orders.items.update', [$order, $item]), ['quantity' => 3, 'unit_price' => 250]);

    $fresh = $order->fresh();

    expect($fresh->subtotal->getMinorAmount()->toInt() + $fresh->tax_amount->getMinorAmount()->toInt())
        ->toBe($fresh->total->getMinorAmount()->toInt());
});

it('refuses to remove the last line item', function (): void {
    ['order' => $order] = placeOrder(customerUser());
    $item = $order->items->first();

    $this->actingAs(adminUser())
        ->from(route('admin.orders.show', $order))
        ->delete(route('admin.orders.items.destroy', [$order, $item]))
        ->assertSessionHasErrors('items');

    expect($order->fresh()->items)->toHaveCount(1);
});

it('refuses to remove a line that already has a provisioned service', function (): void {
    ['order' => $order] = placeOrder(customerUser());
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    // Add a second line so the "last item" guard is not what trips.
    $this->actingAs(adminUser())
        ->post(route('admin.orders.items.store', $order), ['pricing_plan_id' => $plan->id, 'quantity' => 1]);

    $item = $order->fresh()->items->first();
    Service::factory()->create(['order_item_id' => $item->id]);

    $this->actingAs(adminUser())
        ->from(route('admin.orders.show', $order))
        ->delete(route('admin.orders.items.destroy', [$order, $item]))
        ->assertSessionHasErrors('items');
});

it('refuses to edit the lines of a paid order', function (): void {
    ['order' => $order] = placeOrder(customerUser());
    $order->update(['paid_at' => now(), 'status' => OrderStatus::Processing]);
    $item = $order->items->first();

    // Changing an invoiced amount would desync the accounting — use a credit note.
    $this->actingAs(adminUser())
        ->from(route('admin.orders.show', $order))
        ->put(route('admin.orders.items.update', [$order, $item]), ['quantity' => 9, 'unit_price' => 1])
        ->assertSessionHasErrors('items');
});

it('forbids a customer from editing order lines', function (): void {
    ['order' => $order] = placeOrder(customerUser());
    $item = $order->items->first();

    $this->actingAs(customerUser())
        ->put(route('admin.orders.items.update', [$order, $item]), ['quantity' => 5, 'unit_price' => 0])
        ->assertForbidden();
});

// ── G96: roles and permissions ────────────────────────────────────────────────

it('lets an admin create and delete a role', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.roles.store'), ['name' => 'support-agent'])
        ->assertRedirect();

    expect(Role::where('name', 'support-agent')->exists())->toBeTrue();

    $role = Role::where('name', 'support-agent')->firstOrFail();

    $this->actingAs(adminUser())->delete(route('admin.roles.destroy', $role))->assertRedirect();

    expect(Role::where('name', 'support-agent')->exists())->toBeFalse();
});

it('never lets the admin role be deleted', function (): void {
    $role = Role::findOrCreate('admin', 'web');

    $this->actingAs(adminUser())
        ->from(route('admin.roles-permission'))
        ->delete(route('admin.roles.destroy', $role))
        ->assertSessionHasErrors('role');

    expect(Role::where('name', 'admin')->exists())->toBeTrue();
});

it('syncs permissions onto a role', function (): void {
    $role = Role::findOrCreate('billing-clerk', 'web');
    Permission::findOrCreate('invoices.view', 'web');

    $this->actingAs(adminUser())
        ->post(route('admin.roles.permissions', $role), ['permissions' => ['invoices.view']])
        ->assertRedirect();

    expect($role->fresh()->hasPermissionTo('invoices.view'))->toBeTrue();
});

it('assigns and revokes a role on a user', function (): void {
    Role::findOrCreate('reseller', 'web');
    $user = customerUser();

    $this->actingAs(adminUser())
        ->post(route('admin.roles.assign', $user), ['role' => 'reseller', 'action' => 'assign'])
        ->assertRedirect();
    expect($user->fresh()->hasRole('reseller'))->toBeTrue();

    $this->actingAs(adminUser())
        ->post(route('admin.roles.assign', $user), ['role' => 'reseller', 'action' => 'revoke'])
        ->assertRedirect();
    expect($user->fresh()->hasRole('reseller'))->toBeFalse();
});

it('stops an admin removing their own admin role', function (): void {
    $admin = adminUser();

    // Otherwise the last admin could lock everyone out of the system.
    $this->actingAs($admin)
        ->from(route('admin.roles-permission'))
        ->post(route('admin.roles.assign', $admin), ['role' => 'admin', 'action' => 'revoke'])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

// ── G97: customer account recovery ────────────────────────────────────────────

it('sends a password reset link instead of setting a password', function (): void {
    Notification::fake();
    $user = customerUser();

    $this->actingAs(adminUser())
        ->post(route('admin.users.password-reset', $user))
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class);
});

it('clears a lost 2FA setup and demands a reason', function (): void {
    $user = customerUser();
    $user->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();

    // No reason → refused; lowering account protection must be justified.
    $this->actingAs(adminUser())
        ->from(route('admin.customers.index'))
        ->post(route('admin.users.reset-2fa', $user), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->actingAs(adminUser())
        ->post(route('admin.users.reset-2fa', $user), ['reason' => 'Zákazník ztratil telefon i kódy'])
        ->assertRedirect();

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

it('records who reset a customer 2FA and why', function (): void {
    $user = customerUser();
    $user->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs(adminUser())
        ->post(route('admin.users.reset-2fa', $user), ['reason' => 'Ztracené zařízení']);

    $entry = \Spatie\Activitylog\Models\Activity::where('description', 'user.two_factor_reset_by_admin')->firstOrFail();

    expect($entry->properties['reason'] ?? null)->toBe('Ztracené zařízení')
        ->and($entry->causer_id)->not->toBeNull();
});

it('revokes every session of a user', function (): void {
    $user = customerUser();
    $token = $user->remember_token;

    $this->actingAs(adminUser())
        ->post(route('admin.users.logout-everywhere', $user))
        ->assertRedirect();

    expect($user->fresh()->remember_token)->not->toBe($token);
});

it('forbids a customer from using the account recovery actions', function (): void {
    $victim = customerUser();

    $this->actingAs(customerUser())
        ->post(route('admin.users.password-reset', $victim))
        ->assertForbidden();

    $this->actingAs(customerUser())
        ->post(route('admin.users.reset-2fa', $victim), ['reason' => 'chci se dostat dovnitr'])
        ->assertForbidden();
});
