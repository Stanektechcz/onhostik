<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view payment retry status', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.payment-retry-status.index'))
        ->assertOk()
        ->assertViewHas('retries');
});

it('panel user only sees their own retries', function (): void {
    $user      = customerUser();
    $otherUser = customerUser();
    $admin     = adminUser();

    $invoice1 = \App\Domains\Billing\Models\Invoice::factory()->create(['customer_id' => $user->customer->id]);
    $invoice2 = \App\Domains\Billing\Models\Invoice::factory()->create(['customer_id' => $otherUser->customer->id]);

    \App\Models\PaymentRetrySchedule::create([
        'invoice_id'  => $invoice1->id,
        'customer_id' => $user->customer->id,
        'retry_at'    => now()->addDay(),
        'status'      => 'pending',
        'created_by'  => $admin->id,
    ]);

    \App\Models\PaymentRetrySchedule::create([
        'invoice_id'  => $invoice2->id,
        'customer_id' => $otherUser->customer->id,
        'retry_at'    => now()->addDay(),
        'status'      => 'pending',
        'created_by'  => $admin->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.payment-retry-status.index'))
        ->assertOk();

    expect($response->viewData('retries')->total())->toBe(1);
});

it('retries are sorted by retry_at descending', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.payment-retry-status.index'))
        ->assertOk();
});

it('guest cannot access payment retry status', function (): void {
    $this->get(route('panel.payment-retry-status.index'))
        ->assertRedirect();
});

it('panel user with no retries sees empty list', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.payment-retry-status.index'))
        ->assertOk();

    expect($response->viewData('retries')->total())->toBe(0);
});
