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

/** POST a GraphQL query with a bearer token. */
function gql(string $token, string $query, array $variables = []): \Illuminate\Testing\TestResponse
{
    return test()->withToken($token)->postJson('/api/graphql', [
        'query'     => $query,
        'variables' => $variables,
    ]);
}

it('rejects an unauthenticated GraphQL request', function (): void {
    $this->postJson('/api/graphql', ['query' => '{ viewer { id } }'])->assertUnauthorized();
});

it('rejects an empty query', function (): void {
    $token = customerUser()->createToken('gql')->plainTextToken;

    $this->withToken($token)->postJson('/api/graphql', ['query' => ''])->assertStatus(400);
});

it('resolves the viewer scoped to the token owner', function (): void {
    $user  = customerUser();
    $token = $user->createToken('gql')->plainTextToken;

    gql($token, '{ viewer { id email customer { preferredCurrency } } }')
        ->assertOk()
        ->assertJsonPath('data.viewer.id', $user->id)
        ->assertJsonPath('data.viewer.email', $user->email);
});

it('resolves the customer services', function (): void {
    $user      = customerUser();
    $token     = $user->createToken('gql')->plainTextToken;
    $productId = \App\Domains\Products\Models\Product::value('id');

    Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'gql-service',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $response = gql($token, '{ services { label status } }')->assertOk();

    $labels = collect($response->json('data.services'))->pluck('label')->all();
    expect($labels)->toContain('gql-service');
});

