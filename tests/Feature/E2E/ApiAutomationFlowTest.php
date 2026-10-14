<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Notifications\Webhooks\WebhookCommandHandler;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\IdempotencyStore;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E9 — the automation surface of a customer, touching only the real HTTP routes: a personal API token, a service account and a webhook.
 *
 * An owner signs up, steps up and issues a personal token with scopes; the token reads the services and nothing it was not given, is
 * refused on staff routes and on HIGH actions, replays an idempotent write and is told 409 while the first copy still runs; revoking it
 * ends it at the next request. The owner (and only the owner) makes a service account and its token: it acts as `service_account` for its
 * own organization and for no other, is not a person (`person_required`), and stops at once when revoked. A webhook endpoint is created
 * (https on 443, step-up), pinged (cooldown), and receives a REAL customer event — `service.activated` from an order paid through the
 * gateway and provisioned on a stateful fake panel — signed `v1=HMAC(secret, "ts.body")` with the public allow-listed payload only; a
 * delivery is redelivered a bounded number of times, and an endpoint that keeps failing is suspended and its customer told.
 * Every route touched is in the OpenAPI contract, with the x-token-scope the middleware really demands.
 *
 * Only the edges are doubles: gateway, hosting panel and the webhook receiver (Http::fake), the mail (Notification::fake). Setup that
 * has no route (a member of an organization, an exhausted failure counter, a reserved idempotency key) says so where it happens.
 * Asserts do not depend on row order, so the flow holds on SQLite and PostgreSQL alike. Helpers: tests/Support/E2E/E2EHelpers.php.
 */

beforeEach(function () {
    e2eSeedPlatform();
    e2eWebInfrastructure();
    e2eComgateEnvironment();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
    LaravelNotification::fake();
    apiFlowCalled(reset: true);
});

/**
 * The routes this test has touched: `["METHOD uri" => expectation]`. Expectation: a token scope the call needed and got (it must be named by the
 * contract), `portal` (a call a token was refused: the contract must say "not for tokens"), or `any` (the route just has to be in the contract).
 *
 * @return array<string,string>
 */
function apiFlowCalled(?string $key = null, string $expect = 'any', bool $reset = false): array
{
    static $called = [];
    if ($reset) {
        $called = [];
    }
    if ($key !== null) {
        $called[$key] = $called[$key] ?? $expect;
        if ($expect !== 'any' && $called[$key] === 'any') {
            $called[$key] = $expect;
        }
    }

    return $called;
}

/** One request over the real routes; the route it reached is remembered for the contract check. */
function apiFlowCall(object $test, string $method, string $uri, array $body = [], array $headers = [], string $expect = 'any'): TestResponse
{
    if (in_array(strtoupper($method), ['POST', 'PATCH', 'PUT', 'DELETE'], true) && ! isset($headers['Idempotency-Key'])) {
        $headers['Idempotency-Key'] = 'e9-'.bin2hex(random_bytes(8)); // a key sticks to the client once sent (e2eStepUp sends one): never reuse it for another body
    }
    $response = $test->json($method, $uri, $body, $headers);
    $route = app('router')->current();
    if ($route !== null) {
        apiFlowCalled(strtoupper($method).' /'.$route->uri(), $expect);
    }

    return $response;
}

/** The test client as a stranger: no session, no bearer, no cookie of an earlier actor. */
function apiFlowReset(object $test): void
{
    app('auth')->forgetGuards();
    $test->flushHeaders();
    $test->flushSession();
    (function () {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials = false;
    })->call($test);
}

/** Sign in over POST /v1/auth/login as a browser would (a new session), optionally with a fresh step-up. */
function apiFlowOwner(object $test, string $email, string $password, bool $stepUp = true): void
{
    apiFlowReset($test);
    $test->travel(61)->seconds(); // the sign-in routes allow 5 attempts a minute per address: a new minute for each actor switch
    $test->withHeaders(e2eHeaders('login'))->postJson('/v1/auth/login', ['email' => $email, 'password' => $password])->assertOk();
    if ($stepUp) {
        e2eStepUp($test, $password);
    }
}

/** From here on every request carries only this bearer token (no Referer: not the portal's session). */
function apiFlowToken(object $test, string $plain): void
{
    apiFlowReset($test);
    $test->withToken($plain);
}

