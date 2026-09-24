<?php

declare(strict_types=1);

use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;

/*
 * „Po zrušení služeb zůstává klientský účet v ONHOST panelu.“ — the owner, 2026-09-24.
 *
 * Nothing closes an account on its own when the last service ends — the one path that does is the customer's request
 * to erase it (GDPR art. 17), and that path was open to anybody who could edit the organization's profile. Any
 * member with `org_admin` could POST {kind: deletion}; within half an hour the scheduler anonymised every member,
 * the owner included (their e-mail and password overwritten, no way back in), closed the organization and erased the
 * archives of its cancelled services. No step-up, no second look, no way to take it back. The catalogue already says
 * what this is — `organization.close`, "Close the organization and schedule data deletion", held by the owner alone —
 * and nothing asked for it.
 *
 * Now the erasure is the owner's deliberate act: their permission, a fresh step-up, and a period in which anybody who
 * manages the organization can still stop it. And an account that still holds a registered domain is not erased
 * under it — the domain would go on renewing for a closed organization with an address nobody reads.
 */

function erasureAdmin(Organization $org): User
{
    $admin = User::query()->create(['email' => 'admin@erasure.test', 'name' => 'Admin', 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $admin->id, 'role_key' => 'org_admin', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $admin->id, 'state' => 'active', 'role_key' => 'org_admin', 'joined_at' => now()]);

    return $admin;
}

it('does not let an administrator erase the account — only its owner may', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = erasureAdmin($org);
    app(StepUpService::class)->grant($admin, 'totp', null, '127.0.0.1');

    $this->actingAs($admin, 'sanctum')->withHeaders(['X-Organization' => $org->id])
        ->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertForbidden();

    expect(DataRequest::query()->where('kind', 'deletion')->exists())->toBeFalse()
        ->and($owner->fresh()->state)->toBe('active');
});

it('asks the owner to confirm who they are before scheduling the erasure', function () {
    [$owner, $org] = $this->customerWithOrganization();

    $this->actingAs($owner, 'sanctum')->withHeaders(['X-Organization' => $org->id])
        ->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertForbidden()->assertJsonPath('error', 'step_up_required');

    expect(DataRequest::query()->where('kind', 'deletion')->exists())->toBeFalse();
});

it('waits out a grace period before erasing, and any manager can stop it meanwhile', function () {
    [$owner, $org] = $this->customerWithOrganization();
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $id = $this->actingAs($owner, 'sanctum')->withHeaders(['X-Organization' => $org->id])
        ->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertStatus(202)->json('data.id');
    $request = DataRequest::query()->findOrFail($id);
    expect($request->meta['execute_after'] ?? null)->not->toBeNull();

    // the next runs of the scheduler leave it alone: the account is still there, the owner can still sign in
    expect(app(ComplianceService::class)->processDataRequests(now()->addDays(3))['deleted'])->toBe(0)
        ->and($owner->fresh()->state)->toBe('active')->and($request->fresh()->state)->toBe('requested');

    // an administrator stops it (a way back needs no special right; going through with it does)
    $admin = erasureAdmin($org);
    $this->actingAs($admin, 'sanctum')->withHeaders(['X-Organization' => $org->id])
        ->postJson("/v1/data-requests/{$id}/cancel")->assertOk()->assertJsonPath('data.state', 'cancelled');

    expect(app(ComplianceService::class)->processDataRequests(now()->addDays(30))['deleted'])->toBe(0)
        ->and($owner->fresh()->state)->toBe('active')->and(Organization::query()->findOrFail($org->id)->state)->not->toBe('closed');
});

it('erases the account once the grace period is over', function () {
    [$owner, $org] = $this->customerWithOrganization();
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum')->withHeaders(['X-Organization' => $org->id])
        ->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertStatus(202);

    $days = (int) config('onhost.compliance.deletion_grace_days');
    expect(app(ComplianceService::class)->processDataRequests(now()->addDays($days)->addHour())['deleted'])->toBe(1)
        ->and($owner->fresh()->state)->toBe('deleted');
});

it('does not erase an account that still holds a registered domain', function () {
    [$owner, $org] = $this->customerWithOrganization();
    Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'still-mine.cz', 'fqdn_unicode' => 'still-mine.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE,
        'registered_at' => now()->subMonths(3), 'expires_at' => now()->addMonths(9), 'dns_provider' => 'external']);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->actingAs($owner, 'sanctum')->withHeaders(['X-Organization' => $org->id])
        ->postJson('/v1/data-requests', ['kind' => 'deletion'])->assertStatus(409)->assertJsonPath('error', 'deletion_blocked')
        ->assertJsonFragment(['active_domains']);
});
