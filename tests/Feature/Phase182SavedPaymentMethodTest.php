<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\SavedPaymentMethodController;
use App\Models\SavedPaymentMethod;

test('saved payment method controller exists', function (): void {
    expect(class_exists(SavedPaymentMethodController::class))->toBeTrue();
});

test('saved payment method model exists', function (): void {
    expect(class_exists(SavedPaymentMethod::class))->toBeTrue();
});

test('saved payment method table exists', function (): void {
    expect(\Schema::hasTable('saved_payment_methods'))->toBeTrue();
});

test('payment methods index route exists', function (): void {
    expect(Route::has('panel.payment-methods.index'))->toBeTrue();
});

test('payment methods token is hidden in model', function (): void {
    $method = new SavedPaymentMethod(['token' => 'secret_token']);
    $array = $method->toArray();
    expect($array)->not->toHaveKey('token');
});