/** Owner headers for a write: the browser's Referer and a fresh Idempotency-Key. */
function apiFlowH(string $label): array
{
    return e2eHeaders($label);
}

/** A fresh Idempotency-Key and nothing else — for a token's write (a bearer is not a browser). */
function apiFlowKey(string $label): array
{
    return ['Idempotency-Key' => 'e9-'.$label.'-'.bin2hex(random_bytes(6))];
}

/** Panel + gateway on one Http fake stack, and a webhook receiver whose answer a test flips through `$receiver['status']`. @param array<string,mixed> $panel */
function apiFlowDoubles(array &$panel, array &$gate, array &$receiver): void
{
    $receiver += ['status' => 204, 'seen' => []];
    e2eIspPanel($panel);
    e2eComgateFake($gate);
    Http::fake(function (HttpRequest $request) use (&$receiver) {
        if (! str_contains($request->url(), 'hooks.example.cz')) {
            return null;
        }
        $receiver['seen'][] = $request;

        return Http::response($receiver['status'] === 204 ? '' : 'receiver says no', $receiver['status']);
    });
}

/** Order a web hosting for `$fqdn`, pay it through the gateway, let the saga run. Signed in as the owner. */
function apiFlowWebService(object $test, Organization $org, array &$gate, string $fqdn): Service
{
    $test->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => $fqdn]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $test->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $test->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending(); // the events the saga wrote while it ran (service.activated)
    $service = Service::query()->where('organization_id', $org->id)->where('hostname', $fqdn)->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE);

    return $service;
}

/** A personal token as the owner (step-up on file): POST /v1/tokens. @return array{0:string,1:string} plaintext, id */
function apiFlowPersonalToken(object $test, array $scopes, string $name = 'ci'): array
{
    $created = apiFlowCall($test, 'POST', '/v1/tokens', ['name' => $name, 'scopes' => $scopes], apiFlowH('token-'.$name), 'portal')->assertCreated();

    return [(string) $created->json('token'), (string) $created->json('id')];
}

/** Every route this test touched is in the OpenAPI contract, with the scope the middleware demanded. */
function apiFlowAssertContract(): void
{
    $contract = Yaml::parseFile(base_path('contracts/openapi/onhost-v1.yaml'));
    $called = apiFlowCalled();
    expect($called)->not->toBeEmpty();
    foreach ($called as $call => $expect) {
        [$method, $uri] = explode(' ', $call, 2);
        $operation = $contract['paths'][substr($uri, 3)][strtolower($method)] ?? null; // "/v1/…" → the contract's "/…" (servers: /v1)
        expect($operation)->not->toBeNull("{$call} is not in contracts/openapi/onhost-v1.yaml");
        $scopes = $operation['x-token-scope'] ?? null;
        if (str_contains($expect, ':')) {
            expect($scopes)->toBeArray("{$call}: x-token-scope")->toContain($expect);
        } elseif ($expect === 'portal') {
            expect($scopes ?? [])->toBe([], "{$call} is refused to tokens, so the contract must not offer it to them");
        }
    }
}

