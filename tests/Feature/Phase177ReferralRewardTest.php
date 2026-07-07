<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\ReferralRewardController;

test('referral reward controller exists', function (): void {
    expect(class_exists(ReferralRewardController::class))->toBeTrue();
});

test('referral rewards route exists', function (): void {
    expect(Route::has('panel.referrals.rewards.index'))->toBeTrue();
});

test('referral rewards requires authentication', function (): void {
    $response = $this->get(route('panel.referrals.rewards.index'));
    $response->assertRedirect();
});

test('referral rewards loads for customer', function (): void {
    $user = customerUser();
    $response = $this->actingAs($user)->get(route('panel.referrals.rewards.index'));
    $response->assertOk();
});

test('referral rewards view shows reward info', function (): void {
    $user = customerUser();
    $response = $this->actingAs($user)->get(route('panel.referrals.rewards.index'));
    $response->assertSee('odměn', false);
});
