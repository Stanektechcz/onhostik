<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Commands\ApiTokenCommand;
use Onhost\Domain\Identity\Commands\MfaResetCommand;
use Onhost\Domain\Identity\Commands\OwnerRecoveryCommand;
use Onhost\Domain\Identity\Commands\StaffAccountCommand;
use Onhost\Domain\Identity\Commands\StaffAccountCommandHandler;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\StepUpGrant;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Commands\AcceptInvitationCommand;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Commands\OwnershipCommand;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Domain\Organizations\Models\AccessSnapshot;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Commands\ServiceAccessCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Tests\TestCase;

/*
 * TASK-0042 (permission program S1-01, D1): every grant entry point × every GrantPolicy invariant I1–I12 (program §3).
 *
 * The matrix below is the evidence the program asks for: for each entry point that grants, changes or removes access — an
 * organization membership (invite, accept, role change, removal), a project role, a service share and its revocation, the
 * ownership transfer, an access restore, an API token, a partner's attribution, staff — and each invariant, either a cell that
 * proves the rule is enforced there (a refusal, a clamp, a record), or the reason it cannot apply. An invariant nobody decided
 * for an entry point fails the completeness test at the bottom.
 */

/** A person in the organization in one role (attached as the system: the fixture, not the rule under test). */
function gmxMember(Organization $org, string $role, ?CarbonInterface $until = null): User
{
    $user = User::factory()->create();
    app(OrganizationService::class)->attachMember($org, $user, $role, CommandContext::system('gmx fixture'), true, $until);

    return $user;
}

/** The context of a person on the portal, stepped up unless said otherwise (the bus asks StepUpService for a fresh grant). */
function gmxContext(User $actor, ?Organization $org, bool $stepUp = true): CommandContext
{
    if ($stepUp) {
        app(StepUpService::class)->grant($actor, 'totp', null, '127.0.0.1');
    } else {
        StepUpGrant::query()->where('user_id', $actor->id)->delete(); // no fresh step-up left over from an earlier step of the cell
    }

    return new CommandContext('user', $actor->id, $org?->id, null, '127.0.0.1', 'pest', 'gmx-session');
}

function gmxOrgOp(User $actor, Organization $org, array $payload, bool $stepUp = true): mixed
{
    return app(CommandBus::class)->dispatch(new OrganizationCommand($org->id, 'gmx-'.Str::ulid(), $payload), gmxContext($actor, $org, $stepUp));
}

function gmxOwnership(User $actor, Organization $org, array $payload, bool $stepUp = true): mixed
{
    return app(CommandBus::class)->dispatch(new OwnershipCommand($org->id, 'gmx-own-'.Str::ulid(), $payload), gmxContext($actor, $org, $stepUp));
}

function gmxShare(User $actor, Organization $org, Service $service, array $payload, bool $stepUp = true): mixed
{
    return app(CommandBus::class)->dispatch(new ServiceAccessCommand($org->id, 'gmx-share-'.Str::ulid(), ['service_id' => $service->id] + $payload), gmxContext($actor, $org, $stepUp));
}

/** `$actor` invites `$email` through the bus; the token the mail would carry. */
function gmxInvite(User $actor, Organization $org, string $email, string $role, ?string $until = null): string
{
    return (string) gmxOrgOp($actor, $org, ['op' => 'invite', 'email' => $email, 'role' => $role] + ($until !== null ? ['access_until' => $until] : []))['token'];
}

/** The invitee accepts through the bus (S1-02: AcceptInvitationCommand), as the person signed in on the portal. */
function gmxAccept(User $person, string $token): mixed
{
    return app(CommandBus::class)->dispatch(new AcceptInvitationCommand('gmx-accept-'.Str::ulid(), ['token' => $token]), gmxContext($person, null, false));
}

function gmxService(Organization $org): Service
{
    return Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'hostname' => 'gmx-'.Str::lower(Str::random(8)).'.cz',
        'state' => ServiceStateMachine::ACTIVE, 'desired_spec' => [], 'entitlements' => [], 'tags' => [], 'health' => [],
    ]);
}

function gmxProject(Organization $org): Project
{
    return Project::query()->create(['organization_id' => $org->id, 'slug' => 'p-'.Str::lower(Str::random(6)), 'name' => 'Projekt']);
}

/** An IAM edit took a permission away from a role (the authorizer reads role_permissions): what a grantor then lacks. */
function gmxWithhold(string $role, string $permission): void
{
    DB::table('role_permissions')->where('role_key', $role)->where('permission_key', $permission)->delete();
    app(Authorizer::class)->flush();
}

/** An IAM edit gave a role one more permission than its catalogue line: somebody holding it is above an org_admin. */
function gmxAugment(string $role, string $permission): void
{
    DB::table('role_permissions')->insertOrIgnore(['role_key' => $role, 'permission_key' => $permission]);
    app(Authorizer::class)->flush();
}

function gmxRefuses(Closure $attempt, string $error): void
{
    try {
        $attempt();
    } catch (DomainError $e) {
        expect($e->error)->toBe($error, $e->getMessage());

        return;
    }
    test()->fail("Expected the refusal {$error}; the attempt went through.");
}

function gmxRole(Organization $org, User $user): ?string
{
    return OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $user->id)->value('role_key');
}

function gmxEnd(Organization $org, User $user): ?string
{
    return OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $user->id)->first()?->expires_at?->toIso8601String();
}

function gmxSnapshotOf(Organization $org, User $user, string $reason): ?AccessSnapshot
{
    return AccessSnapshot::query()->where('organization_id', $org->id)->where('user_id', $user->id)->where('reason', $reason)->latest('created_at')->first();
}