it('never leaks another customer\'s services through GraphQL', function (): void {
    $mine  = customerUser();
    $other = customerUser();
    $token = $mine->createToken('gql')->plainTextToken;
    $productId = \App\Domains\Products\Models\Product::value('id');

    Service::create([
        'customer_id'         => $other->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'not-mine',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $response = gql($token, '{ services { label } }')->assertOk();

    $labels = collect($response->json('data.services'))->pluck('label')->all();
    expect($labels)->not->toContain('not-mine');
});

it('resolves the credit balance', function (): void {
    $token = customerUser()->createToken('gql')->plainTextToken;

    gql($token, '{ creditBalance { amount currency formatted } }')
        ->assertOk()
        ->assertJsonStructure(['data' => ['creditBalance' => ['amount', 'currency', 'formatted']]]);
});

it('returns a GraphQL error (not a 500) for an unknown field', function (): void {
    $token = customerUser()->createToken('gql')->plainTextToken;

    gql($token, '{ viewer { doesNotExist } }')
        ->assertOk()
        ->assertJsonStructure(['errors']);
});

it('rejects an over-deep query with a validation error', function (): void {
    $token = customerUser()->createToken('gql')->plainTextToken;

    // Introspection nested past the depth limit (10) trips the QueryDepth rule.
    $deep = '{ __schema { types { fields { type { ofType { ofType { ofType { ofType { ofType { ofType { ofType { ofType { ofType { ofType { name } } } } } } } } } } } } } } }';

    gql($token, $deep)
        ->assertOk()
        ->assertJsonStructure(['errors']);
});

// ── mutations ─────────────────────────────────────────────────────────────────

it('creates a support ticket via mutation with the write:tickets ability', function (): void {
    $user  = customerUser();
    $token = $user->createToken('gql', ['write:tickets'])->plainTextToken;

    $res = gql($token, 'mutation { createTicket(subject: "Pomoc", message: "Prosím o pomoc") { id subject status } }')
        ->assertOk();

    expect($res->json('data.createTicket.subject'))->toBe('Pomoc')
        ->and($res->json('data.createTicket.id'))->toBeGreaterThan(0);

    $this->assertDatabaseHas('support_tickets', ['customer_id' => $user->customer->id, 'subject' => 'Pomoc']);
});

it('refuses a mutation from a token without write:tickets', function (): void {
    $token = customerUser()->createToken('gql', ['read'])->plainTextToken;

    gql($token, 'mutation { createTicket(subject: "X", message: "Y") { id } }')
        ->assertOk()
        ->assertJsonStructure(['errors'])
        ->assertJsonPath('data.createTicket', null);
});

it('replies to a ticket via mutation', function (): void {
    $user  = customerUser();
    $token = $user->createToken('gql', ['write:tickets'])->plainTextToken;

    $id = gql($token, 'mutation { createTicket(subject: "S", message: "M") { id } }')->json('data.createTicket.id');

    gql($token, 'mutation { replyTicket(ticketId: ' . $id . ', message: "Doplnění") { id status } }')
        ->assertOk()
        ->assertJsonPath('data.replyTicket.id', $id);
});

it('cannot reply to another customer\'s ticket', function (): void {
    $mine  = customerUser();
    $other = customerUser();
    $token = $mine->createToken('gql', ['write:tickets'])->plainTextToken;

    $otherId = app(\App\Domains\Support\Services\TicketService::class)
        ->open($other->customer, $other, 'Cizí', 'tiket')->id;

    gql($token, 'mutation { replyTicket(ticketId: ' . $otherId . ', message: "hack") { id } }')
        ->assertOk()
        ->assertJsonStructure(['errors'])
        ->assertJsonPath('data.replyTicket', null);
});

// ── persisted queries (Apollo APQ) ────────────────────────────────────────────

it('returns PersistedQueryNotFound for an unknown hash', function (): void {
    $token = customerUser()->createToken('gql')->plainTextToken;

    test()->withToken($token)->postJson('/api/graphql', [
        'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => str_repeat('a', 64)]],
    ])->assertOk()->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_NOT_FOUND');
});

it('registers a persisted query then serves it by hash alone', function (): void {
    $user  = customerUser();
    $token = $user->createToken('gql')->plainTextToken;
    $q     = '{ viewer { id } }';
    $hash  = hash('sha256', $q);

    // 1) register: hash + query
    test()->withToken($token)->postJson('/api/graphql', [
        'query'      => $q,
        'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]],
    ])->assertOk()->assertJsonPath('data.viewer.id', $user->id);

    // 2) subsequent call: hash only, no query text
    test()->withToken($token)->postJson('/api/graphql', [
        'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => $hash]],
    ])->assertOk()->assertJsonPath('data.viewer.id', $user->id);
});

it('rejects a persisted-query hash that does not match the query', function (): void {
    $token = customerUser()->createToken('gql')->plainTextToken;

    test()->withToken($token)->postJson('/api/graphql', [
        'query'      => '{ viewer { id } }',
        'extensions' => ['persistedQuery' => ['version' => 1, 'sha256Hash' => str_repeat('b', 64)]],
    ])->assertOk()->assertJsonPath('errors.0.extensions.code', 'PERSISTED_QUERY_HASH_MISMATCH');
});

// ── expanded read schema ──────────────────────────────────────────────────────

it('exposes the loyalty points balance on the viewer', function (): void {
    $user = customerUser();
    app(\App\Domains\Loyalty\Services\LoyaltyPointsService::class)->award($user->customer, 175, 'seed');
    $token = $user->createToken('gql')->plainTextToken;

    gql($token, '{ viewer { loyaltyPoints } }')
        ->assertOk()
        ->assertJsonPath('data.viewer.loyaltyPoints', 175);
});

it('lists the customer tickets, scoped to the account', function (): void {
    $mine  = customerUser();
    $other = customerUser();
    $token = $mine->createToken('gql')->plainTextToken;

    $svc = app(\App\Domains\Support\Services\TicketService::class);
    $svc->open($mine->customer, $mine, 'Můj tiket', 'text');
    $svc->open($other->customer, $other, 'Cizí tiket', 'text');

    $res = gql($token, '{ tickets { id subject status } }')->assertOk();

    $subjects = collect($res->json('data.tickets'))->pluck('subject')->all();
    expect($subjects)->toContain('Můj tiket')->not->toContain('Cizí tiket');
});

it('lists the customer orders, scoped to the account', function (): void {
    $mine  = customerUser();
    $other = customerUser();
    $token = $mine->createToken('gql')->plainTextToken;

    $makeOrder = fn (int $customerId) => \App\Domains\Billing\Models\Order::create([
        'customer_id'  => $customerId,
        'status'       => \App\Domains\Billing\Enums\OrderStatus::Pending,
        'currency'     => 'CZK',
        'subtotal'     => \Brick\Money\Money::of(826, 'CZK'),
        'tax_amount'   => \Brick\Money\Money::of(173, 'CZK'),
        'total'        => \Brick\Money\Money::of(999, 'CZK'),
        'vat_scenario' => 'domestic',
    ]);
    $mineOrder  = $makeOrder($mine->customer->id);
    $otherOrder = $makeOrder($other->customer->id);

    $res = gql($token, '{ orders { id status total } }')->assertOk();

    $ids = collect($res->json('data.orders'))->pluck('id')->all();
    expect($ids)->toContain($mineOrder->id)->not->toContain($otherOrder->id);
});
