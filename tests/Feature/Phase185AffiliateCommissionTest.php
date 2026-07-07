<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AffiliateCommissionController;
use App\Models\AffiliateCommission;
use App\Domains\Customer\Models\Customer;

test('affiliate commission controller exists', function (): void {
    expect(class_exists(AffiliateCommissionController::class))->toBeTrue();
});

test('affiliate commission model exists', function (): void {
    expect(class_exists(AffiliateCommission::class))->toBeTrue();
});

test('affiliate commissions table exists', function (): void {
    expect(\Schema::hasTable('affiliate_commissions'))->toBeTrue();
});

test('affiliate commission index route exists', function (): void {
    expect(Route::has('admin.affiliate-commissions.index'))->toBeTrue();
});

test('affiliate commission can be created and approved', function (): void {
    $customer = Customer::factory()->create();
    $commission = AffiliateCommission::create([
        'customer_id'      => $customer->id,
        'affiliate_code'   => 'TEST123',
        'commission_haler' => 10000,
        'status'           => 'pending',
    ]);

    expect($commission->status)->toBe('pending');
    $commission->update(['status' => 'approved']);
    expect($commission->fresh()->status)->toBe('approved');
});