it('lets the owner issue a scoped personal token that reads what it was given and is refused everything else', function () {
    $panel = $gate = $receiver = [];
    apiFlowDoubles($panel, $gate, $receiver);
    [$owner, $org, $password] = e2eSignUp($this, 'owner.token@example.cz');
    $service = apiFlowWebService($this, $org, $gate, 'token-e9.cz');

    // 1. a token is a HIGH action: no step-up, no token
    apiFlowCall($this, 'POST', '/v1/tokens', ['name' => 'ci', 'scopes' => ['services:read']], apiFlowH('no-stepup'), 'portal')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(PersonalAccessToken::query()->where('tokenable_id', $owner->id)->count())->toBe(0);
    e2eStepUp($this, $password);
    $key = 'e9-token-create-1';
    $created = apiFlowCall($this, 'POST', '/v1/tokens', ['name' => 'ci', 'scopes' => ['services:read', 'tickets:write']], ['Referer' => 'http://localhost', 'Idempotency-Key' => $key], 'portal')->assertCreated();
    $plain = (string) $created->json('token');
    $tokenId = (string) $created->json('id');
    expect($plain)->toContain('|onh_live_')->and($created->json('scopes'))->toBe(['services:read', 'tickets:write'])->and($created->json('expires_at'))->not->toBeNull();
    // the secret was shown once: repeating the request does not hand it out again
    apiFlowCall($this, 'POST', '/v1/tokens', ['name' => 'ci', 'scopes' => ['services:read', 'tickets:write']], ['Referer' => 'http://localhost', 'Idempotency-Key' => $key], 'portal')->assertStatus(409)->assertJsonPath('error', 'already_done');
    $listed = apiFlowCall($this, 'GET', '/v1/tokens', [], apiFlowH('tokens'), 'portal')->assertOk();
    expect($listed->json('data'))->toHaveCount(1)->and($listed->getContent())->not->toContain(explode('|', $plain)[1] ?? $plain);

    // 2. the token reads the services — and the answer names the API version
    apiFlowToken($this, $plain);
    $services = apiFlowCall($this, 'GET', '/v1/services', [], [], TokenScopes::SERVICES_READ)->assertOk()->assertHeader('X-API-Version', (string) config('onhost.api.version'));
    expect(collect($services->json('data'))->pluck('id')->all())->toBe([$service->id]);
    apiFlowCall($this, 'GET', "/v1/services/{$service->id}", [], [], TokenScopes::SERVICES_READ)->assertOk()->assertJsonPath('data.id', $service->id);
    apiFlowCall($this, 'GET', '/v1/me', [], [])->assertOk()->assertJsonPath('data.user.id', $owner->id)->assertHeader('X-API-Version', (string) config('onhost.api.version'));

    // 3. what it was not given is 403, and says which scope is missing
    apiFlowCall($this, 'GET', '/v1/invoices', [], [])->assertForbidden()->assertJsonPath('message', 'The API token lacks the invoices:read scope.');
    apiFlowCall($this, 'POST', "/v1/services/{$service->id}/actions", ['action' => 'power', 'params' => ['state' => 'restart']], apiFlowKey('power'))->assertForbidden();
    expect(DB::table('operations')->where('service_id', $service->id)->where('kind', 'like', '%power%')->count())->toBe(0);
    // a token never manages tokens or service accounts, edits the account, or reaches the staff console
    apiFlowCall($this, 'GET', '/v1/tokens', [], [], 'portal')->assertForbidden();
    apiFlowCall($this, 'POST', '/v1/tokens', ['name' => 'more', 'scopes' => ['services:read']], apiFlowKey('more'), 'portal')->assertForbidden();
    apiFlowCall($this, 'GET', '/v1/service-accounts', [], [], 'portal')->assertForbidden();
    apiFlowCall($this, 'PATCH', '/v1/me', ['name' => 'Taken Over'], apiFlowKey('me'), 'portal')->assertForbidden();
    apiFlowCall($this, 'GET', '/v1/webhooks', [], [], 'portal')->assertForbidden();
    apiFlowCall($this, 'GET', '/v1/staff/customers', [], [], 'portal')->assertForbidden();
    expect($owner->refresh()->name)->not->toBe('Taken Over');

    // 4. the token acts for its organization only
    apiFlowCall($this, 'GET', '/v1/services', [], ['X-Organization' => 'org_someone_else'])->assertForbidden()->assertJsonPath('error', 'token_organization_mismatch');

    // 5. HIGH actions are refused through a token even when its scope covers the family: a token session never holds a step-up, so the
    //    refusal opens a request the organization's owner approves in the portal (H0, owner decision H-R1)
    apiFlowOwner($this, 'owner.token@example.cz', $password);
    [$powerPlain, $powerId] = apiFlowPersonalToken($this, ['services:read', 'services:power'], 'operator');
    apiFlowToken($this, $powerPlain);
    $terminate = apiFlowCall($this, 'POST', "/v1/services/{$service->id}/actions", ['action' => 'terminate', 'params' => []], apiFlowKey('terminate'), TokenScopes::SERVICES_POWER);
    expect($terminate->getStatusCode())->toBeIn([403, 428])->and($terminate->json('error'))->toBe('approval_required');
    expect($service->refresh()->state)->toBe(ServiceStateMachine::ACTIVE);

    // 6. a write replays under the same Idempotency-Key, refuses the same key with another body, and is 409 while the first copy still runs
    apiFlowToken($this, $plain);
    $ticket = ['subject' => 'Deploy hook failed', 'body' => 'The pipeline could not reach the service.'];
    $first = apiFlowCall($this, 'POST', '/v1/tickets', $ticket, ['Idempotency-Key' => 'e9-ticket-1'], TokenScopes::TICKETS_WRITE)->assertCreated();
    $replay = apiFlowCall($this, 'POST', '/v1/tickets', $ticket, ['Idempotency-Key' => 'e9-ticket-1'], TokenScopes::TICKETS_WRITE)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect($replay->json('data.id'))->toBe($first->json('data.id'))->and(DB::table('support_tickets')->where('organization_id', $org->id)->count())->toBe(1);
    apiFlowCall($this, 'POST', '/v1/tickets', ['subject' => 'Another one', 'body' => 'x'], ['Idempotency-Key' => 'e9-ticket-1'], TokenScopes::TICKETS_WRITE)->assertStatus(409)->assertJsonPath('error', 'idempotency_key_reused');
    // (no route can hold a request open inside a test: the key is reserved as the middleware would while a first copy runs — same scope, same hash)
    $body = ['subject' => 'Slow one', 'body' => 'still being written'];
    $request = Request::create('/v1/tickets', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    $hash = hash('sha256', 'POST|'.$request->path().'?|'.''.'|'.$request->getContent());
    $scope = 'user:'.$owner->id.'|'.substr(hash('sha256', 'org:|token:'.$tokenId), 0, 32);
    app(IdempotencyStore::class)->reserveHttp('e9-ticket-running', $scope, $hash, 600);
    $busy = apiFlowCall($this, 'POST', '/v1/tickets', $body, ['Idempotency-Key' => 'e9-ticket-running'], TokenScopes::TICKETS_WRITE)->assertStatus(409)->assertJsonPath('error', 'idempotency_in_progress');
    expect($busy->headers->get('Retry-After'))->not->toBeNull()->and(DB::table('support_tickets')->where('organization_id', $org->id)->count())->toBe(1);

    // 7. revoking ends a token at the very next request; the other one goes on
    apiFlowOwner($this, 'owner.token@example.cz', $password);
    apiFlowCall($this, 'DELETE', "/v1/tokens/{$tokenId}", [], ['Referer' => 'http://localhost'], 'portal')->assertOk();
    apiFlowToken($this, $plain);
    apiFlowCall($this, 'GET', '/v1/services', [], [], TokenScopes::SERVICES_READ)->assertUnauthorized();
    apiFlowToken($this, $powerPlain);
    apiFlowCall($this, 'GET', '/v1/services', [], [], TokenScopes::SERVICES_READ)->assertOk();
    expect($powerId)->not->toBe($tokenId);

    apiFlowAssertContract();
});

it('lets only the owner make a service account whose token acts for the organization alone and ends the moment it is revoked', function () {
    $panel = $gate = $receiver = [];
    apiFlowDoubles($panel, $gate, $receiver);
    [, $otherOrg] = e2eSignUp($this, 'other.org@example.cz', 'Cizí firma s.r.o.');
    apiFlowReset($this);
    [$admin, , $adminPassword] = e2eSignUp($this, 'org.admin@example.cz', 'Správce s.r.o.');
    apiFlowReset($this);
    [$owner, $org, $password] = e2eSignUp($this, 'owner.sa@example.cz', 'Pipeline s.r.o.');
    $service = apiFlowWebService($this, $org, $gate, 'sa-e9.cz');
    // (a membership has no route of its own in this flow: the admin of the organization is attached as the invitation would)
    app(OrganizationService::class)->attachMember($org, $admin, 'org_admin', CommandContext::system('test'), true);
    $new = ['name' => 'GitHub Actions', 'description' => 'deploys the shop', 'role' => 'viewer', 'scopes' => ['services:read', 'tickets:write']];

    // 1. a service account is a HIGH action: no fresh step-up, none made
    apiFlowCall($this, 'POST', '/v1/service-accounts', $new, apiFlowH('sa-nostep'), 'portal')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(ServiceAccount::query()->count())->toBe(0);

    // 2. owner only: an organization admin, stepped up, is told so; the owner's own call goes through
    apiFlowOwner($this, 'org.admin@example.cz', $adminPassword);
    apiFlowCall($this, 'POST', '/v1/service-accounts', $new, apiFlowH('sa-admin') + ['X-Organization' => $org->id], 'portal')->assertForbidden()->assertJsonPath('message', 'Only the owner of the organization manages its service accounts.');
    apiFlowCall($this, 'GET', '/v1/service-accounts', [], ['X-Organization' => $org->id, 'Referer' => 'http://localhost'], 'portal')->assertForbidden();
    expect(ServiceAccount::query()->count())->toBe(0);
    apiFlowOwner($this, 'owner.sa@example.cz', $password);
    $created = apiFlowCall($this, 'POST', '/v1/service-accounts', $new, apiFlowH('sa-create'), 'portal')->assertCreated();
    $plain = (string) $created->json('token');
    $accountId = (string) $created->json('data.id');
    $tokenId = (string) $created->json('data.tokens.0.id');
    expect($created->json('data.role'))->toBe('viewer')->and(ServiceAccount::query()->findOrFail($accountId)->organization_id)->toBe($org->id);
    $listed = apiFlowCall($this, 'GET', '/v1/service-accounts', [], ['Referer' => 'http://localhost'], 'portal')->assertOk();
    expect($listed->json('data'))->toHaveCount(1)->and($listed->getContent())->not->toContain(explode('|', $plain)[1] ?? $plain);

    // 3. its token acts as the account — not as a person, and never for another organization
    apiFlowToken($this, $plain);
    $services = apiFlowCall($this, 'GET', '/v1/services', [], [], TokenScopes::SERVICES_READ)->assertOk()->assertHeader('X-API-Version', (string) config('onhost.api.version'));
    expect(collect($services->json('data'))->pluck('id')->all())->toBe([$service->id]);
    // it may ask who it is (F12a): the account, its organization, its role and the token's scopes — and acts for no person
    apiFlowCall($this, 'GET', '/v1/me')->assertOk()->assertJsonPath('data.type', 'service_account')->assertJsonPath('data.account.id', $accountId)
        ->assertJsonPath('data.organization.id', $org->id)->assertJsonPath('data.role', 'viewer')->assertJsonPath('data.scopes', ['services:read', 'tickets:write']);
    apiFlowCall($this, 'GET', '/v1/tickets', [], [], TokenScopes::TICKETS_WRITE)->assertForbidden()->assertJsonPath('error', 'person_required');
    apiFlowCall($this, 'GET', '/v1/services', [], ['X-Organization' => $otherOrg->id])->assertForbidden()->assertJsonPath('error', 'token_organization_mismatch');
    apiFlowCall($this, 'GET', "/v1/services/{$service->id}", [], ['X-Organization' => $otherOrg->id])->assertForbidden();
    apiFlowCall($this, 'GET', '/v1/invoices')->assertForbidden()->assertJsonPath('message', 'The API token lacks the invoices:read scope.');
    apiFlowCall($this, 'GET', '/v1/service-accounts', [], [], 'portal')->assertForbidden(); // an account never manages accounts
    apiFlowCall($this, 'GET', '/v1/staff/customers', [], [], 'portal')->assertForbidden()->assertJsonPath('error', 'staff_only'); // 403, not a 401 telling a valid credential to sign in
    // the account's role caps its token: tickets:write is a scope it was issued, but a viewer writes nothing
    apiFlowCall($this, 'POST', '/v1/tickets', ['subject' => 'Bot writes', 'body' => 'x'], [], TokenScopes::TICKETS_WRITE)->assertForbidden()->assertJsonPath('error', 'access_not_approved');
    expect(DB::table('support_tickets')->where('organization_id', $org->id)->count())->toBe(0);
    expect(PersonalAccessToken::query()->findOrFail($tokenId)->tokenable_type)->toBe((new ServiceAccount)->getMorphClass());

    // 4. the role caps the scope: a viewer's token holding services:power is still refused, a developer's operates the service — as the account
    apiFlowOwner($this, 'owner.sa@example.cz', $password);
    $power = ['services:read', 'services:power'];
    $viewerPower = apiFlowCall($this, 'POST', "/v1/service-accounts/{$accountId}/tokens", ['name' => 'power', 'scopes' => $power], apiFlowH('sa-viewer-power'), 'portal')->assertCreated();
    $dev = apiFlowCall($this, 'POST', '/v1/service-accounts', ['name' => 'Deploy bot', 'role' => 'developer', 'scopes' => $power], apiFlowH('sa-dev'), 'portal')->assertCreated();
    $php = ['action' => 'php.set', 'params' => ['version' => '8.3']];
    apiFlowToken($this, (string) $viewerPower->json('token'));
    apiFlowCall($this, 'POST', "/v1/services/{$service->id}/actions", $php, [], TokenScopes::SERVICES_POWER)->assertForbidden();
    apiFlowToken($this, (string) $dev->json('token'));
    $accepted = apiFlowCall($this, 'POST', "/v1/services/{$service->id}/actions", $php, [], TokenScopes::SERVICES_POWER)->assertStatus(202);
    $operation = driveOperation(Operation::query()->findOrFail($accepted->json('operation_id') ?? $accepted->json('data.operation_id') ?? $accepted->json('data.id')));
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->actor_id)->toBe((string) $dev->json('data.id'));

    // 5. revoking its token ends it at the very next request — the account's other token goes on; removing the account ends them all
    apiFlowOwner($this, 'owner.sa@example.cz', $password);
    apiFlowCall($this, 'DELETE', "/v1/service-accounts/{$accountId}/tokens/{$tokenId}", [], ['Referer' => 'http://localhost'], 'portal')->assertOk()->assertJsonPath('revoked', true);
    apiFlowToken($this, $plain);
    apiFlowCall($this, 'GET', '/v1/services', [], [], TokenScopes::SERVICES_READ)->assertUnauthorized();
    apiFlowToken($this, (string) $dev->json('token'));
    apiFlowCall($this, 'GET', '/v1/services', [], [], TokenScopes::SERVICES_READ)->assertOk(); // the account's other token goes on
    apiFlowOwner($this, 'owner.sa@example.cz', $password);
    apiFlowCall($this, 'DELETE', '/v1/service-accounts/'.$dev->json('data.id'), [], ['Referer' => 'http://localhost'], 'portal')->assertOk();
    apiFlowToken($this, (string) $dev->json('token'));
    apiFlowCall($this, 'GET', '/v1/services', [], [], TokenScopes::SERVICES_READ)->assertUnauthorized();

    apiFlowAssertContract();
});

