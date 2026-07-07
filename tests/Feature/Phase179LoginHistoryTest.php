<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\LoginHistoryController;

test('login history controller exists', function (): void {
    expect(class_exists(LoginHistoryController::class))->toBeTrue();
});

test('login history route exists', function (): void {
    expect(Route::has('panel.account.login-history'))->toBeTrue();
});

test('login history requires authentication', function (): void {
    $response = $this->get(route('panel.account.login-history'));
    $response->assertRedirect();
});

test('login history loads for customer', function (): void {
    $user = customerUser();
    $response = $this->actingAs($user)->get(route('panel.account.login-history'));
    $response->assertOk();
});

test('login history table has created_at column', function (): void {
    expect(\Schema::hasColumn('user_login_history', 'created_at'))->toBeTrue();
});
