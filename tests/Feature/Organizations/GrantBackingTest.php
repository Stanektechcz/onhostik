<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Red-team round on the integrated Phase-0 chain (TASK-0035/0036, permission program I6 / IF-8, audit TD-6, SS-1):
 *  · an invitation is a grant that has not landed yet — it was valid for as long as the link, whoever sent it and whatever
 *    became of them: an org_admin invited a second mailbox of their own as org_admin, was removed, clicked the link and was
 *    back. A link now works only while the person who sent it could still send it, and it is withdrawn when they cannot;
 *  · GrantPolicy and the service share skipped every rule for `is_staff`: a staff account holding
 *    organization.members.manage through its global binding made anybody anything in any customer's organization,
 *    itself included. A grant in a customer organization is now judged by what the grantor holds IN that organization.
 */

/** A member in one role, joined now (as the system: the fixture, not the rule under test). */
function grantBackingMember(Organization $org, string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    app(OrganizationService::class)->attachMember($org, $user, $role, CommandContext::system('test'), true);

    return $user;
}

/** The context of a stepped-up person in the organization (the bus asks StepUpService for a fresh grant). */
function grantBackingContext(User $actor, Organization $org): CommandContext
{
    app(StepUpService::class)->grant($actor, 'totp', null, '127.0.0.1');

    return new CommandContext('user', $actor->id, $org->id, null, '127.0.0.1', 'pest', 'test-session', stepUpMethod: 'totp');
}

/** `$actor` invites `$email` through the bus (HIGH, stepped up); returns the token the mail would carry. */
function grantBackingInvite(User $actor, Organization $org, string $email, string $role): string
{
    $command = new OrganizationCommand($org->id, 'invite-'.Str::ulid(), ['op' => 'invite', 'email' => $email, 'role' => $role]);

    return (string) app(CommandBus::class)->dispatch($command, grantBackingContext($actor, $org))['token'];
}

/** `$actor` runs a member operation through the bus (HIGH, stepped up) — the listeners run as they do after a request. */
function grantBackingMemberOp(User $actor, Organization $org, array $payload): mixed
{
    return app(CommandBus::class)->dispatch(new OrganizationCommand($org->id, 'member-'.Str::ulid(), $payload), grantBackingContext($actor, $org));
}

function grantBackingAccept(User $person, string $token)
{
    return test()->flushHeaders()->actingAs($person, 'sanctum')->postJson('/v1/organizations/invitations/accept', ['token' => $token]);
}

function grantBackingRole(Organization $org, User $user): ?string
{
    return OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $user->id)->value('role_key');
}

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
});

// ── an invitation lives only as long as its sender could send it ──────────────────────────────────────────────────────

it('withdraws every link a removed member sent, so their second mailbox cannot bring them back', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = grantBackingMember($org, 'org_admin');
    $alternate = User::factory()->create(['email' => 'druha-schranka@example.cz']);
    $colleague = User::factory()->create(['email' => 'kolega@example.cz']);
    $mine = grantBackingInvite($admin, $org, $alternate->email, 'org_admin'); // the way back in, prepared before leaving
    $theirs = grantBackingInvite($admin, $org, $colleague->email, 'viewer');
    $ownersOwn = grantBackingInvite($owner, $org, 'nekdo@example.cz', 'viewer'); // not the removed member's: stays

    grantBackingMemberOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);

    grantBackingAccept($alternate, $mine)->assertStatus(410)->assertJsonPath('error', 'invitation_invalid');
    grantBackingAccept($colleague, $theirs)->assertStatus(410);
    expect(grantBackingRole($org, $alternate))->toBeNull()->and(grantBackingRole($org, $colleague))->toBeNull()
        ->and(OrganizationInvitation::query()->where('token_hash', hash('sha256', $ownersOwn))->sole()->isUsable())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'organization.member.invite.revoke')->where('organization_id', $org->id)->count())->toBe(2);
});

it('withdraws the links a demoted member can no longer send, and keeps the ones their new role still covers', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = grantBackingMember($org, 'org_admin');
    $up = User::factory()->create();
    $same = User::factory()->create();
    $toAdmin = grantBackingInvite($admin, $org, $up->email, 'org_admin');
    $toViewer = grantBackingInvite($admin, $org, $same->email, 'viewer');

    // the owner makes the admin a developer: a developer invites nobody (organization.members.manage) — both links go
    grantBackingMemberOp($owner, $org, ['op' => 'change_role', 'user_id' => $admin->id, 'role' => 'developer']);
    grantBackingAccept($up, $toAdmin)->assertStatus(410);
    grantBackingAccept($same, $toViewer)->assertStatus(410);
    expect(grantBackingRole($org, $up))->toBeNull()->and(grantBackingRole($org, $same))->toBeNull();

    // an ownership transfer makes the previous owner an org_admin: their billing_admin link (credit spending) goes, the viewer link stays
    $heir = grantBackingMember($org, 'org_admin');
    $billing = User::factory()->create();
    $viewer = User::factory()->create();
    $toBilling = grantBackingInvite($owner, $org, $billing->email, 'billing_admin');
    $toViewer = grantBackingInvite($owner, $org, $viewer->email, 'viewer');
    app(OrganizationService::class)->transferOwnership($org, $heir, $this->contextFor($owner, $org, 'totp'));
    app(OutboxPublisher::class)->relayPending();
    grantBackingAccept($billing, $toBilling)->assertStatus(410);
    grantBackingAccept($viewer, $toViewer)->assertOk()->assertJsonPath('data.role', 'viewer');
});

