<?php

declare(strict_types=1);

use App\Http\Support\CurrentOrganization;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;

/*
 * TASK-0070 (audit 2026-10, package C11): a person in several organizations chooses which one the portal works in. The panel
 * used to act for the first membership always — the boot object, the data script (loaded without ?organization) and the
 * X-Organization the bridge sends. The choice lives in the web session and only one of the person's current memberships can be
 * chosen; a membership that ends takes the choice with it.
 */

/** @return array{0: User, 1: Organization, 2: Organization} a person in Alfa (first) and Beta */
function orgswTwo(): array
{
    $user = User::factory()->create();
    $alfa = app(OrganizationService::class)->create($user, ['name' => 'Alfa s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('orgsw'));
    test()->travel(1)->seconds();
    $beta = app(OrganizationService::class)->create($user, ['name' => 'Beta s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('orgsw'));

    return [$user, $alfa, $beta];
}

function orgswService(Organization $org, string $host): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'label' => $host, 'hostname' => $host, 'region_code' => 'cz1',
        'state' => ServiceStateMachine::ACTIVE, 'desired_spec' => [], 'entitlements' => [], 'tags' => [], 'health' => []]);
}

function orgswPortal($test)
{
    return $test->withHeader('Referer', 'http://localhost/panel');
}

it('switches the portal to another organization of the person: boot, data script and the API default follow', function () {
    [$user, $alfa, $beta] = orgswTwo();
    $inBeta = orgswService($beta, 'beta-app.example.cz');
    orgswService($alfa, 'alfa-app.example.cz');
    $this->actingAs($user, 'web');

    $before = $this->get('/panel')->assertOk()->getContent();
    expect($before)->toContain('"organization":{"id":"'.$alfa->id.'"')->toContain('"organizations":[');

    orgswPortal($this)->putJson('/v1/me/organization', ['organization_id' => $beta->id])->assertOk()->assertJsonPath('data.organization.id', $beta->id);
    expect(AuditEvent::query()->where('action', 'identity.organization.switch')->where('organization_id', $beta->id)->exists())->toBeTrue();

    $after = $this->get('/panel')->assertOk()->getContent();
    expect($after)->toContain('"organization":{"id":"'.$beta->id.'"')
        ->toContain('src="/surfaces/onhost-panel.js?t=')->toContain('&amp;organization='.$beta->id.'"');
    // the data script without a parameter, and an API call naming no organization, act for the chosen one
    expect($this->get('/surfaces/onhost-panel.js')->assertOk()->getContent())->toContain('"organization":"'.$beta->id.'"')->toContain('beta-app.example.cz')->not->toContain('alfa-app.example.cz');
    expect(array_column(orgswPortal($this)->getJson('/v1/services')->assertOk()->json('data'), 'id'))->toContain($inBeta->id);
});

it('refuses to switch to an organization the person is not a member of, and to one that does not exist', function () {
    [$user, $alfa] = orgswTwo();
    [, $stranger] = $this->customerWithOrganization();
    $this->actingAs($user, 'web');

    orgswPortal($this)->putJson('/v1/me/organization', ['organization_id' => $stranger->id])->assertForbidden();
    orgswPortal($this)->putJson('/v1/me/organization', ['organization_id' => '01JUNKNOWNORGANIZATION0000'])->assertForbidden();
    orgswPortal($this)->putJson('/v1/me/organization', [])->assertUnprocessable();

    expect(session(CurrentOrganization::SESSION_KEY))->toBeNull()
        ->and($this->get('/panel')->assertOk()->getContent())->toContain('"organization":{"id":"'.$alfa->id.'"')->not->toContain($stranger->id);
});

it('refuses the switch to staff for an organization they are not a member of', function () {
    [, $customerOrg] = $this->customerWithOrganization();
    $staff = $this->steppedUpStaff('support_l2');
    $this->actingAs($staff, 'web');

    orgswPortal($this)->putJson('/v1/me/organization', ['organization_id' => $customerOrg->id])->assertForbidden();
    expect(session(CurrentOrganization::SESSION_KEY))->toBeNull();
});

it('drops the choice when the membership ends: back to the first organization', function () {
    [$user, $alfa, $beta] = orgswTwo();
    $this->actingAs($user, 'web');
    orgswPortal($this)->putJson('/v1/me/organization', ['organization_id' => $beta->id])->assertOk();

    OrganizationMembership::query()->where('organization_id', $beta->id)->where('user_id', $user->id)->update(['state' => 'removed']);

    expect($this->get('/panel')->assertOk()->getContent())->toContain('"organization":{"id":"'.$alfa->id.'"')
        ->and(session(CurrentOrganization::SESSION_KEY))->toBeNull();
});

it('does not let an API token choose an organization', function () {
    [$user, $alfa, $beta] = orgswTwo();
    $token = $user->createToken('orgsw', TokenScopes::ALL);
    $token->accessToken->forceFill(['organization_id' => $alfa->id])->save();

    $this->withToken($token->plainTextToken)->putJson('/v1/me/organization', ['organization_id' => $beta->id])->assertForbidden();
});
