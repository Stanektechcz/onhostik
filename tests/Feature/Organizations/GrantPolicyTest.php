<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Commands\OrganizationsCommandHandler;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0036 (permission program P0-07 / P0-10, IF-1 IF-2 IF-3 IF-12): a grant never goes beyond what the granter holds.
 * Every test below reproduces an exploit that worked against 3b2a9fb; GrantPolicy is the one place that now decides who
 * may grant, change or remove which role for whom.
 */

/** A member of the organization in one role, joined now. */
function grantPolicyMember(Organization $org, string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    app(OrganizationService::class)->attachMember($org, $user, $role, CommandContext::system('test'), true);

    return $user;
}

/** An invitation whose token the test knows — a link that was mailed earlier and is still valid. */
function grantPolicyInvitation(Organization $org, string $email, string $role): string
{
    $token = Str::random(48);
    OrganizationInvitation::query()->create(['organization_id' => $org->id, 'email' => mb_strtolower($email), 'role_key' => $role, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);

    return $token;
}

function grantPolicyRole(Organization $org, User $user): ?string
{
    return OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $user->id)->value('role_key');
}

function grantPolicyStepUp(User ...$people): void
{
    foreach ($people as $person) {
        app(StepUpService::class)->grant($person, 'totp', null, '127.0.0.1'); // so that a refusal below is the rule's, not the step-up's
    }
}

// ── IF-1 / TD-1: the owner stays the owner ─────────────────────────────────────────────────────────────────────────────

it('keeps the owner an owner when they accept a lower invitation, and invites nobody who already is a member', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = grantPolicyMember($org, 'org_admin');
    $developer = grantPolicyMember($org, 'developer');
    grantPolicyStepUp($admin);

    // an administrator invites the owner's own address as a viewer: accepting the mail used to overwrite the owner binding
    $this->actingAs($admin, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())
        ->postJson("/v1/organizations/{$org->id}/invitations", ['email' => $owner->email, 'role' => 'viewer'])->assertStatus(409)->assertJsonPath('error', 'already_member');
    $this->flushHeaders()->withHeader('Idempotency-Key', (string) Str::ulid())
        ->postJson("/v1/organizations/{$org->id}/invitations", ['email' => $developer->email, 'role' => 'viewer'])->assertStatus(409)->assertJsonPath('error', 'already_member');
    $this->flushHeaders();

    // a link mailed before (or forged into the table by a bug elsewhere) still cannot lower the owner
    $lower = grantPolicyInvitation($org, $owner->email, 'viewer');
    $this->actingAs($owner, 'sanctum')->postJson('/v1/organizations/invitations/accept', ['token' => $lower])->assertOk()->assertJsonPath('data.role', 'owner');
    expect(grantPolicyRole($org, $owner))->toBe('owner')
        ->and(PolicyBinding::query()->where('principal_id', $owner->id)->where('scope_type', 'organization')->where('scope_id', $org->id)->value('role_key'))->toBe('owner');

    // …nor any other member: accepting never lowers a current membership
    $stale = grantPolicyInvitation($org, $developer->email, 'viewer');
    $this->actingAs($developer, 'sanctum')->postJson('/v1/organizations/invitations/accept', ['token' => $stale])->assertOk()->assertJsonPath('data.role', 'developer');
    expect(grantPolicyRole($org, $developer))->toBe('developer');

    // what stays: a link that gives MORE than the member has is still accepted
    $viewer = grantPolicyMember($org, 'viewer');
    $up = grantPolicyInvitation($org, $viewer->email, 'developer');
    $this->actingAs($viewer, 'sanctum')->postJson('/v1/organizations/invitations/accept', ['token' => $up])->assertOk()->assertJsonPath('data.role', 'developer');

    // and the service itself refuses to write anything but `owner` for the organization's owner, whoever calls it
    expect(fn () => app(OrganizationService::class)->attachMember($org, $owner, 'viewer', CommandContext::system('test')))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('owner_role_locked'));
    expect(grantPolicyRole($org, $owner))->toBe('owner');
});

// ── IF-2 / TD-2, TD-5: only current members are changed, and only by somebody who covers them ────────────────────────────

