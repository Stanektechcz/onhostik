<?php

declare(strict_types=1);

use App\Models\TwoFactorRecoveryCode;

it('customer can view 2fa recovery codes page', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('panel.account.2fa-recovery.show'))
         ->assertOk()
         ->assertViewIs('panel.account.2fa-recovery');
});

it('regenerating codes creates 8 new codes', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->post(route('panel.account.2fa-recovery.regenerate'))
         ->assertRedirect();

    expect(TwoFactorRecoveryCode::where('user_id', $customer->id)->count())->toBe(8);
});

it('regenerating codes removes old codes', function (): void {
    $customer = customerUser();

    TwoFactorRecoveryCode::insert([
        ['user_id' => $customer->id, 'code' => 'AAAA-BBBB', 'used_at' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($customer)
         ->post(route('panel.account.2fa-recovery.regenerate'));

    expect(TwoFactorRecoveryCode::where('user_id', $customer->id)->where('code', 'AAAA-BBBB')->exists())->toBeFalse();
});

it('recovery codes have XXXX-XXXX format', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->post(route('panel.account.2fa-recovery.regenerate'));

    $codes = TwoFactorRecoveryCode::where('user_id', $customer->id)->pluck('code');
    foreach ($codes as $code) {
        expect($code)->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
    }
});

it('unauthenticated user cannot access recovery codes', function (): void {
    $this->get(route('panel.account.2fa-recovery.show'))
         ->assertRedirect('/login');
});
