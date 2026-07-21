<?php

declare(strict_types=1);

use App\Domains\Developer\Models\OAuthApplication;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Phase J (J138): the developer portal.
 *
 * A portal that lists tokens is not the deliverable — the deliverable is that
 * a developer can go from nothing to a working API call and back to a revoked
 * credential without contacting support. These tests walk that whole path.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('shows the portal to a signed-in customer', function (): void {
    $this->actingAs(customerUser())
        ->get(route('panel.developer.index'))
        ->assertOk()
        ->assertSee('API token', false);
});

it('takes a developer from no credentials to a working API call', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->post(route('panel.account.api-tokens.store'), [
            'name'      => 'Moje integrace',
            'abilities' => ['read'],
        ])
        ->assertRedirect();

    $plain = session('new_token');
    expect($plain)->toBeString()->not->toBeEmpty();

    // The token the portal just handed over must actually authenticate.
    // Drop the session identity first, so this proves the TOKEN works rather
    // than riding on the cookie left behind by actingAs().
    $this->app['auth']->forgetGuards();

    $this->withToken($plain)->getJson('/api/v1/services')->assertSuccessful();
});

it('shows the plain token exactly once', function (): void {
    $user = customerUser();

    $this->actingAs($user)->post(route('panel.account.api-tokens.store'), ['name' => 'Jednorázový']);
    $plain = (string) session('new_token');

    // The redirect target is the one-time display — the token is flashed, so
    // this request is where the developer copies it.
    $this->actingAs($user)->get(route('panel.developer.index'))
        ->assertOk()
        ->assertSee($plain, false);

    // Any later load must not re-expose it; only the hash was ever stored.
    $this->actingAs($user)->get(route('panel.developer.index'))
        ->assertOk()
        ->assertDontSee($plain, false);

    expect($user->tokens()->first()->token)->not->toBe($plain);
});

it('always grants read so a token is never born useless', function (): void {
    $user = customerUser();

    $this->actingAs($user)->post(route('panel.account.api-tokens.store'), [
        'name'      => 'Jen zápis',
        'abilities' => ['write:tickets'],
    ]);

    expect($user->tokens()->first()->abilities)->toContain('read')
        ->and($user->tokens()->first()->abilities)->toContain('write:tickets');
});

it('refuses abilities that are not on the allowlist', function (): void {
    $this->actingAs(customerUser())
        ->post(route('panel.account.api-tokens.store'), [
            'name'      => 'Příliš mocný',
            'abilities' => ['*'],
        ])
        ->assertSessionHasErrors('abilities.0');
});

it('caps the number of tokens per user', function (): void {
    $user = customerUser();

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)->post(route('panel.account.api-tokens.store'), ['name' => "Token {$i}"]);
    }

    $this->actingAs($user)
        ->post(route('panel.account.api-tokens.store'), ['name' => 'Šestý'])
        ->assertSessionHasErrors('name');

    expect($user->tokens()->count())->toBe(5);
});

it('makes revocation take effect against the API immediately', function (): void {
    $user = customerUser();

    $this->actingAs($user)->post(route('panel.account.api-tokens.store'), ['name' => 'Ke zrušení']);
    $plain   = (string) session('new_token');
    $tokenId = $user->tokens()->first()->id;

    $this->actingAs($user)
        ->delete(route('panel.account.api-tokens.destroy', $tokenId))
        ->assertRedirect();

    // Revoking in the UI but leaving the credential live would be worse than
    // having no revoke button, because the developer believes they are safe.
    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $this->withToken($plain)->getJson('/api/v1/services')->assertUnauthorized();
});

it('does not let one customer revoke another customer\'s token', function (): void {
    $owner     = customerUser();
    $stranger  = customerUser();
    $ownerToken = $owner->createToken('cizí', ['read']);

    $this->actingAs($stranger)
        ->delete(route('panel.account.api-tokens.destroy', $ownerToken->accessToken->id))
        ->assertRedirect();

    expect($owner->tokens()->count())->toBe(1);
});

// ── OAuth applications ────────────────────────────────────────────────────────

it('creates an OAuth application and reveals the secret once', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.developer.oauth-apps.store'), [
            'name'          => 'Partnerská aplikace',
            'redirect_uris' => "https://example.test/callback\nhttps://example.test/alt",
        ])
        ->assertRedirect();

    $secret = (string) session('new_secret');
    expect($secret)->not->toBeEmpty();

    $app = OAuthApplication::where('customer_id', $user->customer->id)->firstOrFail();

    expect($app->redirect_uris)->toBe(['https://example.test/callback', 'https://example.test/alt'])
        // Stored hashed: a leaked database must not yield usable secrets.
        ->and($app->client_secret_hash)->not->toBe($secret)
        ->and(Hash::check($secret, $app->client_secret_hash))->toBeTrue();
});

it('rotates a client secret to a genuinely new value', function (): void {
    $user = customerUser();

    $this->actingAs($user)->post(route('panel.developer.oauth-apps.store'), ['name' => 'Rotace']);
    $first = (string) session('new_secret');

    $app = OAuthApplication::where('customer_id', $user->customer->id)->firstOrFail();

    $this->actingAs($user)
        ->patch(route('panel.developer.oauth-apps.regen', $app))
        ->assertRedirect();

    $second = (string) session('new_secret');

    expect($second)->not->toBe($first)
        ->and(Hash::check($second, $app->fresh()->client_secret_hash))->toBeTrue()
        // The point of rotation is that the old secret stops working.
        ->and(Hash::check($first, $app->fresh()->client_secret_hash))->toBeFalse();
});

it('refuses to touch another customer\'s OAuth application', function (): void {
    $owner    = customerUser();
    $stranger = customerUser();

    $this->actingAs($owner)->post(route('panel.developer.oauth-apps.store'), ['name' => 'Cizí aplikace']);
    $app = OAuthApplication::where('customer_id', $owner->customer->id)->firstOrFail();

    $this->actingAs($stranger)->patch(route('panel.developer.oauth-apps.regen', $app))->assertForbidden();
    $this->actingAs($stranger)->delete(route('panel.developer.oauth-apps.destroy', $app))->assertForbidden();

    expect(OAuthApplication::whereKey($app->id)->exists())->toBeTrue();
});

it('caps the number of OAuth applications', function (): void {
    $user = customerUser();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)->post(route('panel.developer.oauth-apps.store'), ['name' => "App {$i}"]);
    }

    $this->actingAs($user)
        ->post(route('panel.developer.oauth-apps.store'), ['name' => 'Jedenáctá'])
        ->assertSessionHasErrors('name');

    expect(OAuthApplication::where('customer_id', $user->customer->id)->count())->toBe(10);
});

it('links the portal to the API reference', function (): void {
    // The portal is where a developer starts; a dead end there means a ticket.
    $this->actingAs(customerUser())
        ->get(route('panel.developer.index'))
        ->assertOk()
        ->assertSee(route('api.docs'), false);
});
