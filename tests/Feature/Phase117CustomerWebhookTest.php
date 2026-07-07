<?php

declare(strict_types=1);

use App\Models\OutgoingWebhook;
use App\Models\WebhookDelivery;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Index ─────────────────────────────────────────────────────────────────────

it('customer can view their webhooks index', function (): void {
    $user = customerUser();

    OutgoingWebhook::create([
        'customer_id' => $user->customer->id,
        'name'        => 'Test Hook',
        'url'         => 'https://example.com/hook',
        'events'      => ['invoice.paid'],
        'secret'      => '',
        'is_active'   => true,
    ]);

    $this->actingAs($user)
         ->get(route('panel.webhooks.index'))
         ->assertOk()
         ->assertSee('Test Hook')
         ->assertSee('invoice.paid');
});

it('customer cannot see other customers webhooks on index', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();

    OutgoingWebhook::create([
        'customer_id' => $user2->customer->id,
        'name'        => 'Other Customer Hook',
        'url'         => 'https://other.com/hook',
        'events'      => ['*'],
        'secret'      => '',
        'is_active'   => true,
    ]);

    $this->actingAs($user1)
         ->get(route('panel.webhooks.index'))
         ->assertOk()
         ->assertDontSee('Other Customer Hook');
});

// ── Store ─────────────────────────────────────────────────────────────────────

it('customer can create a webhook', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->post(route('panel.webhooks.store'), [
             'name'   => 'My Hook',
             'url'    => 'https://example.com/recv',
             'events' => ['invoice.paid', 'service.suspended'],
             'secret' => 'supersecret',
         ])
         ->assertRedirect()
         ->assertSessionHas('status');

    $webhook = OutgoingWebhook::query()
        ->where('customer_id', $user->customer->id)
        ->where('name', 'My Hook')
        ->first();

    expect($webhook)->not->toBeNull()
        ->and($webhook->url)->toBe('https://example.com/recv')
        ->and($webhook->events)->toBe(['invoice.paid', 'service.suspended'])
        ->and($webhook->is_active)->toBeTrue();
});

it('webhook store validates required fields', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->post(route('panel.webhooks.store'), [])
         ->assertSessionHasErrors(['name', 'url', 'events']);
});

it('webhook store rejects invalid event names', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->post(route('panel.webhooks.store'), [
             'name'   => 'Bad',
             'url'    => 'https://example.com/hook',
             'events' => ['not.a.real.event'],
         ])
         ->assertSessionHasErrors('events.0');
});

it('webhook store enforces maximum limit', function (): void {
    $user = customerUser();

    for ($i = 0; $i < 10; $i++) {
        OutgoingWebhook::create([
            'customer_id' => $user->customer->id,
            'name'        => "Hook {$i}",
            'url'         => "https://example.com/hook{$i}",
            'events'      => ['*'],
            'secret'      => '',
            'is_active'   => true,
        ]);
    }

    $this->actingAs($user)
         ->post(route('panel.webhooks.store'), [
             'name'   => 'One More',
             'url'    => 'https://example.com/extra',
             'events' => ['*'],
         ])
         ->assertSessionHasErrors('name');
});

// ── Toggle ────────────────────────────────────────────────────────────────────

it('customer can toggle webhook active state', function (): void {
    $user    = customerUser();
    $webhook = OutgoingWebhook::create([
        'customer_id' => $user->customer->id,
        'name'        => 'Toggle Me',
        'url'         => 'https://example.com/hook',
        'events'      => ['*'],
        'secret'      => '',
        'is_active'   => true,
    ]);

    $this->actingAs($user)
         ->post(route('panel.webhooks.toggle', $webhook))
         ->assertRedirect()
         ->assertSessionHas('status');

    expect($webhook->fresh()->is_active)->toBeFalse();
});

it('customer cannot toggle another customers webhook', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $webhook = OutgoingWebhook::create([
        'customer_id' => $user2->customer->id,
        'name'        => 'Theirs',
        'url'         => 'https://example.com/hook',
        'events'      => ['*'],
        'secret'      => '',
        'is_active'   => true,
    ]);

    $this->actingAs($user1)
         ->post(route('panel.webhooks.toggle', $webhook))
         ->assertForbidden();
});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('customer can delete their webhook', function (): void {
    $user    = customerUser();
    $webhook = OutgoingWebhook::create([
        'customer_id' => $user->customer->id,
        'name'        => 'Delete Me',
        'url'         => 'https://example.com/hook',
        'events'      => ['*'],
        'secret'      => '',
        'is_active'   => true,
    ]);

    $this->actingAs($user)
         ->delete(route('panel.webhooks.destroy', $webhook))
         ->assertRedirect()
         ->assertSessionHas('status');

    expect(OutgoingWebhook::find($webhook->id))->toBeNull();
});

it('customer cannot delete another customers webhook', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $webhook = OutgoingWebhook::create([
        'customer_id' => $user2->customer->id,
        'name'        => 'Not Mine',
        'url'         => 'https://example.com/hook',
        'events'      => ['*'],
        'secret'      => '',
        'is_active'   => true,
    ]);

    $this->actingAs($user1)
         ->delete(route('panel.webhooks.destroy', $webhook))
         ->assertForbidden();

    expect(OutgoingWebhook::find($webhook->id))->not->toBeNull();
});

// ── Deliveries ────────────────────────────────────────────────────────────────

it('customer can view delivery log for their webhook', function (): void {
    $user    = customerUser();
    $webhook = OutgoingWebhook::create([
        'customer_id' => $user->customer->id,
        'name'        => 'Delivery Test',
        'url'         => 'https://example.com/hook',
        'events'      => ['invoice.paid'],
        'secret'      => '',
        'is_active'   => true,
    ]);

    WebhookDelivery::create([
        'outgoing_webhook_id' => $webhook->id,
        'event'               => 'invoice.paid',
        'payload'             => ['invoice_id' => 1],
        'status'              => 'success',
        'response_code'       => 200,
        'delivered_at'        => now(),
    ]);

    $this->actingAs($user)
         ->get(route('panel.webhooks.deliveries', $webhook))
         ->assertOk()
         ->assertSee('invoice.paid')
         ->assertSee('200');
});

it('customer cannot view delivery log for another customers webhook', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $webhook = OutgoingWebhook::create([
        'customer_id' => $user2->customer->id,
        'name'        => 'Theirs',
        'url'         => 'https://example.com/hook',
        'events'      => ['*'],
        'secret'      => '',
        'is_active'   => true,
    ]);

    $this->actingAs($user1)
         ->get(route('panel.webhooks.deliveries', $webhook))
         ->assertForbidden();
});