it('refuses role changes, removals and ownership moves aimed at somebody who is not a member', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$otherOwner] = $this->customerWithOrganization(); // somebody else's customer, known to an attacker only by id
    grantPolicyStepUp($owner);
    $this->actingAs($owner, 'sanctum');

    // `change_role` with a stranger's id used to make them a member without any invitation — and list their e-mail
    $this->withHeader('Idempotency-Key', (string) Str::ulid())->patchJson("/v1/organizations/{$org->id}/members/{$otherOwner->id}", ['role' => 'viewer'])->assertNotFound();
    expect(grantPolicyRole($org, $otherOwner))->toBeNull()
        ->and(collect($this->flushHeaders()->getJson("/v1/organizations/{$org->id}")->json('data.members'))->pluck('email')->all())->not->toContain($otherOwner->email);

    // removing a stranger said "removed" and told every listener a member left
    $this->withHeader('Idempotency-Key', (string) Str::ulid())->deleteJson("/v1/organizations/{$org->id}/members/{$otherOwner->id}")->assertNotFound();
    expect(OutboxMessage::query()->where('name', 'organization.member.removed')->where('organization_id', $org->id)->exists())->toBeFalse();
    $this->flushHeaders();

    // ownership moves only to a current member
    $command = new OrganizationCommand($org->id, 'transfer-stranger', ['op' => 'transfer_ownership', 'user_id' => $otherOwner->id]);
    expect(fn () => app(OrganizationsCommandHandler::class)->handle($command, $this->contextFor($owner, $org, 'totp')))
        ->toThrow(fn (DomainError $e) => expect($e->status)->toBe(404));
    expect($org->fresh()->owner_user_id)->toBe($owner->id)->and(grantPolicyRole($org, $otherOwner))->toBeNull();
});

it('lets nobody demote or remove a member whose role they do not cover', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = grantPolicyMember($org, 'org_admin');
    $billing = grantPolicyMember($org, 'billing_admin'); // spends the credit — something an organization admin is withheld (owner decision 20)
    $developer = grantPolicyMember($org, 'developer');
    $viewer = grantPolicyMember($org, 'viewer');
    grantPolicyStepUp($owner, $admin);
    $as = fn (User $actor) => $this->actingAs($actor, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid());

    $as($admin)->patchJson("/v1/organizations/{$org->id}/members/{$billing->id}", ['role' => 'viewer'])->assertForbidden()->assertJsonPath('error', 'member_above_own');
    $as($admin)->deleteJson("/v1/organizations/{$org->id}/members/{$billing->id}")->assertForbidden()->assertJsonPath('error', 'member_above_own');
    expect(grantPolicyRole($org, $billing))->toBe('billing_admin');

    // a member on a role nobody knows any more is covered by nobody but the owner (unknown keys fail closed)
    OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $viewer->id)->update(['role_key' => 'legacy_reseller']);
    $as($admin)->deleteJson("/v1/organizations/{$org->id}/members/{$viewer->id}")->assertForbidden()->assertJsonPath('error', 'member_above_own');
    $as($admin)->patchJson("/v1/organizations/{$org->id}/members/{$developer->id}", ['role' => 'legacy_reseller'])->assertStatus(422)->assertJsonPath('error', 'invalid_role');

    // what stays: the administrator changes and removes what their own role covers; the owner covers everybody
    $as($admin)->patchJson("/v1/organizations/{$org->id}/members/{$developer->id}", ['role' => 'viewer'])->assertOk();
    $as($admin)->deleteJson("/v1/organizations/{$org->id}/members/{$developer->id}")->assertOk()->assertJsonPath('removed', true);
    $as($owner)->patchJson("/v1/organizations/{$org->id}/members/{$billing->id}", ['role' => 'viewer'])->assertOk();
    $as($owner)->deleteJson("/v1/organizations/{$org->id}/members/{$viewer->id}")->assertOk();
});

it('tells the listeners that the previous owner was moved to another role by an ownership transfer', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $heir = grantPolicyMember($org, 'org_admin');
    $command = new OrganizationCommand($org->id, 'transfer-heir', ['op' => 'transfer_ownership', 'user_id' => $heir->id]);
    app(OrganizationsCommandHandler::class)->handle($command, $this->contextFor($owner, $org, 'totp'));

    expect($org->fresh()->owner_user_id)->toBe($heir->id)->and(grantPolicyRole($org, $owner))->toBe('org_admin')->and(grantPolicyRole($org, $heir))->toBe('owner');
    $changed = OutboxMessage::query()->where('name', 'organization.member.role_changed')->where('organization_id', $org->id)->get();
    expect($changed->first(fn ($m) => data_get($m->payload, 'user_id') === $owner->id && data_get($m->payload, 'from') === 'owner' && data_get($m->payload, 'to') === 'org_admin'))->not->toBeNull()
        ->and($changed->first(fn ($m) => data_get($m->payload, 'user_id') === $heir->id && data_get($m->payload, 'to') === 'owner'))->not->toBeNull();
});

