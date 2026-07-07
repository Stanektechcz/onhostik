<?php

use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Models\PartnerProfile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can view referral stats dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.referral-stats.index'))
        ->assertOk()
        ->assertViewHas('profile');
});

it('dashboard shows no-profile message when no partner profile exists', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.referral-stats.index'))
        ->assertOk()
        ->assertSee('partnerský profil');
});

it('dashboard shows stats when partner profile exists', function (): void {
    $user    = customerUser();
    $profile = PartnerProfile::create([
        'user_id'                  => $user->id,
        'referral_code'            => 'TEST123',
        'status'                   => PartnerStatus::Active,
        'commission_rate_percent'  => 10.0,
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.referral-stats.index'))
        ->assertOk();

    expect($response->viewData('profile'))->not->toBeNull();
});

it('dashboard view includes commissions and payouts', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.referral-stats.index'))
        ->assertOk();

    expect($response->viewData('totalEarned'))->toBe(0);
    expect($response->viewData('pendingPayout'))->toBe(0);
});

it('guest cannot access referral stats', function (): void {
    $this->get(route('panel.referral-stats.index'))
        ->assertRedirect();
});
