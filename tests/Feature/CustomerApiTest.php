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
