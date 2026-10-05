<?php

declare(strict_types=1);

use App\Http\Middleware\ThrottleFailedAuth;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\ServiceAccounts\ServiceAccountCommand;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Tests\TestCase;

/*
 * TASK-0079 (audit 2026-10, package D6): an organization's service accounts — a pipeline's credential that belongs to the
 * organization, not to the person who set it up. The model and its bindings existed (the Authorizer, GrantPolicy and the bus all
 * knew a `service_account` principal), but nothing created one and a token of one opened nothing: every endpoint asked for a
 * person and answered 401. Managing them is the owner's alone, a HIGH action with a fresh step-up through the bus; the secret is
 * shown once; a revoked token or a removed account stops at the very next request.
 */

/** As the owner in the portal (cookie session), optionally with a fresh step-up. */
function saApiAs(TestCase $test, User $user, Organization $org, bool $stepUp = true): TestCase
{
    app('auth')->forgetGuards();
    $test->flushHeaders(); // no bearer of an earlier request rides along
    if ($stepUp) {
        app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    }

    return $test->actingAs($user, 'sanctum')->withHeaders(['X-Organization' => $org->id]);
}

/** POST /v1/service-accounts as `$user`. */
function saApiCreate(TestCase $test, User $user, Organization $org, array $body = [], bool $stepUp = true): TestResponse
{
    return saApiAs($test, $user, $org, $stepUp)->postJson('/v1/service-accounts', $body + ['name' => 'GitHub Actions', 'description' => 'deploys the shop', 'role' => 'viewer', 'scopes' => ['services:read']], ['Idempotency-Key' => (string) Str::ulid()]);
}

/** A request with only the bearer token (no cookie session of the owner left over). */
function saApiBearer(TestCase $test, string $plain, Organization $org): TestCase
{
    app('auth')->forgetGuards();
    $test->flushHeaders();

    return $test->withToken($plain)->withHeaders(['X-Organization' => $org->id]);
}

it('lets the owner create a service account whose token works for the organization and is shown only once', function () {
    [$owner, $org] = $this->customerWithOrganization();

    $created = saApiCreate($this, $owner, $org)->assertCreated();
    $plain = (string) $created->json('token');
    $accountId = (string) $created->json('data.id');
    expect($plain)->not->toBe('')
        ->and($created->json('data.name'))->toBe('GitHub Actions')
        ->and($created->json('data.role'))->toBe('viewer')
        ->and($created->json('data.tokens.0.scopes'))->toBe(['services:read'])
        ->and($created->json('data.tokens.0.expires_at'))->not->toBeNull(); // R9: every token ends (365 days by default)

    $account = ServiceAccount::query()->findOrFail($accountId);
    expect($account->organization_id)->toBe($org->id)->and($account->created_by)->toBe($owner->id)->and($account->isActive())->toBeTrue();
    $binding = PolicyBinding::query()->where('principal_type', 'service_account')->where('principal_id', $accountId)->sole();
    expect($binding->role_key)->toBe('viewer')->and($binding->scope_type)->toBe('organization')->and($binding->scope_id)->toBe($org->id)->and($binding->granted_by)->toBe($owner->id);
    $token = PersonalAccessToken::query()->where('tokenable_type', $account->getMorphClass())->where('tokenable_id', $accountId)->sole();
    expect($token->organization_id)->toBe($org->id);

    // the secret is never listed again, and the audit and the event do not carry it
    $listed = saApiAs($this, $owner, $org)->getJson('/v1/service-accounts')->assertOk();
    expect($listed->json('data.0.id'))->toBe($accountId)
        ->and($listed->getContent())->not->toContain($plain)
        ->and($listed->getContent())->not->toContain(explode('|', $plain)[1] ?? $plain);
    saApiAs($this, $owner, $org)->getJson("/v1/service-accounts/{$accountId}")->assertOk()->assertJsonPath('data.name', 'GitHub Actions');
    $audit = AuditEvent::query()->where('action', 'service_account.create')->where('result', 'succeeded')->sole();
    expect(json_encode($audit->detail))->not->toContain(explode('|', $plain)[1]);
    expect(OutboxMessage::query()->where('name', 'api_token.created')->get()->contains(fn (OutboxMessage $m) => str_contains(json_encode($m->payload), explode('|', $plain)[1])))->toBeFalse();

    // the token is the organization's: it reads the services as the account's role allows, and nothing it was not given
    saApiBearer($this, $plain, $org)->getJson('/v1/services')->assertOk();
    saApiBearer($this, $plain, $org)->getJson('/v1/invoices')->assertForbidden()->assertJsonPath('message', 'The API token lacks the invoices:read scope.');
    // a token never manages tokens, whoever it belongs to
    saApiBearer($this, $plain, $org)->getJson('/v1/service-accounts')->assertForbidden();
    // and it acts for its own organization only
    [, $other] = $this->customerWithOrganization();
    saApiBearer($this, $plain, $other)->getJson('/v1/services')->assertForbidden()->assertJsonPath('error', 'token_organization_mismatch');
});

