<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\AccountDeletionController;
use App\Http\Controllers\Admin\AccountDeletionAdminController;
use App\Models\AccountDeletionRequest;

test('account deletion panel controller exists', function (): void {
    expect(class_exists(AccountDeletionController::class))->toBeTrue();
});

test('account deletion admin controller exists', function (): void {
    expect(class_exists(AccountDeletionAdminController::class))->toBeTrue();
});

test('account deletion requests table exists', function (): void {
    expect(\Schema::hasTable('account_deletion_requests'))->toBeTrue();
});

test('account deletion create route exists for panel', function (): void {
    expect(Route::has('panel.account.delete.create'))->toBeTrue();
});

test('account deletion request can be submitted', function (): void {
    $user = customerUser();
    $response = $this->actingAs($user)->post(route('panel.account.delete.store'), [
        'reason' => 'No longer need the service.',
    ]);
    $response->assertRedirect();

    expect(AccountDeletionRequest::where('user_id', $user->id)->exists())->toBeTrue();
});
