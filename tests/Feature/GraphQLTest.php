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