it('delivers a real customer event to a webhook: signed, public fields only, redelivered a bounded number of times, suspended when it keeps failing', function () {
    $panel = $gate = $receiver = [];
    apiFlowDoubles($panel, $gate, $receiver);
    [$owner, $org, $password] = e2eSignUp($this, 'owner.hook@example.cz', 'Hooky s.r.o.');
    $hook = ['url' => 'https://hooks.example.cz/onhost', 'events' => ['service.*', 'order.*']];

    // 1. the endpoint: HIGH (step-up), https on 443 or 8443 only, known events only, and the signing secret is shown once
    apiFlowCall($this, 'POST', '/v1/webhooks', $hook, apiFlowH('hook-nostep'), 'portal')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    e2eStepUp($this, $password);
    apiFlowCall($this, 'POST', '/v1/webhooks', ['url' => 'http://hooks.example.cz/onhost'] + $hook, apiFlowH('hook-http'), 'portal')->assertUnprocessable();
    apiFlowCall($this, 'POST', '/v1/webhooks', ['url' => 'https://hooks.example.cz:8080/onhost'] + $hook, apiFlowH('hook-port'), 'portal')->assertUnprocessable()->assertJsonPath('error', 'webhook_port_not_allowed');
    apiFlowCall($this, 'POST', '/v1/webhooks', ['events' => ['service.exploded']] + $hook, apiFlowH('hook-event'), 'portal')->assertUnprocessable()->assertJsonPath('error', 'webhook_event_unknown');
    expect(WebhookEndpoint::query()->count())->toBe(0);
    $created = apiFlowCall($this, 'POST', '/v1/webhooks', $hook, apiFlowH('hook-create'), 'portal')->assertCreated();
    $endpointId = (string) $created->json('data.id');
    $secret = (string) $created->json('data.secret');
    expect($secret)->toStartWith('whsec_');
    $listed = apiFlowCall($this, 'GET', '/v1/webhooks', [], e2eHeaders('hook-list'), 'portal')->assertOk();
    expect($listed->json('data.0.id'))->toBe($endpointId)->and($listed->getContent())->not->toContain($secret);

    // the receiver checks what the docs tell it to: HMAC-SHA256 over "<timestamp>.<raw body>" with the whole whsec_ string
    $verify = function (HttpRequest $request) use ($secret): array {
        $timestamp = $request->header('X-ONhost-Timestamp')[0] ?? '';
        expect(ctype_digit($timestamp) && abs(time() - (int) $timestamp) < 300)->toBeTrue()
            ->and($request->header('X-ONhost-Signature')[0] ?? '')->toBe('v1='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $secret))
            ->and($request->header('Content-Type')[0] ?? '')->toBe('application/json');

        return (array) json_decode($request->body(), true);
    };
    $seen = function (string $event) use (&$receiver): array { // by reference: the receiver's list grows while the flow runs
        return array_values(array_filter($receiver['seen'], fn (HttpRequest $r) => ($r->header('X-ONhost-Event')[0] ?? '') === $event));
    };

    // 2. a test event, once per cooldown
    apiFlowCall($this, 'POST', "/v1/webhooks/{$endpointId}/ping", [], [], 'portal')->assertStatus(202);
    expect($seen('webhook.ping'))->toHaveCount(1);
    $verify($seen('webhook.ping')[0]);
    apiFlowCall($this, 'POST', "/v1/webhooks/{$endpointId}/ping", [], [], 'portal')->assertStatus(429)->assertJsonPath('error', 'webhook_ping_cooldown')->assertJsonStructure(['retry_after']);
    expect($seen('webhook.ping'))->toHaveCount(1);

    // 3. a real customer event: the order is paid and provisioned, `service.activated` is relayed from the outbox and delivered from the queue
    $service = apiFlowWebService($this, $org, $gate, 'hook-e9.cz');
    expect($seen('service.activated'))->toHaveCount(1)->and($seen('order.paid'))->toHaveCount(1);
    $body = $verify($seen('service.activated')[0]);
    $delivery = WebhookDelivery::query()->where('endpoint_id', $endpointId)->where('event', 'service.activated')->sole();
    expect($body['id'])->toBe($delivery->id)->and($seen('service.activated')[0]->header('X-ONhost-Delivery')[0])->toBe($delivery->id)
        ->and($body['event'])->toBe('service.activated')
        ->and(array_keys($body))->toBe(['id', 'event', 'created_at', 'data'])
        ->and($body['data']['aggregate'])->toBe(['type' => 'service', 'id' => $service->id])
        ->and($body['data']['organization_id'])->toBe($org->id)
        ->and($delivery->state)->toBe('delivered');
    // only the public fields of the event, none of what the platform keeps for itself
    $payload = $body['data']['payload'];
    expect(array_diff(array_keys($payload), ['product_key', 'family', 'parent_service_id', 'access']))->toBe([])
        ->and($payload['product_key'])->toBe('web-hosting');
    $internal = OutboxMessage::query()->where('name', 'service.activated')->where('organization_id', $org->id)->sole()->payload;
    expect(array_diff(array_keys($internal), array_keys($payload)))->not->toBe([], 'the outbox payload carries more than the wire may: the allow-list is what filters it');
    foreach (array_diff(array_keys($internal), array_keys($payload)) as $key) {
        expect($seen('service.activated')[0]->body())->not->toContain('"'.$key.'"');
    }
    expect(mb_strtolower($seen('service.activated')[0]->body()))->not->toContain('ispconfig')->not->toContain('operation')->not->toContain('node')->not->toContain($secret);
    // only what the endpoint subscribed to, and never the platform's own work
    $names = array_map(fn (HttpRequest $r) => $r->header('X-ONhost-Event')[0], $receiver['seen']);
    foreach ($names as $name) {
        expect($name === 'webhook.ping' || str_starts_with($name, 'service.') || str_starts_with($name, 'order.'))->toBeTrue("unsubscribed event on the wire: {$name}");
    }
    expect($names)->not->toContain('operation.succeeded')->not->toContain('invoice.issued');

    // 4. redelivery: the same delivery id and body, one more attempt each, at most 10 attempts in all; then 20 requests an hour
    $deliveries = apiFlowCall($this, 'GET', "/v1/webhooks/{$endpointId}/deliveries", [], e2eHeaders('deliveries'), 'portal')->assertOk();
    expect(collect($deliveries->json('data'))->pluck('id')->all())->toContain($delivery->id);
    $url = "/v1/webhooks/{$endpointId}/deliveries/{$delivery->id}/redeliver";
    $before = count($seen('service.activated'));
    apiFlowCall($this, 'POST', $url, [], [], 'portal')->assertStatus(202);
    $again = $seen('service.activated');
    expect($again)->toHaveCount($before + 1)->and($again[$before]->body())->toBe($again[0]->body())->and($again[$before]->header('X-ONhost-Delivery')[0])->toBe($delivery->id)
        ->and(WebhookDelivery::query()->where('endpoint_id', $endpointId)->where('event', 'service.activated')->count())->toBe(1);
    $accepted = 1;
    do {
        $answer = apiFlowCall($this, 'POST', $url, [], [], 'portal');
        $answer->getStatusCode() === 202 && $accepted++;
    } while ($answer->getStatusCode() === 202 && $accepted < 20);
    $answer->assertStatus(409)->assertJsonPath('error', 'webhook_redeliver_limit');
    expect($delivery->refresh()->attempts)->toBe(WebhookDispatcher::MAX_ATTEMPTS)->and($accepted)->toBe(WebhookDispatcher::MAX_ATTEMPTS - 1);
    // (each delivery may be sent 10 times, so the hourly ceiling is reached over several deliveries)
    $pool = WebhookDelivery::query()->where('endpoint_id', $endpointId)->where('id', '!=', $delivery->id)->where('event', '!=', 'webhook.ping')->orderBy('id')->pluck('id')->all();
    expect(count($pool))->toBeGreaterThanOrEqual(3);
    for ($i = $accepted + 1; $i < WebhookCommandHandler::REDELIVERS_PER_HOUR; $i++) { // (the refused request above counted too: the ceiling is per request)
        apiFlowCall($this, 'POST', "/v1/webhooks/{$endpointId}/deliveries/".$pool[$i % count($pool)].'/redeliver', [], [], 'portal')->assertStatus(202);
    }
    apiFlowCall($this, 'POST', "/v1/webhooks/{$endpointId}/deliveries/{$pool[0]}/redeliver", [], [], 'portal')->assertStatus(429)->assertJsonPath('error', 'webhook_redeliver_rate');

    // 5. an endpoint that keeps failing is suspended and its customer is told (the failure count is set: 19 more failed attempts have no route)
    $this->travel(2)->hours();
    e2eStepUp($this, $password);
    WebhookEndpoint::query()->whereKey($endpointId)->update(['failures' => WebhookDispatcher::SUSPEND_AFTER - 1]);
    $receiver['status'] = 503;
    apiFlowCall($this, 'POST', "/v1/webhooks/{$endpointId}/ping", [], [], 'portal')->assertStatus(202);
    app(OutboxPublisher::class)->relayPending();
    $endpoint = WebhookEndpoint::query()->findOrFail($endpointId);
    expect($endpoint->state)->toBe('suspended')->and($endpoint->failures)->toBe(WebhookDispatcher::SUSPEND_AFTER);
    expect(OutboxMessage::query()->where('name', 'webhook.endpoint.suspended')->where('organization_id', $org->id)->count())->toBe(1)
        ->and(Notification::query()->where('organization_id', $org->id)->where('audience', 'customer')->where('title', 'like', 'Webhook pozastaven%')->count())->toBe(1)
        ->and(MailOutbox::query()->where('template_key', 'webhook-suspended')->where('organization_id', $org->id)->count())->toBe(1);
    apiFlowCall($this, 'GET', '/v1/webhooks', [], e2eHeaders('hook-list2'), 'portal')->assertJsonPath('data.0.state', 'suspended');
    $this->travel(31)->seconds();
    apiFlowCall($this, 'POST', "/v1/webhooks/{$endpointId}/ping", [], [], 'portal')->assertStatus(409)->assertJsonPath('error', 'webhook_not_active');
    $receiver['status'] = 204;
    apiFlowCall($this, 'POST', "/v1/webhooks/{$endpointId}/enable", [], [], 'portal')->assertOk()->assertJsonPath('data.state', 'active')->assertJsonPath('data.failures', 0);
    apiFlowCall($this, 'POST', "/v1/webhooks/{$endpointId}/ping", [], [], 'portal')->assertStatus(202);
    expect(WebhookDelivery::query()->where('endpoint_id', $endpointId)->where('event', 'webhook.ping')->orderByDesc('created_at')->first()->state)->toBe('delivered');

    apiFlowAssertContract();
});