function gmxFlagged(Organization $org, string $kind): int
{
    return AuditEvent::query()->where('organization_id', $org->id)->where('action', 'organization.grant.cascade.flag')->get()
        ->filter(fn (AuditEvent $e) => data_get($e->detail, 'kind') === $kind)->count();
}

/**
 * Entry point → invariant → a proof (a closure run in the test) or the reason the invariant does not apply there.
 *
 * @return array<string, array<string, Closure(TestCase): void|string>>
 */
function gmxMatrix(): array
{
    return [
        // ── an organization membership: invitation ─────────────────────────────────────────────────────────────────────
        'invite' => [
            'I1' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                gmxRefuses(fn () => gmxInvite($admin, $org, 'billing@gmx.test', 'billing_admin'), 'role_above_own'); // spending credit is withheld from an org_admin
            },
            'I2' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxInvite($owner, $org, $owner->email, 'viewer'), 'already_member');
            },
            'I3' => 'an invitation names somebody who holds nothing in the organization yet; a current member is refused (I2, I8)',
            'I4' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxInvite($owner, $org, 'heir@gmx.test', 'owner'), 'owner_role_locked');
            },
            'I5' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $end = now()->addDays(10)->startOfSecond();
                $admin = gmxMember($org, 'org_admin', $end);
                gmxInvite($admin, $org, 'forever@gmx.test', 'developer'); // asked for no end
                gmxInvite($admin, $org, 'later@gmx.test', 'developer', now()->addDays(60)->toIso8601String()); // asked for later than their own
                $ends = OrganizationInvitation::query()->where('organization_id', $org->id)->pluck('access_expires_at', 'email');
                expect($ends['forever@gmx.test']?->toIso8601String())->toBe($end->toIso8601String())
                    ->and($ends['later@gmx.test']?->toIso8601String())->toBe($end->toIso8601String());
            },
            'I6' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $alternate = User::factory()->create();
                $token = gmxInvite($admin, $org, $alternate->email, 'org_admin');
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);
                gmxRefuses(fn () => gmxAccept($alternate, $token), 'invitation_invalid');
            },
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'invite', 'email' => 'x@gmx.test', 'role' => 'viewer'], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $developer = gmxMember($org, 'developer');
                gmxRefuses(fn () => gmxInvite($owner, $org, $developer->email, 'viewer'), 'already_member');
            },
            'I9' => 'accepting is its own entry point (accept × I9)',
            'I10' => 'an invitation changes and removes nothing; its acceptance snapshots a membership it widens (accept × I10)',
            'I11' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxInvite($owner, $org, 'svc@gmx.test', 'svc_console'), 'invalid_role'); // a service role is no organization role
            },
            'I12' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $service = gmxService($org);
                $guest = gmxMember($org, 'guest');
                gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $guest->email, 'capabilities' => ['manage']]);
                // somebody a service was shared with passes nothing on: inviting takes organization.members.manage
                gmxRefuses(fn () => gmxInvite($guest, $org, 'friend@gmx.test', 'guest'), 'access_not_approved');
            },
        ],
        // ── the invitation is accepted (S1-02: AcceptInvitationCommand through the bus) ─────────────────────────────────
        'accept' => [
            'I1' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $invitee = User::factory()->create();
                $token = gmxInvite($admin, $org, $invitee->email, 'org_admin');
                // the sender is a developer now — written without the listeners, so the check at the click is what refuses
                OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $admin->id)->update(['role_key' => 'developer']);
                DB::table('policy_bindings')->where('principal_id', $admin->id)->where('organization_id', $org->id)->update(['role_key' => 'developer']);
                app(Authorizer::class)->flush();
                gmxRefuses(fn () => gmxAccept($invitee, $token), 'invitation_invalid');
                expect(gmxRole($org, $invitee))->toBeNull();
            },
            'I2' => 'accepting an invitation of one\'s own is the one self-grant the program allows',
            'I3' => 'the grantee\'s own current membership is what accepting is compared with (I9)',
            'I4' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $token = Str::random(48);
                OrganizationInvitation::query()->create(['organization_id' => $org->id, 'email' => mb_strtolower($owner->email), 'role_key' => 'viewer', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'invited_by' => $owner->id]);
                expect(gmxAccept($owner, $token)['role'])->toBe('owner')->and(gmxRole($org, $owner))->toBe('owner');
            },
            'I5' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $end = now()->addDays(10)->startOfSecond();
                $admin = gmxMember($org, 'org_admin', $end);
                $invitee = User::factory()->create();
                $token = Str::random(48); // a link sent before the sender's own access got an end
                OrganizationInvitation::query()->create(['organization_id' => $org->id, 'email' => mb_strtolower($invitee->email), 'role_key' => 'developer', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'invited_by' => $admin->id]);
                gmxAccept($invitee, $token);
                expect(gmxEnd($org, $invitee))->toBe($end->toIso8601String());
            },
            'I6' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $service = gmxService($org);
                $friend = User::factory()->create();
                gmxShare($admin, $org, $service, ['op' => 'share', 'email' => $friend->email, 'capabilities' => ['manage']]);
                $grant = ServiceAccessGrant::query()->where('service_id', $service->id)->firstOrFail();
                expect($grant->state)->toBe(ServiceAccessGrant::PENDING);
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);
                // the pending share of the removed sharer is cancelled with its invitation (program I6: pending grants are cancelled)
                expect($grant->fresh()->state)->toBe(ServiceAccessGrant::REVOKED);
                // …and the person let in later by somebody else does not collect it
                $token = gmxInvite($owner, $org, $friend->email, 'viewer');
                expect(gmxAccept($friend, $token)['shared_services'])->toBe(0);
                expect(app(Authorizer::class)->can($friend->fresh(), 'service.manage', CommandScope::resource($service->id, $org->id)))->toBeFalse();
            },
            'I7' => 'the risk was the inviter\'s (organization.members.manage, HIGH, step-up); accepting is the invitee\'s own act',
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $invitee = User::factory()->create();
                gmxAccept($invitee, gmxInvite($owner, $org, $invitee->email, 'viewer'));
                // the way in runs through the bus: the audit row names the person and the command
                expect(AuditEvent::query()->where('action', 'organization.invitation.accept')->where('actor_id', $invitee->id)->where('result', 'succeeded')->exists())->toBeTrue();
            },
            'I9' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $developer = gmxMember($org, 'developer');
                $token = Str::random(48);
                OrganizationInvitation::query()->create(['organization_id' => $org->id, 'email' => mb_strtolower($developer->email), 'role_key' => 'viewer', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'invited_by' => $owner->id]);
                expect(gmxAccept($developer, $token)['role'])->toBe('developer');
            },
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $viewer = gmxMember($org, 'viewer');
                $token = Str::random(48);
                OrganizationInvitation::query()->create(['organization_id' => $org->id, 'email' => mb_strtolower($viewer->email), 'role_key' => 'developer', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'invited_by' => $owner->id]);
                gmxAccept($viewer, $token);
                expect(gmxSnapshotOf($org, $viewer, 'invitation_widened')?->access['membership']['role'] ?? null)->toBe('viewer');
            },
            'I11' => 'an invitation carries an organization role only (invite × I11)',
            'I12' => 'accepting passes nothing on',
        ],
        // ── a role change of a current member ──────────────────────────────────────────────────────────────────────────
        'change_role' => [
            'I1' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $developer = gmxMember($org, 'developer');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'change_role', 'user_id' => $developer->id, 'role' => 'billing_admin']), 'role_above_own');
            },
            'I2' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'change_role', 'user_id' => $admin->id, 'role' => 'developer']), 'self_membership_locked');
            },
            'I3' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $billing = gmxMember($org, 'billing_admin');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'change_role', 'user_id' => $billing->id, 'role' => 'viewer']), 'member_above_own');
            },
            'I4' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $owner->id, 'role' => 'org_admin']), 'owner_role_locked');
            },
            'I5' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $end = now()->addDays(10)->startOfSecond();
                $admin = gmxMember($org, 'org_admin', $end);
                $developer = gmxMember($org, 'developer');
                gmxOrgOp($admin, $org, ['op' => 'change_role', 'user_id' => $developer->id, 'role' => 'viewer', 'access_until' => null]);
                expect(gmxEnd($org, $developer))->toBe($end->toIso8601String());
            },
            'I6' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $developer = gmxMember($org, 'viewer');
                gmxOrgOp($admin, $org, ['op' => 'change_role', 'user_id' => $developer->id, 'role' => 'developer']); // the admin's grant
                $alternate = User::factory()->create();
                $token = gmxInvite($admin, $org, $alternate->email, 'org_admin');
                gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $admin->id, 'role' => 'viewer']);
                gmxRefuses(fn () => gmxAccept($alternate, $token), 'invitation_invalid'); // the pending grant is cancelled
                expect(gmxFlagged($org, 'membership'))->toBe(1)->and(gmxRole($org, $developer))->toBe('developer'); // the active one is recorded (switch off)
            },
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $developer = gmxMember($org, 'developer');
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $developer->id, 'role' => 'viewer'], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                [$stranger] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $stranger->id, 'role' => 'viewer']), 'not_found');
            },
            'I9' => 'a role change is not an acceptance (accept × I9)',
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $developer = gmxMember($org, 'developer');
                gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $developer->id, 'role' => 'viewer']);
                expect(gmxSnapshotOf($org, $developer, 'role_changed')?->access['membership']['role'] ?? null)->toBe('developer');
            },
            'I11' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $developer = gmxMember($org, 'developer');
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $developer->id, 'role' => 'svc_view']), 'invalid_role');
            },
            'I12' => 'an organization role is the organization\'s to give, not a reshare (the depth of delegated access is share × I12)',
        ],
        // ── a member is removed ──────────────────────────────────────────────────────────────────────────────────────────
        'remove_member' => [
            'I1' => 'a removal hands nothing over',
            'I2' => 'leaving — removing yourself — is the one change of your own the program allows',
            'I3' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $billing = gmxMember($org, 'billing_admin');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'remove_member', 'user_id' => $billing->id]), 'member_above_own');
            },
            'I4' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'remove_member', 'user_id' => $owner->id]), 'owner_cannot_be_removed');
            },
            'I5' => 'a removal ends access now; there is no later end to compare',
            'I6' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $invitee = User::factory()->create();
                gmxAccept($invitee, gmxInvite($admin, $org, $invitee->email, 'developer')); // a membership the admin granted
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);
                expect(gmxFlagged($org, 'membership'))->toBe(1)->and(gmxRole($org, $invitee))->toBe('developer'); // recorded, not revoked (switch off)
            },
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $developer = gmxMember($org, 'developer');
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $developer->id], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                [$stranger] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $stranger->id]), 'not_found');
            },
            'I9' => 'a removal is not an acceptance',
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $developer = gmxMember($org, 'developer');
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $developer->id]);
                expect(gmxSnapshotOf($org, $developer, 'member_removed')?->access['membership']['role'] ?? null)->toBe('developer');
            },
            'I11' => 'a removal names no role',
            'I12' => 'a removal passes nothing on',
        ],
        // ── a project role is given or changed ───────────────────────────────────────────────────────────────────────────
        'project_add' => [
            'I1' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'viewer');
                gmxAugment('developer', 'billing.wallet.spend'); // the developer line now carries what an org_admin is withheld
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'add_project_member', 'project_id' => gmxProject($org)->id, 'user_id' => $member->id, 'role' => 'developer']), 'role_above_own');
            },
            'I2' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'add_project_member', 'project_id' => gmxProject($org)->id, 'user_id' => $admin->id, 'role' => 'developer']), 'self_membership_locked');
            },
            'I3' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'viewer');
                $project = gmxProject($org);
                gmxOrgOp($owner, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'developer']);
                gmxAugment('developer', 'billing.wallet.spend');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'viewer']), 'member_above_own');
            },
            'I4' => 'the owner binding is organization-scope; a project role comes from an allow-list without it (I11)',
            'I5' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $end = now()->addDays(10)->startOfSecond();
                $admin = gmxMember($org, 'org_admin', $end);
                $member = gmxMember($org, 'viewer');
                $project = gmxProject($org);
                gmxOrgOp($admin, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'developer']);
                expect(ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $member->id)->first()?->expires_at?->toIso8601String())->toBe($end->toIso8601String());
            },
            'I6' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'viewer');
                $project = gmxProject($org);
                gmxOrgOp($admin, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'developer']);
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);
                expect(gmxFlagged($org, 'project_role'))->toBe(1)
                    ->and(ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $member->id)->value('role_key'))->toBe('developer');
            },
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'viewer');
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'add_project_member', 'project_id' => gmxProject($org)->id, 'user_id' => $member->id, 'role' => 'developer'], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                [$stranger] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'add_project_member', 'project_id' => gmxProject($org)->id, 'user_id' => $stranger->id, 'role' => 'viewer']), 'not_a_member');
            },
            'I9' => 'a project role is not an acceptance',
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'viewer');
                $project = gmxProject($org);
                gmxOrgOp($owner, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'developer']);
                gmxOrgOp($owner, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'viewer']);
                expect(collect(gmxSnapshotOf($org, $member, 'project_role_changed')?->access['projects'] ?? [])->pluck('role')->all())->toBe(['developer']);
            },
            'I11' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'viewer');
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'add_project_member', 'project_id' => gmxProject($org)->id, 'user_id' => $member->id, 'role' => 'org_admin']), 'invalid_role');
            },
            'I12' => 'a project role is the organization\'s to give, not a reshare',
        ],
        // ── a project role is taken back ─────────────────────────────────────────────────────────────────────────────────
        'project_remove' => [
            'I1' => 'a removal hands nothing over',
            'I2' => 'giving up your own project role is leaving it',
            'I3' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'viewer');
                $project = gmxProject($org);
                gmxOrgOp($owner, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'developer']);
                gmxAugment('developer', 'billing.wallet.spend');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'remove_project_member', 'project_id' => $project->id, 'user_id' => $member->id]), 'member_above_own');
            },
            'I4' => 'the owner binding is not a project role',
            'I5' => 'a removal ends access now',
            'I6' => 'a removal gives nothing a later loss could leave unbacked',
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'viewer');
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'remove_project_member', 'project_id' => gmxProject($org)->id, 'user_id' => $member->id], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'viewer');
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'remove_project_member', 'project_id' => gmxProject($org)->id, 'user_id' => $member->id]), 'not_found');
            },
            'I9' => 'a removal is not an acceptance',
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'viewer');
                $project = gmxProject($org);
                gmxOrgOp($owner, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'developer']);
                gmxOrgOp($owner, $org, ['op' => 'remove_project_member', 'project_id' => $project->id, 'user_id' => $member->id]);
                expect(collect(gmxSnapshotOf($org, $member, 'project_role_removed')?->access['projects'] ?? [])->pluck('role')->all())->toBe(['developer']);
            },
            'I11' => 'a removal names no role',
            'I12' => 'a removal passes nothing on',
        ],
        // ── one service shared with a person ─────────────────────────────────────────────────────────────────────────────
        'share' => [
            'I1' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                gmxWithhold('org_admin', 'service.console');
                gmxRefuses(fn () => gmxShare($admin, $org, gmxService($org), ['op' => 'share', 'email' => 'shell@gmx.test', 'capabilities' => ['console']]), 'capability_above_own');
            },
            'I2' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                gmxRefuses(fn () => gmxShare($admin, $org, gmxService($org), ['op' => 'share', 'email' => $admin->email, 'capabilities' => ['view']]), 'cannot_share_with_self');
            },
            'I3' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'guest');
                $service = gmxService($org);
                gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['console']]);
                gmxWithhold('org_admin', 'service.console');
                // the admin could not give the console, so they do not take it away by sharing again with less
                gmxRefuses(fn () => gmxShare($admin, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['view']]), 'share_above_own');
                expect(ServiceAccessGrant::query()->where('service_id', $service->id)->value('capabilities'))->toContain('console');
            },
            'I4' => 'a share carries service roles (svc_*) only; the owner binding is never one of them',
            'I5' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $end = now()->addDays(10)->startOfSecond();
                $admin = gmxMember($org, 'org_admin', $end);
                $member = gmxMember($org, 'guest');
                $service = gmxService($org);
                gmxShare($admin, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['manage']]);
                expect(ServiceAccessGrant::query()->where('service_id', $service->id)->first()?->expires_at?->toIso8601String())->toBe($end->toIso8601String())
                    ->and(DB::table('policy_bindings')->where('principal_id', $member->id)->where('scope_type', 'resource')->pluck('expires_at')->map(fn ($v) => $v === null ? null : now()->parse($v)->toIso8601String())->unique()->all())->toBe([$end->toIso8601String()]);
            },
            'I6' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'guest');
                $service = gmxService($org);
                gmxShare($admin, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['manage']]); // active: the person is a member
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);
                expect(gmxFlagged($org, 'service_share'))->toBe(1)->and(ServiceAccessGrant::query()->where('service_id', $service->id)->value('state'))->toBe(ServiceAccessGrant::ACTIVE);
            },
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxShare($owner, $org, gmxService($org), ['op' => 'share', 'email' => 'x@gmx.test', 'capabilities' => ['view']], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $service = gmxService($org);
                $stranger = User::factory()->create();
                gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $stranger->email, 'capabilities' => ['view']]);
                // nobody becomes a member by being shared with: the way in is the invitation the share mailed
                expect(gmxRole($org, $stranger))->toBeNull()
                    ->and(OrganizationInvitation::query()->where('organization_id', $org->id)->where('email', mb_strtolower($stranger->email))->where('role_key', 'guest')->exists())->toBeTrue();
            },
            'I9' => 'a share adds service bindings next to the person\'s role and never replaces it',
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'guest');
                $service = gmxService($org);
                gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['console']]);
                gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['view']]);
                expect(collect(gmxSnapshotOf($org, $member, 'service_access_changed')?->access['bindings'] ?? [])->pluck('role')->all())->toContain('svc_console');
            },
            'I11' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxShare($owner, $org, gmxService($org), ['op' => 'share', 'email' => 'x@gmx.test', 'capabilities' => ['root']]), 'capabilities_invalid');
            },
            'I12' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $service = gmxService($org);
                $first = gmxMember($org, 'guest');
                $second = gmxMember($org, 'guest');
                gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $first->email, 'capabilities' => ['manage']]);
                // somebody a service was shared with cannot share it at all through the bus (organization.members.manage)…
                gmxRefuses(fn () => gmxShare($first, $org, $service, ['op' => 'share', 'email' => 'third@gmx.test', 'capabilities' => ['view']]), 'access_not_approved');
                // …and the policy itself stops a chain at depth two whatever a role would allow: first → second (depth 2) → third (3)
                ServiceAccessGrant::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'email' => mb_strtolower($second->email), 'user_id' => $second->id, 'capabilities' => ['view', 'manage'], 'state' => ServiceAccessGrant::ACTIVE, 'granted_by' => $first->id, 'accepted_at' => now()]);
                DB::table('policy_bindings')->insert(['id' => 'pb_'.Str::lower((string) Str::ulid()), 'principal_type' => 'user', 'principal_id' => $second->id, 'role_key' => 'svc_manage', 'scope_type' => 'resource', 'scope_id' => $service->id, 'organization_id' => $org->id, 'granted_by' => $first->id, 'created_at' => now(), 'updated_at' => now()]);
                app(Authorizer::class)->flush();
                gmxRefuses(fn () => app(GrantPolicy::class)->assertMayShareService($org, $service, gmxContext($second, $org), 'third@gmx.test', ['manage'], null), 'reshare_too_deep');
            },
        ],
        // ── a share is revoked ───────────────────────────────────────────────────────────────────────────────────────────
        'share_revoke' => [
            'I1' => 'a revocation hands nothing over',
            'I2' => 'a person a service was shared with does not hold organization.members.manage (share × I12)',
            'I3' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'guest');
                $service = gmxService($org);
                $grant = gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['console']])['grant'];
                gmxWithhold('org_admin', 'service.console');
                gmxRefuses(fn () => gmxShare($admin, $org, $service, ['op' => 'revoke', 'grant_id' => $grant['id']]), 'share_above_own');
            },
            'I4' => 'a share never carries the owner binding',
            'I5' => 'a revocation ends access now',
            'I6' => 'a revocation gives nothing a later loss could leave unbacked',
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $service = gmxService($org);
                $grant = gmxShare($owner, $org, $service, ['op' => 'share', 'email' => 'x@gmx.test', 'capabilities' => ['view']])['grant'];
                gmxRefuses(fn () => gmxShare($owner, $org, $service, ['op' => 'revoke', 'grant_id' => $grant['id']], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxShare($owner, $org, gmxService($org), ['op' => 'revoke', 'grant_id' => 'sag_nothing']), 'not_found');
            },
            'I9' => 'a revocation is not an acceptance',
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'guest');
                $service = gmxService($org);
                gmxMember($org, 'viewer'); // somebody else stays, so releasing the guest is not what is measured
                $grant = gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['manage']])['grant'];
                gmxShare($owner, $org, $service, ['op' => 'revoke', 'grant_id' => $grant['id']]);
                expect(collect(gmxSnapshotOf($org, $member, 'service_access_revoked')?->access['bindings'] ?? [])->pluck('role')->all())->toContain('svc_manage');
            },
            'I11' => 'a revocation names no role',
            'I12' => 'a revocation passes nothing on',
        ],
        // ── the organization's ownership moves (S1-02: offer + acceptance) ──────────────────────────────────────────────────
        'transfer' => [
            'I1' => 'the owner holds every customer permission, and only the owner offers (I4)',
            'I2' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxOwnership($owner, $org, ['op' => 'offer', 'user_id' => $owner->id]), 'owner_transfer_self');
            },
            'I3' => 'the heir\'s current role is below the owner\'s by construction',
            'I4' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $heir = gmxMember($org, 'developer');
                gmxRefuses(fn () => gmxOwnership($admin, $org, ['op' => 'offer', 'user_id' => $heir->id]), 'owner_transfer_only');
                gmxOwnership($owner, $org, ['op' => 'offer', 'user_id' => $heir->id]);
                expect($org->fresh()->owner_user_id)->toBe($owner->id); // an offer moves nothing: the heir accepts it
                gmxOwnership($heir, $org, ['op' => 'accept']);
                expect($org->fresh()->owner_user_id)->toBe($heir->id)->and(gmxRole($org, $heir))->toBe('owner')->and(gmxRole($org, $owner))->toBe('org_admin');
            },
            'I5' => 'the owner\'s access never ends; the heir\'s end goes with the owner binding',
            'I6' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $heir = gmxMember($org, 'org_admin');
                gmxOwnership($owner, $org, ['op' => 'offer', 'user_id' => $heir->id]);
                $other = gmxMember($org, 'org_admin');
                app(OrganizationService::class)->transferOwnership($org->fresh(), $other, CommandContext::system('gmx: an owner recovery moved it meanwhile'));
                gmxRefuses(fn () => gmxOwnership($heir, $org, ['op' => 'accept']), 'ownership_offer_invalid'); // the offerer is no longer the owner
                expect($org->fresh()->owner_user_id)->toBe($other->id);
            },
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $heir = gmxMember($org, 'org_admin');
                gmxRefuses(fn () => gmxOwnership($owner, $org, ['op' => 'offer', 'user_id' => $heir->id], stepUp: false), 'step_up_required');
                gmxOwnership($owner, $org, ['op' => 'offer', 'user_id' => $heir->id]);
                gmxRefuses(fn () => gmxOwnership($heir, $org, ['op' => 'accept'], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                [$stranger] = $t->customerWithOrganization();
                gmxRefuses(fn () => gmxOwnership($owner, $org, ['op' => 'offer', 'user_id' => $stranger->id]), 'not_found');
            },
            'I9' => 'accepting ownership only ever widens',
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $heir = gmxMember($org, 'org_admin');
                gmxOwnership($owner, $org, ['op' => 'offer', 'user_id' => $heir->id]);
                gmxOwnership($heir, $org, ['op' => 'accept']);
                expect(gmxSnapshotOf($org, $owner, 'ownership_transferred')?->access['membership']['role'] ?? null)->toBe('owner');
            },
            'I11' => 'ownership names the owner role only',
            'I12' => 'ownership is not delegated access',
        ],
        // ── an access snapshot is restored (S1-02) ─────────────────────────────────────────────────────────────────────────
        'restore' => [
            'I1' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'guest');
                gmxMember($org, 'viewer');
                $service = gmxService($org);
                gmxShare($owner, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['console']]);
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $member->id]);
                gmxWithhold('org_admin', 'service.console');
                $snapshot = gmxSnapshotOf($org, $member, 'member_removed');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'restore_access', 'snapshot_id' => $snapshot?->id]), 'role_above_own');
            },
            'I2' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $admin->id, 'role' => 'developer']);
                $snapshot = gmxSnapshotOf($org, $admin, 'role_changed');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'restore_access', 'snapshot_id' => $snapshot?->id]), 'access_not_approved'); // a developer manages no members
                gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $admin->id, 'role' => 'org_admin']);
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'restore_access', 'snapshot_id' => $snapshot?->id]), 'self_membership_locked');
            },
            'I3' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $member = gmxMember($org, 'developer');
                gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $member->id, 'role' => 'billing_admin']);
                $snapshot = gmxSnapshotOf($org, $member, 'role_changed');
                gmxRefuses(fn () => gmxOrgOp($admin, $org, ['op' => 'restore_access', 'snapshot_id' => $snapshot?->id]), 'member_above_own');
            },
            'I4' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $heir = gmxMember($org, 'org_admin');
                gmxOwnership($owner, $org, ['op' => 'offer', 'user_id' => $heir->id]);
                gmxOwnership($heir, $org, ['op' => 'accept']);
                $snapshot = gmxSnapshotOf($org, $owner, 'ownership_transferred');
                gmxRefuses(fn () => gmxOrgOp($heir, $org, ['op' => 'restore_access', 'snapshot_id' => $snapshot?->id]), 'owner_role_locked');
            },
            'I5' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $end = now()->addDays(10)->startOfSecond();
                $admin = gmxMember($org, 'org_admin', $end);
                $member = gmxMember($org, 'developer');
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $member->id]);
                gmxOrgOp($admin, $org, ['op' => 'restore_access', 'snapshot_id' => gmxSnapshotOf($org, $member, 'member_removed')?->id]);
                expect(gmxRole($org, $member))->toBe('developer')->and(gmxEnd($org, $member))->toBe($end->toIso8601String());
            },
            'I6' => 'a restore is decided by who restores, now (I1, I3); the removed grantor\'s loss is the grant × I6 cells',
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'developer');
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $member->id]);
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => gmxSnapshotOf($org, $member, 'member_removed')?->id], stepUp: false), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                [$otherOwner, $other] = $t->customerWithOrganization();
                $member = gmxMember($other, 'developer');
                gmxOrgOp($otherOwner, $other, ['op' => 'remove_member', 'user_id' => $member->id]);
                // a snapshot of another organization proves nothing here: the only way in stays the invitation
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => gmxSnapshotOf($other, $member, 'member_removed')?->id]), 'not_found');
            },
            'I9' => 'a restore is not an acceptance; it gives back what the snapshot recorded, no more',
            'I10' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'developer');
                gmxOrgOp($owner, $org, ['op' => 'change_role', 'user_id' => $member->id, 'role' => 'viewer']);
                gmxOrgOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => gmxSnapshotOf($org, $member, 'role_changed')?->id]);
                expect(gmxSnapshotOf($org, $member, 'before_restore')?->access['membership']['role'] ?? null)->toBe('viewer')->and(gmxRole($org, $member))->toBe('developer');
            },
            'I11' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $member = gmxMember($org, 'developer');
                $project = gmxProject($org);
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $member->id]);
                $snapshot = gmxSnapshotOf($org, $member, 'member_removed');
                $access = $snapshot->access;
                $access['projects'][] = ['project_id' => $project->id, 'role' => 'org_admin', 'expires_at' => null]; // a legacy project role (TD-3)
                $snapshot->forceFill(['access' => $access])->save();
                gmxRefuses(fn () => gmxOrgOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => $snapshot->id]), 'invalid_role');
            },
            'I12' => 'a restore gives the person back their own access; it passes nothing on',
        ],
        // ── an API token (a narrowed view of its person, program D3) ──────────────────────────────────────────────────────
        'token' => [
            'I1' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                [$otherOwner, $other] = $t->customerWithOrganization();
                app(OrganizationService::class)->attachMember($other, $owner, 'viewer', CommandContext::system('gmx'), true);
                $id = app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'gmx-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read']]), gmxContext($owner, $org))['id'];
                $viewed = $owner->fresh()->withAccessToken(PersonalAccessToken::query()->findOrFail($id));
                expect(app(Authorizer::class)->customerPermissionsAt($viewed, CommandScope::organization($other->id)))->toBe([]);
            },
            'I2' => 'a token is its person\'s own narrowed view, not a grant to somebody else',
            'I3' => 'a token has no target person',
            'I4' => 'the organizations route family is closed to tokens (TokenRouteScope; token × I12)',
            'I5' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $end = now()->addDays(10)->startOfSecond();
                $admin = gmxMember($org, 'org_admin', $end);
                $result = app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'gmx-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read'], 'expires_in_days' => 365]), gmxContext($admin, $org));
                expect(PersonalAccessToken::query()->findOrFail($result['id'])->expires_at?->toIso8601String())->toBe($end->toIso8601String())
                    ->and($result['expires_at'])->toBe($end->toIso8601String());
            },
            'I6' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $admin = gmxMember($org, 'org_admin');
                $id = app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'gmx-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read']]), gmxContext($admin, $org))['id'];
                gmxOrgOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);
                app(Authorizer::class)->flush(); // the next request
                $viewed = $admin->fresh()->withAccessToken(PersonalAccessToken::query()->findOrFail($id));
                expect(app(Authorizer::class)->customerPermissionsAt($viewed, CommandScope::organization($org->id)))->toBe([]);
            },
            'I7' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                gmxRefuses(fn () => app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'gmx-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read']]), gmxContext($owner, $org, false)), 'step_up_required');
            },
            'I8' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                [$stranger] = $t->customerWithOrganization();
                gmxRefuses(fn () => app(GrantPolicy::class)->assertMayIssueToken($org, $stranger, now()->addDays(30)), 'not_found');
            },
            'I9' => 'a token is not an acceptance',
            'I10' => 'issuing a token changes no membership',
            'I11' => 'a token carries documented scopes (ApiTokenCommand::SCOPES), never a role',
            'I12' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $token = (string) app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'gmx-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read', 'services:power']]), gmxContext($owner, $org))['token'];
                $t->flushHeaders()->withHeader('Authorization', 'Bearer '.$token)->postJson("/v1/organizations/{$org->id}/invitations", ['email' => 'x@gmx.test', 'role' => 'viewer'])->assertForbidden();
                expect(OrganizationInvitation::query()->where('organization_id', $org->id)->exists())->toBeFalse();
            },
        ],
        // ── a partner's attribution of a customer (grants nothing: reseller delegation is S3-02) ───────────────────────────
        'partner' => [
            'I1' => function (TestCase $t): void {
                [$partnerOwner, $partner] = $t->customerWithOrganization();
                [, $client] = $t->customerWithOrganization([], ['partner_organization_id' => $partner->id]);
                gmxRefuses(fn () => gmxInvite($partnerOwner, $client, 'x@gmx.test', 'viewer'), 'access_not_approved');
            },
            'I2' => 'attribution creates no binding, so there is nobody granting to themselves',
            'I3' => 'attribution creates no binding to cover',
            'I4' => 'attribution never touches the owner binding',
            'I5' => 'attribution has no access and so no end',
            'I6' => 'attribution grants nothing a partner\'s loss could leave unbacked',
            'I7' => 'attribution is not a grant',
            'I8' => function (TestCase $t): void {
                [$partnerOwner, $partner] = $t->customerWithOrganization();
                [$clientOwner, $client] = $t->customerWithOrganization([], ['partner_organization_id' => $partner->id]);
                expect(gmxRole($client, $partnerOwner))->toBeNull();
                gmxRefuses(fn () => gmxOrgOp($clientOwner, $client, ['op' => 'change_role', 'user_id' => $partnerOwner->id, 'role' => 'viewer']), 'not_found');
            },
            'I9' => 'attribution is not an acceptance',
            'I10' => 'attribution changes no membership',
            'I11' => 'attribution names no role',
            'I12' => 'attribution passes nothing on',
        ],
        // ── staff: a global role is the platform's reach, never a customer grant right ─────────────────────────────────────
        'staff' => [
            'I1' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $staff = $t->steppedUpStaff('platform_owner');
                gmxRefuses(fn () => gmxInvite($staff, $org, 'x@gmx.test', 'viewer'), 'role_above_own');
            },
            'I2' => 'a staff account is made from the command line by the system, never by itself',
            'I3' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $developer = gmxMember($org, 'developer');
                $staff = $t->steppedUpStaff('platform_owner');
                gmxRefuses(fn () => gmxOrgOp($staff, $org, ['op' => 'change_role', 'user_id' => $developer->id, 'role' => 'viewer']), 'member_above_own');
            },
            'I4' => function (TestCase $t): void {
                [$owner, $org] = $t->customerWithOrganization();
                $heir = gmxMember($org, 'org_admin');
                $staff = $t->steppedUpStaff('platform_owner');
                gmxRefuses(fn () => gmxOwnership($staff, $org, ['op' => 'offer', 'user_id' => $heir->id]), 'owner_transfer_only');
                // D21: a lost owner is recovered by the owner recovery, never by resetting their MFA on the side
                $iam = $t->steppedUpStaff('iam_admin');
                gmxRefuses(fn () => app(CommandBus::class)->dispatch(new MfaResetCommand('gmx-mfa-'.Str::ulid(), ['user_id' => $owner->id, 'reason' => 'ztratil telefon']), new CommandContext('user', $iam->id, null, null, '127.0.0.1', 'pest', 'gmx-staff', staffMode: true)), 'owner_recovery_required');
            },
            'I5' => 'staff roles are global and end by the staff lifecycle (S4-02), not by a grantor',
            'I6' => 'staff accounts are made by the system from the command line; there is no grantor to lose',
            'I7' => function (TestCase $t): void {
                [, $org] = $t->customerWithOrganization();
                $iam = $t->steppedUpStaff('iam_admin');
                // taking over a customer's organization by recovery is CRITICAL: a second person (D21)
                gmxRefuses(fn () => app(CommandBus::class)->dispatch(new OwnerRecoveryCommand('gmx-rec-'.Str::ulid(), ['op' => 'open', 'organization_id' => $org->id, 'mode' => 'mfa_reset', 'reason' => 'vlastník ztratil přístup', 'ticket_ref' => 'T-1']), new CommandContext('user', $iam->id, null, null, '127.0.0.1', 'pest', 'gmx-staff', staffMode: true)), 'approval_required');
            },
            'I8' => 'staff are never made members by a staff role (staff × I1, I3)',
            'I9' => 'staff accounts accept nothing',
            'I10' => 'staff roles are no customer membership; customer changes by staff are refused (I1, I3)',
            'I11' => function (TestCase $t): void {
                // a customer role at global scope reached every organization: a staff account takes a staff role only
                gmxRefuses(fn () => app(StaffAccountCommandHandler::class)->handle(new StaffAccountCommand('gmx-staff-'.Str::ulid(), ['email' => 'root@gmx.test', 'role' => 'owner', 'password' => 'correct horse battery']), CommandContext::system('cli:staff:create')), 'invalid_role');
            },
            'I12' => 'staff reach is not delegated access',
        ],
    ];
}

