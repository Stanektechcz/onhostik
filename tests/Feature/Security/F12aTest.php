<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;

/*
 * F12a (TASK-0106): follow-ups of the identity and API audit.
 *
 * 1. A test that signs in a member of staff names a role RoleCatalog knows. A made-up key binds no permission at all, so since
 *    D2 the staff guard refuses that person before the permission the test was written for is ever asked — the 403 proved the
 *    guard, not the permission (three tests did it with a `support_agent` that never existed).
 * 2. `X-Organization` naming somebody else's organization answers a stranger the 404 an identifier that does not exist gets —
 *    a 403 "not a member" confirmed the organization exists (the existence oracle TASK-0098 closed for rows). A party of that
 *    organization who lacks the permission keeps the 403 that names what to ask for.
 * 3. `GET /v1/me` with a service account's token answers who that is — the account, its organization, its role and the token's
 *    scopes — instead of `person_required`: a pipeline checks its credential the way a person's token does. The endpoints that
 *    act for a person still refuse it.
 */

/** A service account of `$org` created by its owner in the portal; returns the plain token and the answer. @return array{0: string, 1: array<string, mixed>} */
function f12aServiceAccount(mixed $test, User $owner, Organization $org, string $role, array $scopes): array
{
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $created = $test->actingAs($owner, 'sanctum')->postJson('/v1/service-accounts', ['name' => 'CI pipeline', 'role' => $role, 'scopes' => $scopes], ['X-Organization' => $org->id, 'Idempotency-Key' => (string) Str::ulid()])->assertCreated();
    app('auth')->forgetGuards();
    $test->flushHeaders();

    return [(string) $created->json('token'), (array) $created->json('data')];
}

/** A current member of `$org` in role `$role` (membership and the binding that carries the role). */
function f12aMember(Organization $org, string $role, string $email): User
{
    $user = test()->customer(['email' => $email]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'state' => 'active', 'role_key' => $role, 'joined_at' => now()]);

    return $user;
}

it('signs staff in only with role keys RoleCatalog knows', function () {
    $pattern = '/(?<![A-Za-z0-9_])(?:staff|steppedUpStaff)\(\s*[\'"]([^\'"]+)[\'"]/';
    $calls = 0;
    $unknown = [];
    foreach (File::allFiles(base_path('tests')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        foreach (preg_split('/\R/', $file->getContents()) ?: [] as $index => $line) {
            if (preg_match_all($pattern, $line, $matches) === 0) {
                continue;
            }
            foreach ($matches[1] as $role) {
                $calls++;
                if (! RoleCatalog::exists($role)) {
                    $unknown[] = str_replace('\\', '/', $file->getRelativePathname()).':'.($index + 1)." {$role}";
                }
            }
        }
    }

    expect($calls)->toBeGreaterThan(100) // the scan itself still finds the helper calls: a renamed helper must not make this pass silently
        ->and($unknown)->toBe([]);
});

it('answers a stranger naming a foreign organization exactly as an organization that does not exist', function () {
    [$stranger] = $this->customerWithOrganization(['email' => 'cizi@f12a.cz'], ['name' => 'Cizí s.r.o.']);
    [, $foreign] = $this->customerWithOrganization(['email' => 'majitel@f12a.cz'], ['name' => 'Firma s.r.o.']);
    $this->actingAs($stranger, 'sanctum');

    $missing = $this->getJson('/v1/services', ['X-Organization' => 'org_doesnotexist'])->assertNotFound();
    $named = $this->getJson('/v1/services', ['X-Organization' => $foreign->id])->assertNotFound();
    expect($named->json('error'))->toBe($missing->json('error'))->toBe('not_found')
        ->and($named->json('message'))->toBe($missing->json('message'));
    $this->getJson('/v1/services?organization='.$foreign->id)->assertNotFound()->assertJsonPath('error', 'not_found');
    $this->getJson('/v1/webhooks', ['X-Organization' => $foreign->id])->assertNotFound()->assertJsonPath('error', 'not_found');
});

it('keeps the 403 for a member of that organization who lacks the permission, and lets staff with the customer view in', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'majitel2@f12a.cz'], ['name' => 'Firma 2 s.r.o.']);
    $viewer = f12aMember($org, 'viewer', 'ctenar@f12a.cz');

    $this->actingAs($viewer, 'sanctum')->getJson('/v1/services', ['X-Organization' => $org->id])->assertOk();
    $this->getJson('/v1/webhooks', ['X-Organization' => $org->id])->assertForbidden()->assertJsonPath('message', 'Missing permission organization.manage');

    // staff with the customer view reach the organization; what they may do there is still their permission's (not a 404)
    $this->actingAs($this->staff('support_l1'), 'sanctum')->getJson('/v1/webhooks', ['X-Organization' => $org->id])->assertForbidden()->assertJsonPath('message', 'Missing permission organization.manage');
});
it('tells a service account token who it is on GET /v1/me, and nothing about a person', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'majitel3@f12a.cz'], ['name' => 'Pipeline s.r.o.']);
    [$plain, $account] = f12aServiceAccount($this, $owner, $org, 'developer', ['services:read', 'tickets:write']);

    $me = $this->withToken($plain)->getJson('/v1/me')->assertOk()->json('data');
    expect($me)->toBe([
        'type' => 'service_account',
        'account' => ['id' => $account['id'], 'name' => 'CI pipeline'],
        'organization' => ['id' => $org->id, 'name' => 'Pipeline s.r.o.'],
        'role' => 'developer',
        'scopes' => ['services:read', 'tickets:write'],
        'token' => ['id' => $account['tokens'][0]['id'], 'expires_at' => $account['tokens'][0]['expires_at']],
    ]);
    // its own organization named is the same answer; another one is refused as for any token
    $this->withToken($plain)->getJson('/v1/me', ['X-Organization' => $org->id])->assertOk()->assertJsonPath('data.account.id', $account['id']);
    [, $other] = $this->customerWithOrganization(['email' => 'jiny@f12a.cz']);
    $this->withToken($plain)->getJson('/v1/me', ['X-Organization' => $other->id])->assertForbidden()->assertJsonPath('error', 'token_organization_mismatch');
    // what acts for a person still refuses it
    $this->flushHeaders();
    $this->withToken($plain)->getJson('/v1/tickets')->assertForbidden()->assertJsonPath('error', 'person_required');
});

it('still answers a person their own record on GET /v1/me', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'osoba@f12a.cz']);
    $me = $this->actingAs($owner, 'sanctum')->getJson('/v1/me', ['X-Organization' => $org->id])->assertOk()->json('data');
    expect($me['type'])->toBe('person')->and($me['user']['id'])->toBe($owner->id)->and($me['organization']['id'])->toBe($org->id);
});