it('renames an account, issues a second token and revokes one, which stops at the very next request', function () {
    $this->freezeSecond();
    [$owner, $org] = $this->customerWithOrganization();
    $created = saApiCreate($this, $owner, $org)->assertCreated();
    $accountId = (string) $created->json('data.id');
    $first = (string) $created->json('token');

    saApiAs($this, $owner, $org)->patchJson("/v1/service-accounts/{$accountId}", ['name' => 'CI deploy', 'description' => null], ['Idempotency-Key' => (string) Str::ulid()])
        ->assertOk()->assertJsonPath('data.name', 'CI deploy');

    $second = saApiAs($this, $owner, $org)->postJson("/v1/service-accounts/{$accountId}/tokens", ['name' => 'rotation', 'scopes' => ['services:read', 'dns:read'], 'expires_in_days' => 30], ['Idempotency-Key' => (string) Str::ulid()])
        ->assertCreated();
    $secondPlain = (string) $second->json('token');
    $secondId = (string) $second->json('id');
    expect($second->json('scopes'))->toBe(['services:read', 'dns:read'])
        ->and($second->json('expires_at'))->toBe(now()->addDays(30)->startOfSecond()->toIso8601String());

    saApiBearer($this, $first, $org)->getJson('/v1/services')->assertOk();
    saApiBearer($this, $secondPlain, $org)->getJson('/v1/services')->assertOk();

    saApiAs($this, $owner, $org)->deleteJson("/v1/service-accounts/{$accountId}/tokens/{$secondId}")->assertOk()->assertJsonPath('revoked', true);
    expect(PersonalAccessToken::query()->findOrFail($secondId)->revoked_at)->not->toBeNull();
    saApiBearer($this, $secondPlain, $org)->getJson('/v1/services')->assertUnauthorized();
    saApiBearer($this, $first, $org)->getJson('/v1/services')->assertOk(); // the other token goes on
    expect(OutboxMessage::query()->where('name', 'api_token.revoked')->exists())->toBeTrue();

    // a token of another account (or a person's) is not revoked through this one
    $otherAccount = saApiCreate($this, $owner, $org, ['name' => 'Terraform'])->assertCreated();
    saApiAs($this, $owner, $org)->deleteJson("/v1/service-accounts/{$accountId}/tokens/".$otherAccount->json('data.tokens.0.id'))->assertNotFound();
});

it('removes an account: every token of it ends at once, its role with it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $created = saApiCreate($this, $owner, $org)->assertCreated();
    $accountId = (string) $created->json('data.id');
    $plain = (string) $created->json('token');
    saApiBearer($this, $plain, $org)->getJson('/v1/services')->assertOk();

    saApiAs($this, $owner, $org)->deleteJson("/v1/service-accounts/{$accountId}")->assertOk()->assertJsonPath('deleted', true);

    expect(ServiceAccount::query()->find($accountId))->toBeNull()
        ->and(ServiceAccount::withTrashed()->findOrFail($accountId)->state)->toBe('deleted')
        ->and(PolicyBinding::query()->where('principal_type', 'service_account')->where('principal_id', $accountId)->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->where('tokenable_id', $accountId)->whereNull('revoked_at')->exists())->toBeFalse();
    saApiBearer($this, $plain, $org)->getJson('/v1/services')->assertUnauthorized();
    saApiAs($this, $owner, $org)->getJson('/v1/service-accounts')->assertOk()->assertJsonCount(0, 'data');
});

