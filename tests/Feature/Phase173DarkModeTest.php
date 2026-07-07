<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\DarkModeController;
use App\Models\User;

test('dark mode controller exists', function (): void {
    expect(class_exists(DarkModeController::class))->toBeTrue();
});

test('dark mode route exists', function (): void {
    expect(Route::has('panel.account.dark-mode.toggle'))->toBeTrue();
});

test('dark mode toggle requires authentication', function (): void {
    $response = $this->post(route('panel.account.dark-mode.toggle'));
    $response->assertRedirect();
});

test('dark mode toggle turns on dark mode', function (): void {
    $user = customerUser();
    $user->update(['dark_mode' => false]);

    $response = $this->actingAs($user)->post(route('panel.account.dark-mode.toggle'));

    $response->assertRedirect();
    expect($user->fresh()->dark_mode)->toBeTrue();
});

test('dark mode toggle turns off dark mode', function (): void {
    $user = customerUser();
    $user->update(['dark_mode' => true]);

    $response = $this->actingAs($user)->post(route('panel.account.dark-mode.toggle'));

    $response->assertRedirect();
    expect($user->fresh()->dark_mode)->toBeFalse();
});
