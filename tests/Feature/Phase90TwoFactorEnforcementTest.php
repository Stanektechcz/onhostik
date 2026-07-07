<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/**
 * Helper: enable / disable the require_customer_2fa setting.
 */
function setRequireCustomer2fa(bool $value): void
{
    DB::table('settings')->updateOrInsert(
        ['group' => 'security', 'name' => 'require_customer_2fa'],
        ['payload' => json_encode($value), 'created_at' => now(), 'updated_at' => now()],
    );
}

// ── Middleware OFF (default) ───────────────────────────────────────────────────

it('customers without 2FA can access panel when enforcement is off', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk();
});

// ── Middleware ON ──────────────────────────────────────────────────────────────

it('customer without 2FA is redirected to security page when enforcement is on', function (): void {
    setRequireCustomer2fa(true);

    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertRedirect(route('panel.account.security'));
});

it('customer with confirmed 2FA can access panel when enforcement is on', function (): void {
    setRequireCustomer2fa(true);

    $user = customerUser();
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk();
});

it('security page itself is accessible even without 2FA when enforcement on', function (): void {
    setRequireCustomer2fa(true);

    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.account.security'))
        ->assertOk();
});

it('admin with 2FA is not blocked by customer 2FA enforcement', function (): void {
    setRequireCustomer2fa(true);

    $admin = adminUser();
    // Admin has 2FA confirmed — satisfies RequireAdminTwoFactor
    $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

    // Admin accesses admin route — RequireCustomerTwoFactor passes them (isAdmin check)
    $this->actingAs($admin)
        ->get(route('admin.audit-trail.index'))
        ->assertOk();
});

// ── Admin security settings page ──────────────────────────────────────────────

it('admin can view security settings page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.security-settings.index'))
        ->assertOk()
        ->assertSee('Nastavení zabezpečení');
});

it('non-admin cannot view security settings page', function (): void {
    $user = customerUser();
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    setRequireCustomer2fa(true);

    $this->actingAs($user)
        ->get(route('admin.security-settings.index'))
        ->assertStatus(403);
});

it('admin can enable require_customer_2fa setting', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.security-settings.update'), ['require_customer_2fa' => '1'])
        ->assertRedirect(route('admin.security-settings.index'));

    $row = DB::table('settings')
        ->where('group', 'security')
        ->where('name', 'require_customer_2fa')
        ->first();

    expect(json_decode($row->payload, true))->toBeTrue();
});

it('admin can disable require_customer_2fa setting', function (): void {
    $admin = adminUser();

    setRequireCustomer2fa(true);

    $this->actingAs($admin)
        ->put(route('admin.security-settings.update'), [])
        ->assertRedirect(route('admin.security-settings.index'));

    $row = DB::table('settings')
        ->where('group', 'security')
        ->where('name', 'require_customer_2fa')
        ->first();

    expect(json_decode($row->payload, true))->toBeFalse();
});

it('redirect message warns about mandatory 2FA setup', function (): void {
    setRequireCustomer2fa(true);

    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertRedirect();

    $this->followRedirects($response)
        ->assertSee('dvoufázové');
});