it('refuses everybody but the owner, an organization admin included', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = $this->customer(['email' => 'org-admin-sa@example.cz']);
    app(OrganizationService::class)->attachMember($org, $admin, 'org_admin', CommandContext::system('test'), true);
    $created = saApiCreate($this, $owner, $org)->assertCreated();
    $accountId = (string) $created->json('data.id');
    $tokenId = (string) $created->json('data.tokens.0.id');

    // org_admin holds api_token.manage (its own personal tokens) and has stepped up — a service account is still the owner's
    saApiCreate($this, $admin, $org)->assertForbidden()->assertJsonPath('message', 'Only the owner of the organization manages its service accounts.');
    saApiAs($this, $admin, $org)->getJson('/v1/service-accounts')->assertForbidden();
    saApiAs($this, $admin, $org)->getJson("/v1/service-accounts/{$accountId}")->assertForbidden();
    saApiAs($this, $admin, $org)->patchJson("/v1/service-accounts/{$accountId}", ['name' => 'mine now'])->assertForbidden();
    saApiAs($this, $admin, $org)->postJson("/v1/service-accounts/{$accountId}/tokens", ['name' => 'x', 'scopes' => ['services:read']])->assertForbidden();
    saApiAs($this, $admin, $org)->deleteJson("/v1/service-accounts/{$accountId}/tokens/{$tokenId}")->assertForbidden();
    saApiAs($this, $admin, $org)->deleteJson("/v1/service-accounts/{$accountId}")->assertForbidden();

    // a viewer has nothing to do here either, and an owner of another organization does not reach this one's accounts
    $viewer = $this->customer(['email' => 'viewer-sa@example.cz']);
    app(OrganizationService::class)->attachMember($org, $viewer, 'viewer', CommandContext::system('test'), true);
    saApiAs($this, $viewer, $org)->getJson('/v1/service-accounts')->assertForbidden();
    [$stranger, $strangerOrg] = $this->customerWithOrganization(['email' => 'stranger-sa@example.cz']);
    saApiAs($this, $stranger, $strangerOrg)->getJson("/v1/service-accounts/{$accountId}")->assertNotFound();
    saApiAs($this, $stranger, $strangerOrg)->deleteJson("/v1/service-accounts/{$accountId}")->assertNotFound();

    expect(ServiceAccount::query()->count())->toBe(1)
        ->and(PersonalAccessToken::query()->whereKey($tokenId)->whereNull('revoked_at')->exists())->toBeTrue();
});

it('asks the owner for a fresh step-up before any change', function () {
    [$owner, $org] = $this->customerWithOrganization();

    saApiCreate($this, $owner, $org, [], false)->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(ServiceAccount::query()->exists())->toBeFalse();

    $created = saApiCreate($this, $owner, $org)->assertCreated();
    $accountId = (string) $created->json('data.id');
    $tokenId = (string) $created->json('data.tokens.0.id');
    app(StepUpService::class)->revokeAll($owner);

    saApiAs($this, $owner, $org, false)->patchJson("/v1/service-accounts/{$accountId}", ['name' => 'x'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    saApiAs($this, $owner, $org, false)->postJson("/v1/service-accounts/{$accountId}/tokens", ['name' => 'x', 'scopes' => ['services:read']])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    saApiAs($this, $owner, $org, false)->deleteJson("/v1/service-accounts/{$accountId}/tokens/{$tokenId}")->assertForbidden()->assertJsonPath('error', 'step_up_required');
    saApiAs($this, $owner, $org, false)->deleteJson("/v1/service-accounts/{$accountId}")->assertForbidden()->assertJsonPath('error', 'step_up_required');
    saApiAs($this, $owner, $org, false)->getJson('/v1/service-accounts')->assertOk(); // reading the list takes no step-up

    expect(ServiceAccount::query()->findOrFail($accountId)->name)->toBe('GitHub Actions')
        ->and(PersonalAccessToken::query()->where('tokenable_id', $accountId)->count())->toBe(1)
        ->and(PersonalAccessToken::query()->findOrFail($tokenId)->revoked_at)->toBeNull();
});

it('gives an account only an organization role below the owner and documented scopes', function () {
    [$owner, $org] = $this->customerWithOrganization();

    foreach (['owner', 'svc_console', 'platform_owner', 'guest', 'made_up'] as $role) {
        saApiCreate($this, $owner, $org, ['role' => $role])->assertUnprocessable();
    }
    saApiCreate($this, $owner, $org, ['scopes' => ['services:read', 'admin:*']])->assertUnprocessable();
    saApiCreate($this, $owner, $org, ['scopes' => []])->assertUnprocessable();
    saApiCreate($this, $owner, $org, ['name' => ''])->assertUnprocessable();
    expect(ServiceAccount::query()->exists())->toBeFalse();
});

it('never lets a service account token take a step-up action or act as a person', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $plain = (string) saApiCreate($this, $owner, $org, ['role' => 'org_admin', 'scopes' => ['services:read', 'services:power', 'tickets:write']])->assertCreated()->json('token');

    // the person-only endpoints answer that they are for a person, not a 500 and not somebody else's data; GET /v1/me answers
    // who the account is (F12a)
    saApiBearer($this, $plain, $org)->getJson('/v1/tickets')->assertForbidden()->assertJsonPath('error', 'person_required');
    saApiBearer($this, $plain, $org)->getJson('/v1/me')->assertOk()->assertJsonPath('data.type', 'service_account')->assertJsonPath('data.role', 'org_admin');
    // it cannot step up (the route is no token's) …
    saApiBearer($this, $plain, $org)->postJson('/v1/auth/step-up', ['method' => 'password', 'password' => 'x'])->assertForbidden();
    // … so a HIGH command it sends is refused by the bus, even with the organization admin's role behind it
    $account = ServiceAccount::query()->sole();
    $tokenId = (string) PersonalAccessToken::query()->where('tokenable_id', $account->id)->value('id');
    $context = new CommandContext('service_account', $account->id, $org->id, null, '127.0.0.1', 'pest', 'token:'.$tokenId);
    // (G7, TASK-0115: managing service accounts is no token's at all, so the bus says that before it asks for a step-up) …
    expect(fn () => app(CommandBus::class)->dispatch(new ServiceAccountCommand($org->id, 'sa-self-'.Str::ulid(), ['op' => 'create', 'name' => 'child', 'role' => 'viewer', 'scopes' => ['services:read']]), $context))
        ->toThrow(DomainError::class, 'This action is not available to API tokens');
    // … and a HIGH action its scope does cover (a restore under services:power) still meets the step-up it can never take
    $web = featureWebService($org, 'aapanel');
    expect(fn () => app(CommandBus::class)->dispatch(new ServiceActionCommand($org->id, 'sa-restore-'.Str::ulid(), ['service_id' => $web->id, 'project_id' => $web->project_id, 'action' => 'restore', 'params' => []]), $context))
        ->toThrow(DomainError::class, 'Service accounts cannot perform actions that require step-up');
    expect(ServiceAccount::query()->count())->toBe(1);
});

it('lets a service account token write what its role and scope allow, audited as the account', function () {
    pdnsLab();
    [$owner, $org] = $this->customerWithOrganization();
    $zone = DnsZone::query()->create(['organization_id' => $org->id, 'name' => 'sa-zone.cz', 'provider' => 'powerdns', 'provider_instance_id' => pdnsLab()->id,
        'serial' => 1, 'version' => 1, 'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']]);
    $created = saApiCreate($this, $owner, $org, ['name' => 'external-dns', 'role' => 'dns_manager', 'scopes' => ['dns:write']])->assertCreated();
    $plain = (string) $created->json('token');

    saApiBearer($this, $plain, $org)->postJson("/v1/dns/zones/{$zone->id}/changes", ['change' => 'add', 'record' => ['name' => 'api', 'type' => 'A', 'content' => '192.0.2.11', 'ttl' => 300]], ['Idempotency-Key' => 'sa-stage-1'])
        ->assertCreated();
    $audit = AuditEvent::query()->where('action', 'dns.stage')->where('result', 'succeeded')->sole();
    expect($audit->actor_type)->toBe('service_account')
        ->and($audit->actor_id)->toBe((string) $created->json('data.id'));
});

