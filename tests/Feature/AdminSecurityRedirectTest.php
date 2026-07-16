<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Admins without confirmed 2FA are redirected to the admin-scoped security
 * page (admin.account.security), which lives OUTSIDE the require-admin-2fa
 * gate so it stays reachable. Regression guard for the reported bug where
 * the redirect pointed at /panel/ucet/zabezpeceni instead.
 */

function adminWithout2fa(): User
{
    Role::findOrCreate('admin', 'web');
    $user = User::factory()->create(['two_factor_confirmed_at' => null]);
    $user->assignRole('admin');

    return $user;
}

it('admin without 2FA is redirected to the admin security page', function (): void {
    $admin = adminWithout2fa();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.account.security'));
});

it('admin security page is reachable without confirmed 2FA (not gated)', function (): void {
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
