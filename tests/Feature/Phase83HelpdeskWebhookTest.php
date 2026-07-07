<?php

declare(strict_types=1);

use App\Domains\Support\Models\HelpdeskWebhook;
use App\Domains\Support\Models\HelpdeskWebhookDelivery;
use App\Domains\Support\Services\HelpdeskWebhookService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model ─────────────────────────────────────────────────────────────────────

it('EVENTS constant covers all expected events', function (): void {
    expect(HelpdeskWebhook::EVENTS)->toHaveKey('ticket.created')
        ->and(HelpdeskWebhook::EVENTS)->toHaveKey('ticket.replied')
        ->and(HelpdeskWebhook::EVENTS)->toHaveKey('ticket.closed')
        ->and(HelpdeskWebhook::EVENTS)->toHaveKey('ticket.status_changed');
});

it('listensTo returns true for subscribed events', function (): void {
    $wh = HelpdeskWebhook::create([
        'name'      => 'Test',
        'url'       => 'https://example.com',
        'events'    => ['ticket.created', 'ticket.closed'],
        'is_active' => true,
    ]);

    expect($wh->listensTo('ticket.created'))->toBeTrue()
        ->and($wh->listensTo('ticket.replied'))->toBeFalse();
});

it('deliveries relation exists', function (): void {
    $wh = HelpdeskWebhook::create([
        'name' => 'Test', 'url' => 'https://example.com',
        'events' => ['ticket.created'], 'is_active' => true,
    ]);

    expect($wh->deliveries())->not->toBeNull();
});

// ── Service ───────────────────────────────────────────────────────────────────

it('fire sends HTTP POST to matching webhooks', function (): void {
    Http::fake(['https://example.com/*' => Http::response('ok', 200)]);

    $wh = HelpdeskWebhook::create([
        'name'      => 'CI Hook',
        'url'       => 'https://example.com/webhook',
        'events'    => ['ticket.created'],
        'is_active' => true,
    ]);

    $user   = customerUser();
    $ticket = openTicket($user);

    $service = new HelpdeskWebhookService();
    $service->fire('ticket.created', $ticket);

    Http::assertSent(fn ($req) => str_contains($req->url(), 'example.com'));
});

it('fire logs a delivery record', function (): void {
    Http::fake(['https://example.com/*' => Http::response('ok', 200)]);

    $wh = HelpdeskWebhook::create([
        'name'      => 'Logger',
        'url'       => 'https://example.com/webhook',
        'events'    => ['ticket.created'],
        'is_active' => true,
    ]);

    $ticket = openTicket(customerUser());

    (new HelpdeskWebhookService())->fire('ticket.created', $ticket);

    expect(HelpdeskWebhookDelivery::where('helpdesk_webhook_id', $wh->id)->count())->toBe(1);
    expect(HelpdeskWebhookDelivery::where('helpdesk_webhook_id', $wh->id)->first()->success)->toBeTrue();
});

it('fire does not send to unsubscribed webhooks', function (): void {
    Http::fake();

    HelpdeskWebhook::create([
        'name'      => 'Only Closed',
        'url'       => 'https://example.com/webhook',
        'events'    => ['ticket.closed'],
        'is_active' => true,
    ]);

    $ticket = openTicket(customerUser());
    (new HelpdeskWebhookService())->fire('ticket.created', $ticket);

    Http::assertNothingSent();
});

it('fire does not send to inactive webhooks', function (): void {
    Http::fake();

    HelpdeskWebhook::create([
        'name'      => 'Inactive',
        'url'       => 'https://example.com/webhook',
        'events'    => ['ticket.created'],
        'is_active' => false,
    ]);

    $ticket = openTicket(customerUser());
    (new HelpdeskWebhookService())->fire('ticket.created', $ticket);

    Http::assertNothingSent();
});

