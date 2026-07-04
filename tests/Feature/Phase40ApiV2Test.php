<?php

declare(strict_types=1);

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Models\OutgoingWebhook;
use App\Models\WebhookDelivery;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function p40MakeService(int $customerId): Service
{
    return Service::create([
        'customer_id'         => $customerId,
        'product_id'          => \App\Domains\Products\Models\Product::value('id'),
        'status'              => ServiceStatus::Active,
        'label'               => 'p40-svc-' . uniqid(),
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);
}

function p40MakeWebhook(int $customerId): OutgoingWebhook
{
    return OutgoingWebhook::create([
        'customer_id' => $customerId,
        'name'        => 'Hook ' . uniqid(),
        'url'         => 'https://example.com/hook-' . uniqid(),
        'events'      => ['invoice.paid', 'service.status_changed'],
        'secret'      => '',
        'is_active'   => true,
    ]);
}

// ── V2 services ───────────────────────────────────────────────────────────────

it('v2 services index returns monitors key per service', function (): void {
    $user  = customerUser();
    $svc   = p40MakeService($user->customer->id);
    Monitor::factory()->create(['service_id' => $svc->id, 'status' => MonitorStatus::Up]);

    $token = $user->createToken('v2-test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v2/services')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'label', 'status', 'monitors']]]);

    $first = collect($response->json('data'))->firstWhere('id', $svc->id);
    expect($first)->not->toBeNull();
    expect($first['monitors'])->toBeArray();
    expect($first['monitors'][0])->toHaveKeys(['id', 'name', 'status', 'uptime_percent', 'last_check_at']);
});

it('v2 services index returns empty monitors array when no monitors', function (): void {
    $user  = customerUser();
    p40MakeService($user->customer->id);
    $token = $user->createToken('v2-test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v2/services')
        ->assertOk();

    $first = collect($response->json('data'))->first();
    expect($first['monitors'])->toBeArray()->toHaveCount(0);
});

it('v2 services does not expose other customers services', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();
    p40MakeService($user1->customer->id);
    p40MakeService($user2->customer->id);

    $token = $user1->createToken('v2-test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v2/services')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    foreach ($user2->customer->services as $s) {
        expect($ids)->not->toContain($s->id);
    }
});

it('v2 services show returns detailed data with monitors', function (): void {
    $user  = customerUser();
    $svc   = p40MakeService($user->customer->id);
    Monitor::factory()->create(['service_id' => $svc->id]);
    $token = $user->createToken('v2-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v2/services/' . $svc->uuid)
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'uuid', 'label', 'status', 'product', 'monitors',
        ]])
        ->assertJsonPath('data.id', $svc->id);
});

it('v2 services show returns 403 for cross-customer', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();
    $svc   = p40MakeService($user2->customer->id);
    $token = $user1->createToken('v2-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v2/services/' . $svc->uuid)
        ->assertForbidden();
});

it('v2 services unauthenticated returns 401', function (): void {
    $this->getJson('/api/v2/services')->assertUnauthorized();
});

it('v2 services returns 403 when user has no customer profile', function (): void {
    $user  = \App\Models\User::factory()->create();
    $token = $user->createToken('v2-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v2/services')
        ->assertForbidden();
});

// ── V2 webhooks ───────────────────────────────────────────────────────────────

it('v2 webhook index returns own webhooks', function (): void {
    $user  = customerUser();
    p40MakeWebhook($user->customer->id);
    $token = $user->createToken('v2-wh')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v2/webhooks')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'url', 'events', 'is_active', 'created_at']]]);

    expect(count($response->json('data')))->toBe(1);
});

it('v2 webhook index does not return other customers webhooks', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();
    p40MakeWebhook($user1->customer->id);
    p40MakeWebhook($user2->customer->id);
    $token = $user1->createToken('v2-wh')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v2/webhooks')
        ->assertOk();

    expect(count($response->json('data')))->toBe(1);
});

it('v2 webhook store creates a webhook', function (): void {
    $user  = customerUser();
    $token = $user->createToken('v2-wh')->plainTextToken;

    $payload = [
        'name'   => 'My Webhook',
        'url'    => 'https://hooks.example.com/receive',
        'events' => ['invoice.paid', 'service.status_changed'],
    ];

    $response = $this->withToken($token)
        ->postJson('/api/v2/webhooks', $payload)
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'url', 'events', 'is_active']]);

    expect($response->json('data.name'))->toBe('My Webhook');
    expect($response->json('data.events'))->toBe(['invoice.paid', 'service.status_changed']);

    $this->assertDatabaseHas('outgoing_webhooks', [
        'customer_id' => $user->customer->id,
        'name'        => 'My Webhook',
    ]);
});

it('v2 webhook store validates required fields', function (): void {
    $user  = customerUser();
    $token = $user->createToken('v2-wh')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v2/webhooks', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'url', 'events']);
});

it('v2 webhook store rejects unknown event type', function (): void {
    $user  = customerUser();
    $token = $user->createToken('v2-wh')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v2/webhooks', [
            'name'   => 'Bad Webhook',
            'url'    => 'https://example.com/hook',
            'events' => ['unknown.event'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['events.0']);
});

it('v2 webhook store rejects invalid url', function (): void {
    $user  = customerUser();
    $token = $user->createToken('v2-wh')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v2/webhooks', [
            'name'   => 'Bad Webhook',
            'url'    => 'not-a-url',
            'events' => ['invoice.paid'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('v2 webhook destroy deletes own webhook', function (): void {
    $user    = customerUser();
    $webhook = p40MakeWebhook($user->customer->id);
    $token   = $user->createToken('v2-wh')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/v2/webhooks/' . $webhook->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('outgoing_webhooks', ['id' => $webhook->id]);
});

it('v2 webhook destroy returns 403 for cross-customer', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $webhook = p40MakeWebhook($user2->customer->id);
    $token   = $user1->createToken('v2-wh')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/v2/webhooks/' . $webhook->id)
        ->assertForbidden();

    $this->assertDatabaseHas('outgoing_webhooks', ['id' => $webhook->id]);
});

it('v2 webhook deliveries returns delivery log', function (): void {
    $user    = customerUser();
    $webhook = p40MakeWebhook($user->customer->id);
    $token   = $user->createToken('v2-wh')->plainTextToken;

    WebhookDelivery::create([
        'outgoing_webhook_id' => $webhook->id,
        'event'               => 'invoice.paid',
        'payload'             => ['invoice_id' => 1],
        'status'              => 'delivered',
        'response_code'       => 200,
        'response_body'       => 'OK',
        'delivered_at'        => now(),
    ]);

    $this->withToken($token)
        ->getJson('/api/v2/webhooks/' . $webhook->id . '/deliveries')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'event', 'status', 'response_code', 'delivered_at']],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);
});

it('v2 webhook deliveries returns 403 for cross-customer', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $webhook = p40MakeWebhook($user2->customer->id);
    $token   = $user1->createToken('v2-wh')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v2/webhooks/' . $webhook->id . '/deliveries')
        ->assertForbidden();
});

it('v2 webhook endpoints unauthenticated return 401', function (): void {
    $this->getJson('/api/v2/webhooks')->assertUnauthorized();
    $this->postJson('/api/v2/webhooks', [])->assertUnauthorized();
});

it('v2 webhook store returns 403 when user has no customer profile', function (): void {
    $user  = \App\Models\User::factory()->create();
    $token = $user->createToken('v2-wh')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v2/webhooks', [
            'name'   => 'Test',
            'url'    => 'https://example.com',
            'events' => ['invoice.paid'],
        ])
        ->assertForbidden();
});
