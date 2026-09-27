<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationRunner;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * TASK-0039 — permission program P0-09 (IF-5, audit PA-04): an API token is a narrowed view of its person — ONE organization.
 *
 * A token is created for an organization (`personal_access_tokens.organization_id`) and nothing enforced it: a person in two
 * organizations sent the token of A with `X-Organization: B`, or simply named a service of B, and acted on B; a member of staff's
 * token carried the whole global reach of the staff role. Now the token sees only the bindings of its own organization (never a
 * global binding, never a JIT elevation), a request naming another organization is refused, and without a header the token's
 * own organization is used (not the person's first membership). A token bound to no organization (older rows, CLI-made) is
 * listed by `operator:tokens:unbound` and refused once `onhost.token_organization_required` is switched on after the notice.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
});

/** A person in two organizations — B joined FIRST, so "the first membership" is B — and a token made for A. @return array{0:User,1:Organization,2:Organization,3:string,4:string} */
function tptTwoOrganizations(string $email = 'dva-uctu@example.cz'): array
{
    $user = User::factory()->create(['email' => $email]);
    $b = app(OrganizationService::class)->create($user, ['name' => 'Beta s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $a = app(OrganizationService::class)->create($user, ['name' => 'Alfa s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $token = $user->createToken('tpt-alfa', TokenScopes::ALL);
    $token->accessToken->forceFill(['organization_id' => $a->id])->save();

    return [$user, $a, $b, $token->plainTextToken, (string) $token->accessToken->getKey()];
}

it('refuses a token on another organization by header, and resolves a missing header to the token\'s own (IF-5)', function () {
    [, $a, $b, $plain] = tptTwoOrganizations();
    $mine = featureWebService($a, 'ispconfig');
    $theirs = featureWebService($b, 'aapanel');

    $this->withToken($plain)->withHeader('X-Organization', $b->id)->getJson('/v1/services')->assertForbidden()->assertJsonPath('error', 'token_organization_mismatch');
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->withToken($plain)->getJson("/v1/services?organization={$b->id}")->assertForbidden()->assertJsonPath('error', 'token_organization_mismatch');
    app('auth')->forgetGuards();
    $this->flushHeaders();

    // no header: the token's own organization, not the person's first membership (which is B)
    $listed = $this->withToken($plain)->getJson('/v1/services')->assertOk()->json('data');
    expect(array_column($listed, 'id'))->toBe([$mine->id])->not->toContain($theirs->id);
    app('auth')->forgetGuards();
    $this->withToken($plain)->withHeader('X-Organization', $a->id)->getJson('/v1/services')->assertOk();
});

it('refuses a token the resources of another organization by id — the service, its queued operations and a new action (IF-5)', function () {
    [$user, $a, $b, $plain, $tokenId] = tptTwoOrganizations();
    $theirs = featureWebService($b, 'aapanel');
    Operation::query()->create(['organization_id' => $b->id, 'service_id' => $theirs->id, 'kind' => 'service.action', 'workflow' => 'x', 'state' => Operation::PENDING, 'idempotency_key' => 'tpt-queued', 'queue' => 'default', 'desired' => ['action' => 'backup']]);

    foreach (["/v1/services/{$theirs->id}", "/v1/services/{$theirs->id}/operations"] as $url) {
        $this->withToken($plain)->getJson($url)->assertForbidden();
        app('auth')->forgetGuards();
    }
    $this->withToken($plain)->withHeader('Idempotency-Key', 'tpt-act')->postJson("/v1/services/{$theirs->id}/actions", ['action' => 'backup'])->assertForbidden();
    app('auth')->forgetGuards();
    $this->flushHeaders();
    expect(Operation::query()->where('service_id', $theirs->id)->count())->toBe(1);

    // the bus asks the same view: a command carrying the token's session is decided for A only
    $context = new CommandContext('user', $user->id, $b->id, null, '127.0.0.1', 'pest', "token:{$tokenId}");
    expect(fn () => app(CommandBus::class)->dispatch(new ServiceActionCommand($b->id, 'tpt-bus', ['service_id' => $theirs->id, 'action' => 'backup', 'params' => []]), $context))
        ->toThrow(fn (DomainError $e) => expect($e->status)->toBe(403));
    expect(Operation::query()->where('service_id', $theirs->id)->count())->toBe(1);

    // the same person in the portal is a member of both
    $this->actingAs($user, 'sanctum')->getJson("/v1/services/{$theirs->id}")->assertOk();
});

it('never lends a token the global reach of a staff role', function () {
    [, $customerOrg] = $this->customerWithOrganization();
    $foreign = featureWebService($customerOrg, 'aapanel');
    $staff = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $staff->id, 'role_key' => 'platform_owner', 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    $own = app(OrganizationService::class)->create($staff, ['name' => 'Vlastní s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $token = $staff->createToken('tpt-staff', TokenScopes::ALL);
    $token->accessToken->forceFill(['organization_id' => $own->id])->save();

    $this->withToken($token->plainTextToken)->getJson("/v1/services/{$foreign->id}")->assertForbidden();
    app('auth')->forgetGuards();
    $this->withToken($token->plainTextToken)->withHeader('X-Organization', $customerOrg->id)->getJson('/v1/services')->assertForbidden();
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->withToken($token->plainTextToken)->getJson('/v1/services')->assertOk(); // their own organization, as a member
});

it('lists tokens bound to no organization and refuses them once the switch is on', function () {
    $user = User::factory()->create(['email' => 'stary-token@example.cz']);
    $a = app(OrganizationService::class)->create($user, ['name' => 'Gama s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    featureWebService($a, 'aapanel');
    $bound = $user->createToken('alfa-deploy', TokenScopes::ALL);
    $bound->accessToken->forceFill(['organization_id' => $a->id])->save();
    $unbound = $user->createToken('legacy-ci', TokenScopes::ALL); // made before tokens carried an organization

    // listed for the notice, nothing changed by the listing
    expect(Artisan::call('operator:tokens:unbound', ['--dry-run' => true]))->toBe(0);
    $listing = Artisan::output();
    expect($listing)->toContain((string) $unbound->accessToken->getKey())->toContain('stary-token@example.cz')->toContain('legacy-ci')
        ->not->toContain('alfa-deploy');

    // the switch off: the old token still works for its person's organizations (never with a staff reach)
    $this->withToken($unbound->plainTextToken)->withHeader('X-Organization', $a->id)->getJson('/v1/services')->assertOk();
    app('auth')->forgetGuards();
    $this->flushHeaders();

    config(['onhost.token_organization_required' => true]);
    $this->withToken($unbound->plainTextToken)->withHeader('X-Organization', $a->id)->getJson('/v1/services')->assertForbidden()->assertJsonPath('error', 'token_unbound');
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->withToken($bound->plainTextToken)->getJson('/v1/services')->assertOk();
});

// ── TASK-0039 review round 2 ──
/** A run the token started on A through the bus, queued (not run) — the way a request with the token leaves it. */
function tptQueuedRun(User $user, Organization $a, Service $service, string $tokenId, string $key, string $action = 'suspend'): Operation
{
    $context = new CommandContext('user', $user->id, $a->id, null, '127.0.0.1', 'pest', "token:{$tokenId}");
    app(CommandBus::class)->dispatch(new ServiceActionCommand($a->id, $key, ['service_id' => $service->id, 'action' => $action, 'params' => ['reason' => 'údržba']]), $context);

    return Operation::query()->where('service_id', $service->id)->where('state', Operation::PENDING)->sole();
}

/** Another token of the same person for A. */
function tptTokenFor(User $user, Organization $a, string $name): string
{
    $token = $user->createToken($name, TokenScopes::ALL);
    $token->accessToken->forceFill(['organization_id' => $a->id])->save();

    return (string) $token->accessToken->getKey();
}

it('keeps a queued run on the token that started it: revoked or expired mid-run, the run stops (review round 2, P0-09 "including queued operations")', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => []]));
    [$user, $a, , , $tokenId] = tptTwoOrganizations();
    $service = featureWebService($a, 'aapanel');

    // the run remembers the token — from the starting context alone, a parameter of that name is dropped
    $operation = tptQueuedRun($user, $a, $service, $tokenId, 'tpt-run-revoked');
    expect(data_get($operation->desired, 'token_id'))->toBe($tokenId);

    // the token is revoked before the run acts; the person is still an admin of A, so the person's view alone would carry on
    PersonalAccessToken::query()->whereKey($tokenId)->delete();
    $stopped = tptTick($operation);
    expect($stopped->state)->toBe(Operation::FAILED)->and(data_get($stopped->error, 'detail.access_revoked'))->toBeTrue();
    Http::assertNothingSent();

    // a live token carries the run to its end, and an expired one stops it like a revoked one
    $live = tptTokenFor($user, $a, 'tpt-live');
    expect(tptTick(tptQueuedRun($user, $a, $service, $live, 'tpt-run-live'))->state)->toBe(Operation::SUCCEEDED);
    expect(Http::recorded())->not->toBeEmpty(); // the live run did reach the panel
    $expiring = tptTokenFor($user, $a, 'tpt-expiring');
    $resume = tptQueuedRun($user, $a, $service, $expiring, 'tpt-run-expired', 'resume');
    PersonalAccessToken::query()->whereKey($expiring)->update(['expires_at' => now()->subMinute()]);
    $expired = tptTick($resume);
    expect($expired->state)->toBe(Operation::FAILED)->and(data_get($expired->error, 'detail.access_revoked'))->toBeTrue()
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED);
});

it('drops a token_id a caller puts among the parameters: only the starting context names the token (review round 2)', function () {
    [$user, $a] = tptTwoOrganizations();
    $service = featureWebService($a, 'aapanel');
    $operation = app(OperationService::class)->start(ServiceActionWorkflow::class, 'tpt-forged', ['action' => 'backup', 'service_id' => $service->id, 'token_id' => '999999'],
        new CommandContext('user', $user->id, $a->id), $service->id, $a->id, dispatch: false);
    expect((array) $operation->desired)->not->toHaveKey('token_id');
});

function tptTick(Operation $operation): Operation
{
    app(OperationRunner::class)->tick($operation->fresh(), 60);

    return $operation->fresh();
}
// ── end TASK-0039 review round 2 ──

// ── TASK-0039 review round 3 ──
/*
 * P0-09 on the entry points OUTSIDE /v1. `auth:sanctum` accepts a bearer token as readily as the portal's session cookie, and the
 * web routes that use it never asked the token anything: `GET /surfaces/onhost-panel.js?organization=B` served B's services to the
 * token of A (and, without the parameter, the person's FIRST membership — B again), the console pre-flight said "valid" for B's
 * console, the staff pages rendered for a staff token. These are the browser's own endpoints (a script tag, a page, a pre-flight
 * of the portal) — nothing there is for API tokens, so they are the portal session's alone: `token.scope` refuses every token
 * there, as it does on /v1 for a family it does not list.
 */
it('refuses a token the panel seam of another organization and of its own, and keeps it for the session (review round 3)', function () {
    [$user, $a, $b, $plain] = tptTwoOrganizations('seam-dva@example.cz');
    featureWebService($a, 'ispconfig');
    $theirs = featureWebService($b, 'aapanel');

    foreach (["/surfaces/onhost-panel.js?organization={$b->id}", '/surfaces/onhost-panel.js', "/surfaces/onhost-panel.js?organization={$a->id}"] as $url) {
        $answer = $this->withToken($plain)->get($url);
        expect($answer->status())->toBe(403)->and((string) $answer->getContent())->not->toContain($theirs->id)->not->toContain('window.ONHOST_PANEL');
        app('auth')->forgetGuards();
        $this->flushHeaders();
    }

    // the person in the portal: a member of both, B is theirs to see
    expect($this->actingAs($user)->get("/surfaces/onhost-panel.js?organization={$b->id}")->assertOk()->getContent())->toContain($theirs->id);
});

it('refuses a token the console pre-flight, and keeps it for the session (review round 3)', function () {
    [$user, , $b, $plain] = tptTwoOrganizations('konzole-dva@example.cz');
    $theirs = featureWebService($b, 'aapanel');
    $console = 'con_'.strtolower((string) Str::ulid());
    Cache::put("onhost:console:{$console}", ['kind' => 'pve_vnc', 'upstream' => 'https://pve.lab/x', 'port' => 5900, 'vncticket' => 'PVEVNC:secret', 'service_id' => $theirs->id, 'organization_id' => $b->id], 120);

    $this->withToken($plain)->getJson("/console/check/{$console}")->assertForbidden()->assertJsonMissingPath('data.valid');
    app('auth')->forgetGuards();
    $this->flushHeaders();

    $this->actingAs($user)->getJson("/console/check/{$console}")->assertOk()->assertJsonPath('data.valid', true);
});

it('refuses a staff token the staff pages, and keeps them for the session (review round 3)', function () {
    [, $customerOrg] = $this->customerWithOrganization(['email' => 'stranka@example.cz']);
    $service = featureWebService($customerOrg, 'aapanel');
    $staff = $this->staff('platform_owner');
    $plain = $staff->createToken('tpt-staff-pages', TokenScopes::ALL)->plainTextToken;
    $pages = ['/sprava/nastaveni/integrace', '/sprava/nastaveni/provoz', '/sprava/nastaveni/hromadne-akce', '/sprava/nastaveni/zivotni-cyklus',
        '/sprava/nastaveni/schvalovani', '/sprava/nastaveni/tarify', "/sprava/konzole/{$service->id}"];

    foreach ($pages as $page) {
        expect($this->withToken($plain)->get($page)->status())->toBe(403, $page);
        app('auth')->forgetGuards();
        $this->flushHeaders();
    }
    $this->actingAs($staff)->get("/sprava/konzole/{$service->id}")->assertOk();
    $this->actingAs($staff)->get('/sprava/nastaveni/integrace')->assertOk();
});

it('lets no web route authenticate with auth:sanctum without token.scope (review round 3)', function () {
    $open = [];
    foreach (app('router')->getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();
        if (in_array('auth:sanctum', $middleware, true) && ! in_array('token.scope', $middleware, true)) {
            $open[] = implode('|', $route->methods()).' '.$route->uri();
        }
    }
    expect($open)->toBe([]);
});
// ── end TASK-0039 review round 3 ──
