<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
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
function tptTwoOrganizations(): array
{
    $user = User::factory()->create(['email' => 'dva-uctu@example.cz']);
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