it('answers a service account token with all scopes on every GET route without a server error', function () {
    // security review of PR #72 (M1): GET /v1/services/{id}/features handed the account to code typed for a person — a 500.
    // Every GET route is called with an organization admin's account token holding every scope: a refusal (401/403/404/422,
    // `person_required` included) is an answer, a 5xx is a bug
    Http::fake();
    // the staff routes answer a token 401, which the failed-authentication throttle counts: without it the sweep ends in 429s
    $this->withoutMiddleware([ThrottleRequests::class, ThrottleFailedAuth::class]);
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'sa-sweep.cz', 'fqdn_unicode' => 'sa-sweep.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addYear()]);
    $zone = DnsZone::query()->create(['organization_id' => $org->id, 'domain_id' => $domain->id, 'name' => 'sa-sweep.cz', 'provider' => 'powerdns', 'provider_instance_id' => pdnsLab()->id, 'serial' => 1, 'version' => 1, 'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz']]);
    $created = saApiCreate($this, $owner, $org, ['name' => 'sweep', 'role' => 'org_admin', 'scopes' => TokenScopes::ALL])->assertCreated();
    $plain = (string) $created->json('token');
    $ids = ['service' => $service->id, 'organization' => $org->id, 'zone' => $zone->id, 'domain' => $domain->id, 'account' => (string) $created->json('data.id')];

    $errors = [];
    $calls = 0;
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'v1/') || ! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $uri = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($ids[$m[1]] ?? 'x'), $route->uri());
        $response = saApiBearer($this, $plain, $org)->getJson($uri);
        $calls++;
        if ($response->getStatusCode() === 429) {
            $errors[] = "GET {$uri} → 429: throttled, the route was never reached";
        }
        // a provider answer the empty fake cannot give (`provider_*`, 502) is the panel's, the same for a person; anything else is ours
        if ($response->getStatusCode() >= 500 && ! str_starts_with((string) $response->json('error'), 'provider_')) {
            $errors[] = "GET {$uri} → {$response->getStatusCode()} ".substr((string) $response->getContent(), 0, 200);
        }
    }
    expect($calls)->toBeGreaterThan(100)
        ->and($errors)->toBe([], "A service account token met a server error:\n".implode("\n", $errors));
    // and the route of the finding answers: what this account may do with the service
    saApiBearer($this, $plain, $org)->getJson("/v1/services/{$service->id}/features")->assertOk()->assertJsonStructure(['data' => ['features', 'actions']]);
});