/** @return list<array{0:string, 1:string}> every cell that carries a proof */
function gmxEnforcedCells(): array
{
    $cells = [];
    foreach (gmxMatrix() as $entry => $invariants) {
        foreach ($invariants as $invariant => $cell) {
            if ($cell instanceof Closure) {
                $cells["{$entry} × {$invariant}"] = [$entry, $invariant];
            }
        }
    }

    return $cells;
}

beforeEach(function () {
    Http::fake(); // nothing leaves for a panel from here
});

it('enforces the invariant at the entry point', function (string $entry, string $invariant) {
    $cell = gmxMatrix()[$entry][$invariant];
    $cell->call($this, $this); // bound to the test case: its protected fixtures (customerWithOrganization, steppedUpStaff) are reachable
})->with(gmxEnforcedCells());

it('decides every invariant for every grant entry point — a proof or a stated reason, never a gap', function () {
    expect(array_keys(GrantPolicy::INVARIANTS))->toBe(['I1', 'I2', 'I3', 'I4', 'I5', 'I6', 'I7', 'I8', 'I9', 'I10', 'I11', 'I12']);
    $entries = ['invite', 'accept', 'change_role', 'remove_member', 'project_add', 'project_remove', 'share', 'share_revoke', 'transfer', 'restore', 'token', 'partner', 'staff'];
    expect(array_keys(gmxMatrix()))->toBe($entries);
    foreach (gmxMatrix() as $entry => $invariants) {
        expect(array_keys($invariants))->toBe(array_keys(GrantPolicy::INVARIANTS), $entry);
        foreach ($invariants as $invariant => $cell) {
            expect($cell instanceof Closure || (is_string($cell) && mb_strlen($cell) >= 15))->toBeTrue("{$entry} × {$invariant}");
        }
    }
    // every invariant is proven somewhere
    foreach (array_keys(GrantPolicy::INVARIANTS) as $invariant) {
        expect(collect(gmxMatrix())->filter(fn (array $cells) => $cells[$invariant] instanceof Closure)->isNotEmpty())->toBeTrue($invariant);
    }
});
