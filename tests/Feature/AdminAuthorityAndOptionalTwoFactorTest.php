<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Two standing product rules:
 *
 *  1. Two-factor authentication is NEVER mandatory — it is opt-in, and an
 *     admin who has not set it up must still reach the whole admin panel.
 *  2. An admin may perform ANY action or edit. Authorization must never be
 *     what stops them.
 *
 * Business-state rules (cannot edit a sent campaign, cannot refund an
 * uncompleted payment) are a different thing and stay enforced.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── 2FA is optional ───────────────────────────────────────────────────────────

it('does not require two-factor authentication by default', function (): void {
    expect(DB::table('settings')->where('group', 'security')->where('name', 'require_admin_2fa')->value('payload'))
        ->toBeNull();
});

/** An admin who has never set 2FA up — the default for a fresh install. */
function adminWithoutTwoFactor(): \App\Models\User
{
    $admin = adminUser();
    $admin->forceFill(['two_factor_confirmed_at' => null, 'two_factor_secret' => null])->save();

    return $admin->fresh();
}

it('lets an admin without 2FA reach the admin panel', function (): void {
    $admin = adminWithoutTwoFactor();
    expect($admin->two_factor_confirmed_at)->toBeNull();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.services.index'))->assertOk();
});

it('lets a customer without 2FA use the panel', function (): void {
    $user = customerUser();

    $this->actingAs($user)->get(route('panel.dashboard'))->assertOk();
    $this->actingAs($user)->get(route('panel.services.index'))->assertOk();
});

it('only enforces admin 2FA once it is explicitly switched on', function (): void {
    $admin = adminWithoutTwoFactor();

    // Off (the default) → straight through.
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

    DB::table('settings')->updateOrInsert(
        ['group' => 'security', 'name' => 'require_admin_2fa'],
        ['payload' => json_encode(true)],
    );

    // On → redirected to set it up. Opt-in, never the default.
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.account.security'));
});

// ── Admin may do anything ─────────────────────────────────────────────────────

it('grants an admin every ability through the gate', function (string $ability): void {
    expect(Gate::forUser(adminUser())->allows($ability))->toBeTrue();
})->with([
    'access-admin',
    'access-reseller',
    'some-future-ability-nobody-defined-yet',
]);

it('grants an admin every ability on every policy-backed model', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    ['order' => $order] = placeOrder($user);
    $service = Service::factory()->create(['customer_id' => $customer->id]);

    foreach (['view', 'update', 'delete', 'forceDelete'] as $ability) {
        expect(Gate::forUser($admin)->allows($ability, $order))->toBeTrue()
            ->and(Gate::forUser($admin)->allows($ability, $service))->toBeTrue()
            ->and(Gate::forUser($admin)->allows($ability, $customer))->toBeTrue();
    }
});

it('lets an admin act on another customer\'s records', function (): void {
    $customer = customerUser()->customer;
    $service  = Service::factory()->create(['customer_id' => $customer->id]);

    // Belongs to someone else entirely — an admin must still manage it.
    $this->actingAs(adminUser())
        ->get(route('admin.services.show', $service))
        ->assertOk();

    $this->actingAs(adminUser())
        ->put(route('admin.services.update-label', $service), ['label' => 'Přejmenováno adminem'])
        ->assertRedirect();

    expect($service->fresh()->label)->toBe('Přejmenováno adminem');
});

it('still denies a customer another customer\'s records', function (): void {
    // The admin bypass must not leak into ordinary users.
    $service = Service::factory()->create(['customer_id' => customerUser()->customer->id]);

    $this->actingAs(customerUser())
        ->get(route('panel.services.show', $service))
        ->assertForbidden();
});

it('keeps business-state rules in force for admins', function (): void {
    // Authorization is bypassed for admins; data-integrity rules are not.
    $service = Service::factory()->create(['status' => \App\Domains\Provisioning\Enums\ServiceStatus::Terminated]);

    $this->actingAs(adminUser())
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.reprovision', $service))
        ->assertSessionHasErrors('service');
});