it('asks at the click whether the sender could still send the link, whatever the listeners did or did not do yet', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = grantBackingMember($org, 'org_admin');
    $alternate = User::factory()->create();
    $token = grantBackingInvite($admin, $org, $alternate->email, 'org_admin');

    // the removal is written, its event not delivered yet (a relay that has not run, a listener that failed)
    OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $admin->id)->delete();
    PolicyBinding::query()->where('principal_id', $admin->id)->where('organization_id', $org->id)->delete();
    app(Authorizer::class)->flush();
    grantBackingAccept($alternate, $token)->assertStatus(410)->assertJsonPath('error', 'invitation_invalid');
    expect(grantBackingRole($org, $alternate))->toBeNull()
        ->and(OrganizationInvitation::query()->where('token_hash', hash('sha256', $token))->sole()->accepted_at)->toBeNull();

    // a sender demoted in the meantime backs nothing above their new role
    $second = grantBackingMember($org, 'org_admin');
    $other = User::factory()->create();
    $token = grantBackingInvite($second, $org, $other->email, 'org_admin');
    OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $second->id)->update(['role_key' => 'developer']);
    PolicyBinding::query()->where('principal_id', $second->id)->where('organization_id', $org->id)->update(['role_key' => 'developer']);
    app(Authorizer::class)->flush();
    grantBackingAccept($other, $token)->assertStatus(410);
    expect(grantBackingRole($org, $other))->toBeNull();

    // a link nobody sent (no inviter on record) is nobody's grant: it fails closed
    $stranger = User::factory()->create();
    $orphan = Str::random(48);
    OrganizationInvitation::query()->create(['organization_id' => $org->id, 'email' => mb_strtolower($stranger->email), 'role_key' => 'viewer', 'token_hash' => hash('sha256', $orphan), 'expires_at' => now()->addDays(7)]);
    grantBackingAccept($stranger, $orphan)->assertStatus(410);

    // and a link from somebody who still may send it works as before
    $fine = User::factory()->create();
    grantBackingAccept($fine, grantBackingInvite($owner, $org, $fine->email, 'developer'))->assertOk()->assertJsonPath('data.role', 'developer');
});

// ── staff are judged by what they hold in the customer's organization ─────────────────────────────────────────────────

it('gives a staff account no grant right in a customer organization through its global binding', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $developer = grantBackingMember($org, 'developer');
    $staff = $this->steppedUpStaff('platform_owner');
    $h = fn () => $this->flushHeaders()->actingAs($staff, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid());

    $h()->postJson("/v1/organizations/{$org->id}/invitations", ['email' => 'podpora-alt@example.cz', 'role' => 'org_admin'])->assertForbidden()->assertJsonPath('error', 'role_above_own');
    $h()->patchJson("/v1/organizations/{$org->id}/members/{$developer->id}", ['role' => 'org_admin'])->assertForbidden()->assertJsonPath('error', 'member_above_own');
    $h()->deleteJson("/v1/organizations/{$org->id}/members/{$developer->id}")->assertForbidden()->assertJsonPath('error', 'member_above_own');
    expect(grantBackingRole($org, $developer))->toBe('developer')
        ->and(OrganizationInvitation::query()->where('organization_id', $org->id)->where('email', 'podpora-alt@example.cz')->exists())->toBeFalse();

    // a staff account that is ALSO a member is judged by that membership: a viewer makes nobody an administrator, itself least
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $staff->id, 'role_key' => 'viewer', 'state' => 'active', 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $staff->id, 'role_key' => 'viewer', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    app(Authorizer::class)->flush();
    $h()->patchJson("/v1/organizations/{$org->id}/members/{$staff->id}", ['role' => 'org_admin'])->assertForbidden();
    expect(grantBackingRole($org, $staff))->toBe('viewer');
});

it('lets a staff account share a customer service only with what it holds on that service itself', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'aapanel');
    $staff = $this->steppedUpStaff('platform_owner');

    $this->actingAs($staff, 'sanctum')->postJson("/v1/services/{$shop->id}/access", ['email' => 'kamarad@example.cz', 'capabilities' => ['console']], ['X-Organization' => $org->id, 'Idempotency-Key' => 'staff-share-1'])
        ->assertForbidden()->assertJsonPath('error', 'capability_above_own');
    expect(ServiceAccessGrant::query()->where('service_id', $shop->id)->exists())->toBeFalse()
        ->and(OrganizationInvitation::query()->where('organization_id', $org->id)->exists())->toBeFalse();
});