it('fire includes HMAC signature when secret is set', function (): void {
    Http::fake(['https://example.com/*' => Http::response('ok', 200)]);

    HelpdeskWebhook::create([
        'name'      => 'Signed',
        'url'       => 'https://example.com/webhook',
        'events'    => ['ticket.created'],
        'secret'    => 'my-super-secret',
        'is_active' => true,
    ]);

    $ticket = openTicket(customerUser());
    (new HelpdeskWebhookService())->fire('ticket.created', $ticket);

    Http::assertSent(fn ($req) => $req->hasHeader('X-Onhost-Signature'));
});

it('fire increments failure_count on HTTP error', function (): void {
    Http::fake(['https://example.com/*' => Http::response('error', 500)]);

    $wh = HelpdeskWebhook::create([
        'name' => 'Failing', 'url' => 'https://example.com/webhook',
        'events' => ['ticket.created'], 'is_active' => true,
    ]);

    $ticket = openTicket(customerUser());
    (new HelpdeskWebhookService())->fire('ticket.created', $ticket);

    expect($wh->fresh()->failure_count)->toBe(1);
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view helpdesk webhooks page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.helpdesk-webhooks.index'))
        ->assertOk()
        ->assertSee('webhook');
});

it('admin can create a webhook', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.helpdesk-webhooks.store'), [
            'name'      => 'My Webhook',
            'url'       => 'https://hooks.example.com/receive',
            'events'    => ['ticket.created', 'ticket.closed'],
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.helpdesk-webhooks.index'));

    expect(HelpdeskWebhook::where('name', 'My Webhook')->exists())->toBeTrue();
});

it('validation rejects invalid url', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.helpdesk-webhooks.store'), [
            'name'   => 'Bad',
            'url'    => 'not-a-url',
            'events' => ['ticket.created'],
        ])
        ->assertSessionHasErrors('url');
});

it('validation requires at least one event', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.helpdesk-webhooks.store'), [
            'name' => 'Test',
            'url'  => 'https://example.com',
        ])
        ->assertSessionHasErrors('events');
});

it('admin can update a webhook', function (): void {
    $admin = adminUser();
    $wh    = HelpdeskWebhook::create([
        'name' => 'Old Name', 'url' => 'https://example.com',
        'events' => ['ticket.created'], 'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.helpdesk-webhooks.update', $wh), [
            'name'      => 'New Name',
            'url'       => 'https://new.example.com',
            'events'    => ['ticket.replied', 'ticket.closed'],
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.helpdesk-webhooks.index'));

    expect($wh->fresh()->name)->toBe('New Name');
});

it('admin can delete a webhook', function (): void {
    $admin = adminUser();
    $wh    = HelpdeskWebhook::create([
        'name' => 'Delete Me', 'url' => 'https://example.com',
        'events' => ['ticket.created'], 'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.helpdesk-webhooks.destroy', $wh))
        ->assertRedirect(route('admin.helpdesk-webhooks.index'));

    expect(HelpdeskWebhook::find($wh->id))->toBeNull();
});

it('admin can regenerate webhook secret', function (): void {
    $admin = adminUser();
    $wh    = HelpdeskWebhook::create([
        'name' => 'Regen', 'url' => 'https://example.com',
        'events' => ['ticket.created'], 'secret' => 'old-secret', 'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.helpdesk-webhooks.regenerate', $wh))
        ->assertRedirect(route('admin.helpdesk-webhooks.index'));

    expect($wh->fresh()->secret)->not->toBe('old-secret')
        ->and($wh->fresh()->secret)->not->toBeNull();
});

it('non-admin cannot access webhook routes', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.helpdesk-webhooks.index'))
        ->assertStatus(403);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function openTicket($user): \App\Domains\Support\Models\SupportTicket
{
    $customer = $user->customer ?? \App\Domains\Customer\Models\Customer::factory()->create(['user_id' => $user->id]);

    return \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $user) {
        $ticket = \App\Domains\Support\Models\SupportTicket::factory()->create([
            'customer_id' => $customer->id,
        ]);
        return $ticket;
    });
}
