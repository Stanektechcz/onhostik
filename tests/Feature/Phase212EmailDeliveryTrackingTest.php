<?php

use App\Models\EmailDelivery;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view email delivery index', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.email-deliveries.index'))
        ->assertOk()
        ->assertViewHas('deliveries');
});

it('admin can filter email deliveries by status', function (): void {
    EmailDelivery::create(['recipient' => 'a@test.com', 'subject' => 'Test', 'status' => 'delivered']);
    EmailDelivery::create(['recipient' => 'b@test.com', 'subject' => 'Test', 'status' => 'bounced']);

    $this->actingAs(adminUser())
        ->get(route('admin.email-deliveries.index') . '?status=delivered')
        ->assertOk()
        ->assertViewHas('deliveries');
});

it('customer can view their own email delivery history', function (): void {
    $user = customerUser();

    EmailDelivery::create(['user_id' => $user->id, 'recipient' => $user->email, 'subject' => 'Hello', 'status' => 'delivered']);

    $this->actingAs($user)
        ->get(route('panel.email-deliveries.index'))
        ->assertOk()
        ->assertViewHas('deliveries');
});

it('customer sees only their own email deliveries', function (): void {
    $user  = customerUser();
    $other = customerUser();

    EmailDelivery::create(['user_id' => $other->id, 'recipient' => $other->email, 'subject' => 'Other', 'status' => 'sent']);

    $response = $this->actingAs($user)
        ->get(route('panel.email-deliveries.index'))
        ->assertOk();

    $deliveries = $response->viewData('deliveries');
    expect($deliveries->total())->toBe(0);
});

it('guest cannot access email deliveries panel', function (): void {
    $this->get(route('panel.email-deliveries.index'))
        ->assertRedirect();
});
