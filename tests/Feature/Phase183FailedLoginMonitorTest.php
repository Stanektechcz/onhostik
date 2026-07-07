<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\FailedLoginMonitorController;
use App\Models\FailedAdminLogin;

test('failed login monitor controller exists', function (): void {
    expect(class_exists(FailedLoginMonitorController::class))->toBeTrue();
});

test('failed admin login model exists', function (): void {
    expect(class_exists(FailedAdminLogin::class))->toBeTrue();
});

test('failed admin logins table exists', function (): void {
    expect(\Schema::hasTable('failed_admin_logins'))->toBeTrue();
});

test('failed login monitor route exists', function (): void {
    expect(Route::has('admin.failed-logins.index'))->toBeTrue();
});

test('failed login monitor loads for admin', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->get(route('admin.failed-logins.index'));
    $response->assertOk();
});
