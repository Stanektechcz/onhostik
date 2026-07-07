<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view payment retry schedules index', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.payment-retry-schedules.index'))
        ->assertOk()
        ->assertViewHas('retries');
});

it('admin can create a payment retry schedule', function (): void {
    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.payment-retry-schedules.store'), [
            'invoice_id'  => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'retry_at'    => now()->addDay()->format('Y-m-d H:i:s'),
            'note'        => 'Test',
        ])
        ->assertRedirect();

    expect(\App\Models\PaymentRetrySchedule::count())->toBe(1);
});

it('admin can delete a payment retry schedule', function (): void {
    $admin   = adminUser();
    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create();
    $record  = \App\Models\PaymentRetrySchedule::create([
        'invoice_id'  => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'retry_at'    => now()->addDay(),
        'status'      => 'pending',
        'created_by'  => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.payment-retry-schedules.destroy', $record))
        ->assertRedirect();

    $this->assertDatabaseMissing('payment_retry_schedules', ['id' => $record->id]);
});

it('guest cannot access payment retry schedules', function (): void {
    $this->get(route('admin.payment-retry-schedules.index'))
        ->assertRedirect();
});

it('store validates retry_at is required', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.payment-retry-schedules.store'), [
            'invoice_id'  => 1,
            'customer_id' => 1,
        ])
        ->assertSessionHasErrors('retry_at');
});
