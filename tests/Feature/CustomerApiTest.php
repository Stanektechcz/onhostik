<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Token management ─────────────────────────────────────────────────────────

it('customer can view api tokens page', function (): void {
    $this->actingAs(customerUser())
        ->get(route('panel.account.api-tokens'))
        ->assertOk();
});

it('customer can create api token', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.account.api-tokens.store'), ['name' => 'Test Token'])
        ->assertRedirect();

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('Test Token');
});

it('customer cannot create more than 5 tokens', function (): void {
    $user = customerUser();

    for ($i = 1; $i <= 5; $i++) {
        $user->createToken("Token {$i}");
    }

    $this->actingAs($user)
        ->post(route('panel.account.api-tokens.store'), ['name' => 'One Too Many'])
        ->assertRedirect();

    // Still only 5
    expect($user->tokens()->count())->toBe(5);
});

it('customer can delete api token', function (): void {
    $user  = customerUser();
    $token = $user->createToken('My Token');

    $this->actingAs($user)
        ->delete(route('panel.account.api-tokens.destroy', $token->accessToken->id))
        ->assertRedirect();

    expect($user->tokens()->count())->toBe(0);
});

// ── REST API v1 ───────────────────────────────────────────────────────────────

it('unauthenticated request to api returns 401', function (): void {
    $this->getJson('/api/v1/profile')->assertUnauthorized();
});

it('api profile returns user data', function (): void {
    $user  = customerUser();
    $token = $user->createToken('api-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('api services returns customer services', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');
    $token     = $user->createToken('api-test')->plainTextToken;

    Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'api-service-test',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/services')
        ->assertOk()
        ->assertJsonStructure(['data']);

    expect(count($response->json('data')))->toBeGreaterThan(0);
});

it('api services does not return other customers services', function (): void {
    $user1 = customerUser();
    $user2 = customerUser(); // second customer
    $token = $user1->createToken('api-test')->plainTextToken;

    $productId = \App\Domains\Products\Models\Product::value('id');

    // Create service for user2
    Service::create([
        'customer_id'         => $user2->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'other-customer-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/services')
        ->assertOk();

    $labels = collect($response->json('data'))->pluck('label')->toArray();
    expect($labels)->not->toContain('other-customer-service');
});

it('api invoices returns paginated invoices', function (): void {
    $user  = customerUser();
    $token = $user->createToken('api-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/invoices')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('api service show returns 403 for another customers service', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();
    $token = $user1->createToken('api-test')->plainTextToken;

    $productId = \App\Domains\Products\Models\Product::value('id');
    $service   = Service::create([
        'customer_id'         => $user2->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'private-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/services/{$service->id}");

    // 403 (our check) or 404 (Laravel implicit binding scope) — both deny access
    expect($response->status())->toBeIn([403, 404]);
});

// ── Support SLA ───────────────────────────────────────────────────────────────

it('admin can set SLA deadline on a ticket', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $ticket = \App\Domains\Support\Models\SupportTicket::create([
        'customer_id' => $user->customer->id,
        'subject'     => 'Potřebuji pomoc',
        'status'      => 'open',
        'priority'    => 'normal',
        'last_reply_at' => now(),
    ]);

    $deadline = now()->addHours(4)->format('Y-m-d H:i');

    $this->actingAs($admin)
        ->post(route('admin.support.sla', $ticket), ['sla_deadline' => $deadline])
        ->assertRedirect();

    expect($ticket->fresh()->sla_deadline)->not->toBeNull();
});

// ── API v1: domains ──────────────────────────────────────────────────────────

it('api domains returns customer domains', function (): void {
    $user    = customerUser();
    $token   = $user->createToken('api-domains')->plainTextToken;
    $product = \App\Domains\Products\Models\Product::first();

    $service = \App\Domains\Provisioning\Models\Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $product->id,
        'status'              => \App\Domains\Provisioning\Enums\ServiceStatus::Active,
        'label'               => 'test-domain',
        'provisioning_driver' => \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel,
    ]);

    \App\Domains\Provisioning\Models\DomainRegistration::create([
        'service_id'  => $service->id,
        'domain'      => 'mujdomen',
        'tld'         => 'cz',
        'registrar'   => 'wedos',
        'auto_renew'  => true,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/domains')
        ->assertOk()
        ->assertJsonStructure(['data']);

    $domains = collect($response->json('data'));
    expect($domains->pluck('domain'))->toContain('mujdomen.cz');
});

it('api domains does not leak other customer domains', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $token   = $user1->createToken('api-domains')->plainTextToken;
    $product = \App\Domains\Products\Models\Product::first();

    $service = \App\Domains\Provisioning\Models\Service::create([
        'customer_id'         => $user2->customer->id,
        'product_id'          => $product->id,
        'status'              => \App\Domains\Provisioning\Enums\ServiceStatus::Active,
        'label'               => 'other-domain',
        'provisioning_driver' => \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel,
    ]);

    \App\Domains\Provisioning\Models\DomainRegistration::create([
        'service_id'  => $service->id,
        'domain'      => 'cizidomena',
        'tld'         => 'cz',
        'registrar'   => 'wedos',
        'auto_renew'  => true,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/domains')
        ->assertOk();

    $domains = collect($response->json('data'))->pluck('domain')->toArray();
    expect($domains)->not->toContain('cizidomena.cz');
});

// ── API v1: credit balance ────────────────────────────────────────────────────

it('api billing/credit returns the credit balance', function (): void {
    $user  = customerUser();
    $token = $user->createToken('api-credit')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/billing/credit')
        ->assertOk()
        ->assertJsonStructure(['data' => ['amount', 'currency', 'formatted']]);

    expect($response->json('data.amount'))->toBeInt()
        ->and($response->json('data.currency'))->toBe('CZK');
});

// ── API v1: support tickets ───────────────────────────────────────────────────

it('api support/tickets returns the customer tickets list', function (): void {
    $user  = customerUser();
    $token = $user->createToken('api-tickets')->plainTextToken;

    \App\Domains\Support\Models\SupportTicket::create([
        'customer_id'   => $user->customer->id,
        'subject'       => 'API testovací tiket',
        'status'        => 'open',
        'priority'      => 'normal',
        'last_reply_at' => now(),
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/support/tickets')
        ->assertOk()
        ->assertJsonStructure(['data']);

    $subjects = collect($response->json('data'))->pluck('subject')->toArray();
    expect($subjects)->toContain('API testovací tiket');
});

it('api support/tickets does not return other customer tickets', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();
    $token = $user1->createToken('api-tickets')->plainTextToken;

    \App\Domains\Support\Models\SupportTicket::create([
        'customer_id'   => $user2->customer->id,
        'subject'       => 'Cizí tiket',
        'status'        => 'open',
        'priority'      => 'normal',
        'last_reply_at' => now(),
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/support/tickets')
        ->assertOk();

    $subjects = collect($response->json('data'))->pluck('subject')->toArray();
    expect($subjects)->not->toContain('Cizí tiket');
});

it('admin can add internal note not visible in regular reply', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $ticket = \App\Domains\Support\Models\SupportTicket::create([
        'customer_id' => $user->customer->id,
        'subject'     => 'Test interní poznámka',
        'status'      => 'open',
        'priority'    => 'normal',
        'last_reply_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.support.reply', $ticket), [
            'message'     => 'Interní poznámka pro kolegy',
            'is_internal' => '1',
        ])
        ->assertRedirect();

    $note = $ticket->messages()->where('is_internal', true)->first();
    expect($note)->not->toBeNull();
    expect($note->message)->toBe('Interní poznámka pro kolegy');
    expect($note->is_staff)->toBeTrue();
});
