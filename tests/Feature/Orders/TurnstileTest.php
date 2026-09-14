<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\OrderRiskService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Risk\Turnstile;

/*
 * Cloudflare Turnstile (audit §5q-6): registration refuses a missing or failed check while enforced, checkout scores
 * it as one more risk signal, the surfaces get the site key with the boot object and the CSP lets the widget load.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Http::fake(['https://api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

it('refuses registration without a valid check, scores checkout, and hands the site key to the surfaces', function () {
    $turnstile = app(Turnstile::class);
    expect($turnstile->enabled())->toBeFalse()->and($turnstile->check(HttpRequest::create('/x')))->toBe('off');
    $payload = ['name' => 'Robot Ruda', 'email' => 'ruda@example.cz', 'password' => 'Velmi-dlouhe-heslo-2026', 'terms' => true];
    $this->postJson('/v1/auth/register', $payload)->assertStatus(201); // off: nothing asked

    config()->set('onhost.turnstile.site_key', '1x00000000000000000000AA');
    config()->set('onhost.turnstile.secret', '1x0000000000000000000000000000000AA');
    Http::fake([
        'https://api.pwnedpasswords.com/*' => Http::response('', 200),
        Turnstile::VERIFY_URL => Http::sequence()->push(['success' => false, 'error-codes' => ['invalid-input-response']], 200)->push(['success' => true, 'hostname' => 'onhost.cz'], 200)->push(['success' => false], 200),
    ]);
    expect($turnstile->enabled())->toBeTrue();
    $this->postJson('/v1/auth/register', ['email' => 'r2@example.cz'] + $payload)->assertStatus(422)->assertJsonPath('error', 'turnstile_required')->assertJsonPath('result', 'missing');
    $this->postJson('/v1/auth/register', ['email' => 'r2@example.cz', 'turnstile' => 'bad-token'] + $payload)->assertStatus(422)->assertJsonPath('result', 'fail');
    $this->withHeader('CF-Turnstile-Response', 'good-token')->postJson('/v1/auth/register', ['email' => 'r2@example.cz'] + $payload)->assertStatus(201);
    Http::assertSent(fn (Request $r) => $r->url() === Turnstile::VERIFY_URL && $r['response'] === 'good-token' && $r['secret'] === '1x0000000000000000000000000000000AA' && $r->isForm());
    expect(User::query()->where('email', 'r2@example.cz')->exists())->toBeTrue();
    config()->set('onhost.turnstile.enforce_register', false);
    $this->postJson('/v1/auth/register', ['email' => 'r3@example.cz', 'turnstile' => 'meh'] + $payload)->assertStatus(201); // scored only, never refused

    // checkout: a failed or missing check is a risk signal with its own weight, a pass is not
    [$owner, $org] = $this->customerWithOrganization(['email' => 'petra@shop.cz']);
    $risk = app(OrderRiskService::class);
    expect($risk->tuning()['defaults'])->toHaveKey('turnstile_failed')->and($risk->weights()['turnstile_failed'])->toBe(35);
    $quote = app(QuoteService::class)->quote([['product_key' => 'game', 'plan_key' => 'game-8', 'config' => ['egg' => 'minecraft-spigot']]], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
    $ctx = $this->contextFor($owner, $org);
    expect($turnstile->check(HttpRequest::create('/v1/orders', 'POST')))->toBe('missing');
    $missing = $risk->assess($quote, $org, $owner, $ctx);
    expect($missing['reasons'])->toContain('turnstile_failed');
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true], 200)]);
    expect($turnstile->check(HttpRequest::create('/v1/orders', 'POST', ['turnstile' => 'ok'])))->toBe('pass');
    expect($risk->assess($quote, $org, $owner, $ctx)['reasons'])->not->toContain('turnstile_failed');
    expect($missing['score'] - $risk->assess($quote, $org, $owner, $ctx)['score'])->toBe(35);
    Http::fake([Turnstile::VERIFY_URL => fn () => throw new RuntimeException('verifier down')]);
    expect($turnstile->verify('token', '10.0.0.1'))->toBe('pass'); // the verifier being down never punishes customers

    // the surfaces: the site key rides on the boot object, the CSP lets the widget load, the bridge sends the token
    $html = $this->get('/')->assertOk();
    expect($html->getContent())->toContain('"turnstile":"1x00000000000000000000AA"');
    $csp = (string) $html->headers->get('Content-Security-Policy');
    expect($csp)->toContain('script-src')->toContain('https://challenges.cloudflare.com')->toContain('frame-src https://challenges.cloudflare.com');
    $bridge = (string) file_get_contents(base_path('apps/surfaces/api/onhost-session-bridge.js'));
    expect($bridge)->toContain('turnstile: turnstileToken()')->toContain('challenges.cloudflare.com/turnstile/v0/api.js?render=explicit')->toContain("appearance: 'interaction-only'");
    config()->set('onhost.turnstile.site_key', '');
    $off = $this->get('/')->assertOk();
    expect($off->getContent())->toContain('"turnstile":null')->and((string) $off->headers->get('Content-Security-Policy'))->not->toContain('challenges.cloudflare.com');
});
