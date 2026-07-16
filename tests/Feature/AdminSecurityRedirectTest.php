<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Admin 2FA enforcement is OPT-IN (security.require_admin_2fa, default off)
 * so a fresh install reaches the admin panel without a lock-out loop. When
 * enabled, admins without confirmed 2FA are redirected to the admin-scoped
 * security page (admin.account.security), which lives OUTSIDE the gate and
 * never redirects onto itself.
 */

function adminWithout2fa(): User
{
    Role::findOrCreate('admin', 'web');
    $user = User::factory()->create(['two_factor_confirmed_at' => null]);
    $user->assignRole('admin');

    return $user;
}

function setRequireAdmin2fa(bool $on): void
{
    DB::table('settings')->updateOrInsert(
        ['group' => 'security', 'name' => 'require_admin_2fa'],
        ['payload' => json_encode($on), 'updated_at' => now(), 'created_at' => now()],
    );
}

it('admin without 2FA reaches the dashboard when enforcement is off (default)', function (): void {
    $admin = adminWithout2fa();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('admin without 2FA is redirected once enforcement is enabled', function (): void {
    setRequireAdmin2fa(true);
    $admin = adminWithout2fa();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.account.security'));
});

it('security page never redirects onto itself even with enforcement on', function (): void {
    setRequireAdmin2fa(true);
    $admin = adminWithout2fa();

    $this->actingAs($admin)
        ->get(route('admin.account.security'))
        ->assertOk();
});

it('admin.account.security resolves to the admin URL space', function (): void {
    expect(route('admin.account.security'))->toContain('/admin/');
});

it('confirmed-2fa admin reaches the dashboard directly', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});
