<?php

use App\Models\CustomerWebhookSubscription;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can view their webhook subscriptions', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.webhook-subscriptions.index'))
        ->assertOk()
        ->assertViewHas('subscriptions');
});

it('customer can create a webhook subscription', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.webhook-subscriptions.store'), [
            'url'    => 'https://example.com/hook',
            'events' => ['invoice.paid'],
        ])
        ->assertRedirect();

    expect(CustomerWebhookSubscription::where('customer_id', $user->customer->id)->exists())->toBeTrue();
});

it('created webhook has a non-empty auto-generated secret', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.webhook-subscriptions.store'), [
            'url'    => 'https://example.com/hook',
            'events' => ['invoice.paid'],
        ]);

    $sub = CustomerWebhookSubscription::where('customer_id', $user->customer->id)->first();
    expect($sub)->not()->toBeNull();
    expect(strlen($sub->secret))->toBeGreaterThan(16);
});

it('customer can delete their own webhook', function (): void {
    $user = customerUser();
    $sub  = CustomerWebhookSubscription::create([
        'customer_id' => $user->customer->id,
        'url'         => 'https://example.com/hook',
        'secret'      => 'testsecret',
        'events'      => ['invoice.paid'],
        'is_active'   => true,
    ]);

    $this->actingAs($user)
        ->delete(route('panel.webhook-subscriptions.destroy', $sub))
        ->assertRedirect();

    expect(CustomerWebhookSubscription::find($sub->id))->toBeNull();
});

it('customer cannot delete another customers webhook', function (): void {
    $user    = customerUser();
    $other   = customerUser();
    $sub     = CustomerWebhookSubscription::create([
        'customer_id' => $other->customer->id,
        'url'         => 'https://example.com/hook',
        'secret'      => 'othersecret',
        'events'      => ['service.created'],
        'is_active'   => true,
    ]);

    $this->actingAs($user)
        ->delete(route('panel.webhook-subscriptions.destroy', $sub))
        ->assertForbidden();
});
