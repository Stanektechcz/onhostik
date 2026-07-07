<?php

use App\Domains\Customer\Models\Customer;
use App\Models\FraudReview;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view fraud review queue', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.fraud-reviews.index'))
        ->assertOk()
        ->assertViewHas('pending');
});

it('admin can mark a review as cleared', function (): void {
    $review = FraudReview::factory()->create(['score' => 70, 'status' => 'pending']);

    $this->actingAs(adminUser())
        ->patch(route('admin.fraud-reviews.update', $review), [
            'status' => 'cleared',
        ])
        ->assertRedirect();

    expect($review->fresh()->status)->toBe('cleared');
    expect($review->fresh()->reviewed_at)->not()->toBeNull();
});

it('admin can block a review', function (): void {
    $review = FraudReview::factory()->create(['score' => 90, 'status' => 'pending']);

    $this->actingAs(adminUser())
        ->patch(route('admin.fraud-reviews.update', $review), [
            'status' => 'blocked',
        ])
        ->assertRedirect();

    expect($review->fresh()->status)->toBe('blocked');
});

it('admin can create a new fraud review', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.fraud-reviews.store'), [
            'customer_id' => $customer->id,
            'score'       => 85,
            'signals'     => ['new_card', 'vpn_detected'],
        ])
        ->assertRedirect();

    expect(FraudReview::where('customer_id', $customer->id)->exists())->toBeTrue();
});

it('score must not exceed 100', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.fraud-reviews.store'), [
            'customer_id' => $customer->id,
            'score'       => 150,
            'signals'     => ['test'],
        ])
        ->assertSessionHasErrors('score');
});
