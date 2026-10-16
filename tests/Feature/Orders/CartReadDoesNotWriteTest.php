<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Cart;

/*
 * W7-0: GET /v1/cart only reads. Without an open cart it answers an empty virtual cart (no id, no token) and the carts table
 * stays empty; a token is issued only by PUT /v1/cart (apps/surfaces/api/onhost-cart.api.js takes it from the PUT answer).
 */
beforeEach(function () {
    $this->seed([CatalogSeeder::class]);
});

/** @return list<array{line_id: string, product_key: string, plan_key: string}> */
function crdItems(): array
{
    return [['line_id' => 'crd1', 'product_key' => 'web-hosting', 'plan_key' => 'start']];
}

function crdAssertVirtual(mixed $data): void
{
    expect($data)->toBe(['id' => null, 'token' => null, 'items' => [], 'commit_months' => 1, 'currency' => 'CZK', 'promo_code' => null, 'loyalty_points' => null, 'expires_at' => null]);
}

it('answers an anonymous read without a token with a virtual cart and writes nothing', function () {
    crdAssertVirtual($this->getJson('/v1/cart')->assertOk()->json('data'));

    expect(Cart::query()->count())->toBe(0);
});

it('answers an anonymous read with a malformed token with a virtual cart and writes nothing', function () {
    crdAssertVirtual($this->withHeaders(['X-Cart-Token' => 'short!'])->getJson('/v1/cart')->assertOk()->json('data'));

    expect(Cart::query()->count())->toBe(0);
});

it('does not create a cart for a well-formed token nobody holds', function () {
    crdAssertVirtual($this->withHeaders(['X-Cart-Token' => Str::random(40)])->getJson('/v1/cart')->assertOk()->json('data'));

    expect(Cart::query()->count())->toBe(0);
});

it('returns the items of an existing guest cart by its token', function () {
    $token = $this->putJson('/v1/cart', ['items' => crdItems()])->assertOk()->json('data.token');
    expect($token)->toBeString();

    $data = $this->withHeaders(['X-Cart-Token' => $token])->getJson('/v1/cart')->assertOk()->json('data');
    expect($data['token'])->toBe($token)
        ->and($data['id'])->not->toBeNull()
        ->and($data['items'][0]['plan_key'])->toBe('start')
        ->and(Cart::query()->count())->toBe(1);
});

it('does not create a cart for a signed-in user who has none', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    crdAssertVirtual($this->getJson('/v1/cart')->assertOk()->json('data'));
    expect(Cart::query()->count())->toBe(0);
});

it('still creates a cart and issues a token on PUT without a token', function () {
    $data = $this->putJson('/v1/cart', ['items' => crdItems()])->assertOk()->json('data');

    expect($data['token'])->toBeString()->toMatch('/^[A-Za-z0-9_-]{16,80}$/')
        ->and(Cart::query()->where('session_token', $data['token'])->where('state', 'open')->count())->toBe(1);
});