// ── IF-3 / TD-3: project roles go through the same policy ──────────────────────────────────────────────────────────────

it('puts project roles behind member management, its step-up and an allow-list of project roles', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = grantPolicyMember($org, 'org_admin');
    $member = grantPolicyMember($org, 'viewer');
    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $project = $this->postJson("/v1/organizations/{$org->id}/projects", ['name' => 'E-shop'])->assertCreated()->json('project.id');
    $add = fn (User $actor, array $body) => $this->actingAs($actor, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/organizations/{$org->id}/projects/{$project}/members", $body);

    // a project role that can delete VMs was handed out under `project.manage` — a NORMAL permission, no fresh step-up
    $add($owner, ['user_id' => $member->id, 'role' => 'cloud_operator'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(ProjectMembership::query()->where('project_id', $project)->exists())->toBeFalse();

    grantPolicyStepUp($owner, $admin);
    // an organization role is not a project role: an org admin "inside one project" manages the organization's members
    $add($owner, ['user_id' => $member->id, 'role' => 'org_admin'])->assertStatus(422)->assertJsonPath('error', 'invalid_role');
    $add($owner, ['user_id' => $member->id, 'role' => 'billing_admin'])->assertStatus(422)->assertJsonPath('error', 'invalid_role');
    // nobody gives themselves a project role
    $add($admin, ['user_id' => $admin->id, 'role' => 'developer'])->assertForbidden()->assertJsonPath('error', 'self_membership_locked');
    // removing somebody who has no role in the project is not "removed"
    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->deleteJson("/v1/organizations/{$org->id}/projects/{$project}/members/{$stranger->id}")->assertNotFound();
    expect(OutboxMessage::query()->where('name', 'project.member.removed')->exists())->toBeFalse();

    // what stays: a project role from the allow-list, given by somebody who covers it, and taken back
    $add($admin, ['user_id' => $member->id, 'role' => 'developer'])->assertCreated()->assertJsonPath('membership.role_key', 'developer');
    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->deleteJson("/v1/organizations/{$org->id}/projects/{$project}/members/{$member->id}")->assertOk();
});

// ── IF-12 / SE-5, G12: an idempotency key belongs to one organization, service, action and actor ─────────────────────────

it('keeps one organization\'s idempotency key from answering for another, and refuses the same key for another request', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$alice, $orgA] = $this->customerWithOrganization();
    [$bob, $orgB] = $this->customerWithOrganization();
    $siteA = featureWebService($orgA, 'aapanel');
    $siteB = featureWebService($orgB, 'ispconfig'); // another node: the two sites are not one panel record

    $this->actingAs($alice, 'sanctum')->withHeader('Idempotency-Key', 'deploy-1')->postJson("/v1/services/{$siteA->id}/actions", ['action' => 'https.force', 'params' => ['enabled' => true]])->assertStatus(202);
    $this->flushHeaders();
    // the same header from another customer used to return the first customer's operation — and ran nothing on their own site
    $this->actingAs($bob, 'sanctum')->withHeader('Idempotency-Key', 'deploy-1')->postJson("/v1/services/{$siteB->id}/actions", ['action' => 'https.force', 'params' => ['enabled' => true]])->assertStatus(202);
    $this->flushHeaders();
    expect(Operation::query()->where('service_id', $siteB->id)->count())->toBe(1)
        ->and(Operation::query()->where('service_id', $siteA->id)->count())->toBe(1);

    // one actor, one service, one key: a retry is answered by the same operation, a different request under that key is refused
    $services = app(ServiceService::class);
    Operation::query()->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    $context = $this->contextFor($alice, $orgA);
    $first = $services->requestAction($siteA, 'https.force', $context, 'svc-key-9', ['enabled' => true]);
    expect($services->requestAction($siteA, 'https.force', $context, 'svc-key-9', ['enabled' => true])->id)->toBe($first->id);
    expect(fn () => $services->requestAction($siteA, 'https.force', $context, 'svc-key-9', ['enabled' => false]))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('idempotency_key_reused')->and($e->status)->toBe(409));
    // the same key from another member of the same organization is their own request, not the first one's answer
    $colleague = grantPolicyMember($orgA, 'developer');
    Operation::query()->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    expect($services->requestAction($siteA, 'https.force', $this->contextFor($colleague, $orgA), 'svc-key-9', ['enabled' => true])->id)->not->toBe($first->id);
});
