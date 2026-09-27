<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Compliance\Commands\DataRequestCommand;
use Onhost\Domain\Identity\Commands\ApiTokenCommand;
use Onhost\Domain\Identity\Models\StepUpGrant;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Organizations\AccessExpiry;
use Onhost\Domain\Organizations\AccessSnapshots;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Commands\OwnershipCommand;
use Onhost\Domain\Organizations\GrantCascade;
use Onhost\Domain\Organizations\Models\AccessSnapshot;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\OwnerRecovery;
use Onhost\Domain\Organizations\Models\OwnershipTransfer;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Organizations\OwnerRecoveries;
use Onhost\Domain\Services\Commands\ServiceAccessCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0042 (permission program S1-02, D21; audit TD-6, TD-7, TD-9): what is taken away can be given back exactly, a lost
 * grantor's grants are handled by one rule, ownership moves only when the heir says yes, and a customer owner who lost access
 * is recovered in the open — never by a quiet MFA reset.
 */

function arxMember(Organization $org, string $role, ?CarbonInterface $until = null): User
{
    $user = User::factory()->create();
    app(OrganizationService::class)->attachMember($org, $user, $role, CommandContext::system('arx fixture'), true, $until);

    return $user;
}

function arxContext(User $actor, ?Organization $org, bool $stepUp = true): CommandContext
{
    if ($stepUp) {
        app(StepUpService::class)->grant($actor, 'totp', null, '127.0.0.1');
    } else {
        StepUpGrant::query()->where('user_id', $actor->id)->delete();
    }

    return new CommandContext('user', $actor->id, $org?->id, null, '127.0.0.1', 'pest', 'arx-session');
}

function arxOp(User $actor, Organization $org, array $payload): mixed
{
    return app(CommandBus::class)->dispatch(new OrganizationCommand($org->id, 'arx-'.Str::ulid(), $payload), arxContext($actor, $org));
}

function arxShare(User $actor, Organization $org, Service $service, array $payload): mixed
{
    return app(CommandBus::class)->dispatch(new ServiceAccessCommand($org->id, 'arx-share-'.Str::ulid(), ['service_id' => $service->id] + $payload), arxContext($actor, $org));
}

function arxService(Organization $org): Service
{
    return Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'hostname' => 'arx-'.Str::lower(Str::random(8)).'.cz',
        'state' => ServiceStateMachine::ACTIVE, 'desired_spec' => [], 'entitlements' => [], 'tags' => [], 'health' => [],
    ]);
}

function arxRefuses(Closure $attempt, string $error): void
{
    try {
        $attempt();
    } catch (DomainError $e) {
        expect($e->error)->toBe($error, $e->getMessage());

        return;
    }
    test()->fail("Expected the refusal {$error}; the attempt went through.");
}

function arxSnapshot(Organization $org, User $user, string $reason): AccessSnapshot
{
    return AccessSnapshot::query()->where('organization_id', $org->id)->where('user_id', $user->id)->where('reason', $reason)->latest('created_at')->firstOrFail();
}

function arxMails(string $template): array
{
    return MailOutbox::query()->where('template_key', $template)->pluck('to')->map(fn ($to) => mb_strtolower((string) $to))->sort()->values()->all();
}

/** The staff route of the owner recovery, asked by `$staff`; with `$approvalId` the request is repeated after a second person approved it. */
function arxRecovery(User $staff, Organization $org, array $body, ?string $approvalId = null)
{
    return test()->flushHeaders()->actingAs($staff, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())
        ->postJson("/v1/staff/customers/{$org->id}/owner-recovery", $body + ($approvalId !== null ? ['approval_ids' => [$approvalId]] : []));
}

/** Opens a recovery the way support must: the first request opens an approval, a second person approves, the repeat runs. */
function arxOpenRecovery(User $staff, Organization $org, array $body): OwnerRecovery
{
    $approval = (string) arxRecovery($staff, $org, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    secondPersonApproves($approval);
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    arxRecovery($staff, $org, $body, $approval)->assertCreated();
    app(OutboxPublisher::class)->relayPending();

    return OwnerRecovery::query()->where('organization_id', $org->id)->latest('created_at')->firstOrFail();
}

beforeEach(function () {
    Http::fake();
});

// ── I10: remove + restore returns identical access ─────────────────────────────────────────────────────────────────────

it('gives a removed member back exactly the access they had: role, its end, project roles and shared services', function () {
    [$owner, $org] = $this->customerWithOrganization();
    arxMember($org, 'viewer'); // somebody else stays: releasing a last guest is not what is measured
    $member = arxMember($org, 'viewer', now()->addDays(30)->startOfSecond()); // a viewer: the shared console is more than the role gives
    $project = Project::query()->create(['organization_id' => $org->id, 'slug' => 'eshop', 'name' => 'E-shop']);
    arxOp($owner, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $member->id, 'role' => 'cloud_operator', 'access_until' => now()->addDays(20)->startOfSecond()->toIso8601String()]);
    $service = arxService($org);
    arxShare($owner, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['console', 'backups'], 'access_until' => now()->addDays(25)->startOfSecond()->toIso8601String()]);
    $before = app(AccessSnapshots::class)->capture($org, $member->id);
    expect($before['membership']['role'])->toBe('viewer')
        ->and(collect($before['bindings'])->pluck('role')->all())->toContain('svc_console', 'cloud_operator', 'viewer')
        ->and($before['projects'])->toHaveCount(1)->and($before['shares'])->toHaveCount(1);

    arxOp($owner, $org, ['op' => 'remove_member', 'user_id' => $member->id]);
    $gone = app(AccessSnapshots::class)->capture($org, $member->id);
    expect($gone['membership'])->toBeNull()->and($gone['bindings'])->toBe([])->and($gone['projects'])->toBe([])->and($gone['shares'])->toBe([]);

    // the team page lists the snapshot with the exact request that gives it back
    $this->actingAs($owner, 'sanctum');
    $row = collect($this->getJson("/v1/organizations/{$org->id}/access-snapshots")->assertOk()->json('data'))->firstWhere('user_id', $member->id);
    expect($row['reason'])->toBe('member_removed')
        ->and($row['restore'])->toBe(['method' => 'POST', 'path' => "/v1/organizations/{$org->id}/access-snapshots/restore", 'body' => ['snapshot_id' => $row['id']]]);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson($row['restore']['path'], $row['restore']['body'])->assertOk()->assertJsonPath('restored', true);

    expect(app(AccessSnapshots::class)->capture($org, $member->id))->toBe($before);
    expect(OutboxMessage::query()->where('name', 'organization.access.restored')->where('organization_id', $org->id)->exists())->toBeTrue()
        ->and(AccessSnapshot::query()->findOrFail($row['id'])->restored_at)->not->toBeNull();
    // a snapshot is restored once; the restore itself left a snapshot of what it replaced
    arxRefuses(fn () => arxOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => $row['id']]), 'snapshot_restored');
});

it('gives a demoted member back their role and everything that went with it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $member = arxMember($org, 'billing_admin');
    $service = arxService($org);
    arxShare($owner, $org, $service, ['op' => 'share', 'email' => $member->email, 'capabilities' => ['console']]);
    $before = app(AccessSnapshots::class)->capture($org, $member->id);

    arxOp($owner, $org, ['op' => 'change_role', 'user_id' => $member->id, 'role' => 'viewer']);
    expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $member->id)->value('role_key'))->toBe('viewer');
    arxOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => arxSnapshot($org, $member, 'role_changed')->id]);

    expect(app(AccessSnapshots::class)->capture($org, $member->id))->toBe($before);
});

it('keeps a snapshot for 90 days: afterwards it is refused and the sweep removes it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $member = arxMember($org, 'developer');
    arxOp($owner, $org, ['op' => 'remove_member', 'user_id' => $member->id]);
    $snapshot = arxSnapshot($org, $member, 'member_removed');
    expect($snapshot->expires_at?->toDateString())->toBe(now()->addDays(90)->toDateString());

    $this->travel(91)->days();
    arxRefuses(fn () => arxOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => $snapshot->id]), 'snapshot_expired');
    expect(app(AccessExpiry::class)->sweep()['snapshots_pruned'])->toBe(1)->and(AccessSnapshot::query()->whereKey($snapshot->id)->exists())->toBeFalse();
});

// ── I8 / TD-7: the way in is AcceptInvitationCommand, through the bus ─────────────────────────────────────────────────────

it('accepts an invitation through the bus and asks at the click whether the inviter could still send it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = arxMember($org, 'org_admin');
    $invitee = User::factory()->create();
    $token = (string) arxOp($admin, $org, ['op' => 'invite', 'email' => $invitee->email, 'role' => 'org_admin'])['token'];
    // the inviter was demoted — written without the listeners, so what refuses is the check at the click
    OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $admin->id)->update(['role_key' => 'developer']);
    DB::table('policy_bindings')->where('principal_id', $admin->id)->where('organization_id', $org->id)->update(['role_key' => 'developer']);

    $this->flushHeaders()->actingAs($invitee, 'sanctum')->postJson('/v1/organizations/invitations/accept', ['token' => $token])->assertStatus(410)->assertJsonPath('error', 'invitation_invalid');
    expect(AuditEvent::query()->where('action', 'organization.invitation.accept')->where('actor_id', $invitee->id)->where('result', 'failed')->exists())->toBeTrue()
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $invitee->id)->exists())->toBeFalse();

    // a link the owner sent is accepted — the same bus, audited as the person who clicked, the binding names the sender
    $welcome = (string) arxOp($owner, $org, ['op' => 'invite', 'email' => $invitee->email, 'role' => 'viewer'])['token'];
    $this->flushHeaders()->actingAs($invitee, 'sanctum')->postJson('/v1/organizations/invitations/accept', ['token' => $welcome])
        ->assertOk()->assertJsonPath('data.role', 'viewer')->assertJsonPath('data.organization_id', $org->id)->assertJsonPath('data.shared_services', 0);
    expect(AuditEvent::query()->where('action', 'organization.invitation.accept')->where('actor_id', $invitee->id)->where('result', 'succeeded')->exists())->toBeTrue()
        ->and(DB::table('policy_bindings')->where('principal_id', $invitee->id)->where('scope_type', 'organization')->value('granted_by'))->toBe($owner->id);
});

// ── I6: the grants of a lost grantor ────────────────────────────────────────────────────────────────────────────────────

/** An admin who let three people in three ways: a membership by link, a project role, a shared service. @return array{User, User, User, User, Project, Service} */
function arxAdminWithGrants(Organization $org, User $owner): array
{
    $admin = arxMember($org, 'org_admin');
    $joined = User::factory()->create();
    $token = (string) arxOp($admin, $org, ['op' => 'invite', 'email' => $joined->email, 'role' => 'developer'])['token'];
    test()->flushHeaders()->actingAs($joined, 'sanctum')->postJson('/v1/organizations/invitations/accept', ['token' => $token])->assertOk();
    $projectMember = arxMember($org, 'viewer');
    $project = Project::query()->create(['organization_id' => $org->id, 'slug' => 'eshop', 'name' => 'E-shop']);
    arxOp($admin, $org, ['op' => 'add_project_member', 'project_id' => $project->id, 'user_id' => $projectMember->id, 'role' => 'developer']);
    $guest = arxMember($org, 'guest');
    $service = arxService($org);
    arxShare($admin, $org, $service, ['op' => 'share', 'email' => $guest->email, 'capabilities' => ['manage']]);

    return [$admin, $joined, $projectMember, $guest, $project, $service];
}

it('only records what a removed admin had given while the cascade switch is off, and lists it for the operator', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$admin, $joined, $projectMember, $guest, $project, $service] = arxAdminWithGrants($org, $owner);

    arxOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);

    expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $joined->id)->value('role_key'))->toBe('developer')
        ->and(ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $projectMember->id)->value('role_key'))->toBe('developer')
        ->and(ServiceAccessGrant::query()->where('service_id', $service->id)->where('user_id', $guest->id)->value('state'))->toBe(ServiceAccessGrant::ACTIVE);
    $flags = AuditEvent::query()->where('organization_id', $org->id)->where('action', 'organization.grant.cascade.flag')->get();
    expect($flags->map(fn ($e) => data_get($e->detail, 'kind'))->sort()->values()->all())->toBe(['membership', 'project_role', 'service_share'])
        ->and(OutboxMessage::query()->where('name', 'organization.grants.unbacked')->where('organization_id', $org->id)->value('payload')['revoked'] ?? null)->toBeFalse();

    $this->artisan('operator:grants:cascade', ['--dry-run' => true])
        ->expectsOutputToContain($joined->email)->expectsOutputToContain($projectMember->email)->expectsOutputToContain($guest->email)->assertSuccessful();
    $this->artisan('operator:grants:cascade', ['--apply' => true])->assertFailed();
});

it('revokes what a removed admin had given once the switch is on — each with a snapshot that gives it back', function () {
    config(['onhost.grants.cascade_enabled' => true]);
    [$owner, $org] = $this->customerWithOrganization();
    [$admin, $joined, $projectMember, $guest, $project, $service] = arxAdminWithGrants($org, $owner);
    $joinedBefore = app(AccessSnapshots::class)->capture($org, $joined->id);

    arxOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);

    expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $joined->id)->exists())->toBeFalse()
        ->and(ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $projectMember->id)->exists())->toBeFalse()
        ->and(ServiceAccessGrant::query()->where('service_id', $service->id)->where('user_id', $guest->id)->value('state'))->toBe(ServiceAccessGrant::REVOKED)
        ->and(AuditEvent::query()->where('organization_id', $org->id)->where('action', 'organization.grant.cascade.revoke')->count())->toBe(3);

    // the owner decides that one of them stays after all
    arxOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => arxSnapshot($org, $joined, 'member_removed')->id]);
    expect(app(AccessSnapshots::class)->capture($org, $joined->id))->toBe($joinedBefore);
});

// ── I4 / TD-9: ownership moves when the heir accepts ──────────────────────────────────────────────────────────────────────

it('moves ownership only when the heir accepts, tells both, and lets the owner cancel and the heir decline', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $heir = arxMember($org, 'org_admin');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $as = fn (User $who) => $this->flushHeaders()->actingAs($who, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid());

    $as($owner)->postJson("/v1/organizations/{$org->id}/ownership-transfer", ['user_id' => $heir->id])->assertCreated()->assertJsonPath('transfer.state', 'pending');
    app(OutboxPublisher::class)->relayPending();
    expect($org->fresh()->owner_user_id)->toBe($owner->id)->and(arxMails('ownership-offered'))->toBe([mb_strtolower($heir->email)]);

    $as($heir)->postJson("/v1/organizations/{$org->id}/ownership-transfer/accept")->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($heir, 'totp', null, '127.0.0.1');
    $as($heir)->postJson("/v1/organizations/{$org->id}/ownership-transfer/accept")->assertOk()->assertJsonPath('transfer.state', 'accepted');
    app(OutboxPublisher::class)->relayPending();
    expect($org->fresh()->owner_user_id)->toBe($heir->id)
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $owner->id)->value('role_key'))->toBe('org_admin')
        ->and(arxMails('ownership-transferred'))->toContain(mb_strtolower($owner->email));

    // the new owner offers it on and cancels; the next heir cannot accept a cancelled offer
    $next = arxMember($org, 'developer');
    $as($heir)->postJson("/v1/organizations/{$org->id}/ownership-transfer", ['user_id' => $next->id])->assertCreated();
    $as($heir)->deleteJson("/v1/organizations/{$org->id}/ownership-transfer")->assertOk()->assertJsonPath('transfer.state', 'cancelled');
    app(StepUpService::class)->grant($next, 'totp', null, '127.0.0.1');
    $as($next)->postJson("/v1/organizations/{$org->id}/ownership-transfer/accept")->assertStatus(409)->assertJsonPath('error', 'ownership_offer_invalid');

    // an heir may say no
    $as($heir)->postJson("/v1/organizations/{$org->id}/ownership-transfer", ['user_id' => $next->id])->assertCreated();
    $as($next)->postJson("/v1/organizations/{$org->id}/ownership-transfer/decline")->assertOk()->assertJsonPath('transfer.state', 'declined');

    // an offer nobody accepted lapses
    $as($heir)->postJson("/v1/organizations/{$org->id}/ownership-transfer", ['user_id' => $next->id])->assertCreated();
    $this->travel(8)->days();
    app(StepUpService::class)->grant($next, 'totp', null, '127.0.0.1');
    $as($next)->postJson("/v1/organizations/{$org->id}/ownership-transfer/accept")->assertStatus(409)->assertJsonPath('error', 'ownership_offer_invalid');
    expect($org->fresh()->owner_user_id)->toBe($heir->id);
});

// ── D21: a lost owner is recovered in the open ───────────────────────────────────────────────────────────────────────────

it('recovers a lost owner only after a second person, a week of notice to everybody, and while any admin can cancel', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = arxMember($org, 'org_admin');
    $developer = arxMember($org, 'developer');
    $owner->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();
    $iam = $this->steppedUpStaff('iam_admin');
    $body = ['mode' => 'mfa_reset', 'reason' => 'Vlastník ztratil telefon, ověřeno dokladem', 'ticket_ref' => 'T-4711'];

    $recovery = arxOpenRecovery($iam, $org, $body);
    expect($recovery->state)->toBe('pending')->and($recovery->not_before?->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and(arxMails('owner-recovery-opened'))->toBe(collect([$owner->email, $admin->email, $developer->email])->map(fn ($e) => mb_strtolower($e))->sort()->values()->all());

    // nothing happens before the week is over
    $this->flushHeaders()->actingAs($iam, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/staff/customers/{$org->id}/owner-recovery/complete")
        ->assertStatus(409)->assertJsonPath('error', 'owner_recovery_locked');
    // …and meanwhile no credential or export leaves the organization, and its ownership does not move
    arxRefuses(fn () => app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'arx-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read']]), arxContext($admin, $org)), 'owner_recovery_hold');
    arxRefuses(fn () => app(CommandBus::class)->dispatch(new DataRequestCommand($org->id, 'arx-export-'.Str::ulid(), ['op' => 'request', 'kind' => 'export']), arxContext($admin, $org)), 'owner_recovery_hold');
    arxRefuses(fn () => app(CommandBus::class)->dispatch(new OwnershipCommand($org->id, 'arx-own-'.Str::ulid(), ['op' => 'offer', 'user_id' => $admin->id]), arxContext($owner, $org)), 'owner_recovery_hold');

    // any organization admin stops it
    app(StepUpService::class)->grant($admin, 'totp', null, '127.0.0.1');
    $this->flushHeaders()->actingAs($admin, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/organizations/{$org->id}/owner-recovery/cancel")
        ->assertOk()->assertJsonPath('recovery.state', 'cancelled');
    $this->travel(8)->days();
    app(StepUpService::class)->grant($iam, 'totp', null, '127.0.0.1');
    $this->flushHeaders()->actingAs($iam, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/staff/customers/{$org->id}/owner-recovery/complete")
        ->assertStatus(409)->assertJsonPath('error', 'owner_recovery_not_pending');
    expect($owner->fresh()->hasTotp())->toBeTrue();

    // a recovery nobody cancels resets the owner's MFA after the week, and the owner is told
    arxOpenRecovery($iam, $org, $body);
    $this->travel(7)->days();
    $this->travel(2)->minutes();
    app(StepUpService::class)->grant($iam, 'totp', null, '127.0.0.1');
    $this->flushHeaders()->actingAs($iam, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/staff/customers/{$org->id}/owner-recovery/complete")
        ->assertOk()->assertJsonPath('recovery.state', 'completed');
    app(OutboxPublisher::class)->relayPending();
    expect($owner->fresh()->hasTotp())->toBeFalse()->and(arxMails('security-mfa'))->toContain(mb_strtolower($owner->email));
});

it('recovers an organization whose owner is gone by handing it to a member, after the same week', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = arxMember($org, 'org_admin');
    $iam = $this->steppedUpStaff('iam_admin');

    arxOpenRecovery($iam, $org, ['mode' => 'transfer', 'new_owner_user_id' => $admin->id, 'reason' => 'Vlastník zemřel, doloženo úmrtním listem', 'ticket_ref' => 'T-4712']);
    $this->travel(7)->days();
    $this->travel(2)->minutes();
    app(StepUpService::class)->grant($iam, 'totp', null, '127.0.0.1');
    $this->flushHeaders()->actingAs($iam, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertOk();

    expect($org->fresh()->owner_user_id)->toBe($admin->id)
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $owner->id)->value('role_key'))->toBe('org_admin')
        ->and(AccessSnapshot::query()->where('organization_id', $org->id)->where('user_id', $owner->id)->where('reason', 'ownership_transferred')->exists())->toBeTrue();
});

it('refuses iam.mfa.reset on a customer owner — the owner recovery is the only way — and resets anybody else', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $member = arxMember($org, 'developer');
    foreach ([$owner, $member] as $person) {
        $person->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();
    }
    $iam = $this->steppedUpStaff('iam_admin');
    $reset = fn (User $who) => $this->flushHeaders()->actingAs($iam, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())
        ->postJson("/v1/staff/users/{$who->id}/mfa-reset", ['reason' => 'Ztracený telefon, ověřeno']);

    $reset($owner)->assertStatus(409)->assertJsonPath('error', 'owner_recovery_required');
    expect($owner->fresh()->hasTotp())->toBeTrue();

    $reset($member)->assertOk()->assertJsonPath('reset', true);
    expect($member->fresh()->hasTotp())->toBeFalse();
});

// ── review round 1 (TASK-0042): the reach of a recovery, the second person for an admin's reset, parties and races ─────────

/** A request to a staff route other than opening a recovery, with a fresh step-up. */
function arxStaffPost(User $staff, string $path, array $body = [])
{
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');

    return test()->flushHeaders()->actingAs($staff, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->postJson($path, $body);
}

it('reaches every organization an MFA reset of an owner reaches: all are told, all are held, any of their admins cancels', function () {
    [$owner, $small] = $this->customerWithOrganization();
    $large = app(OrganizationService::class)->create($owner, ['name' => 'Velká a.s.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('arx fixture'));
    $largeAdmin = arxMember($large, 'org_admin');
    $largeDeveloper = arxMember($large, 'developer');
    [$neighbour, $managed] = $this->customerWithOrganization();
    app(OrganizationService::class)->attachMember($managed, $owner, 'org_admin', CommandContext::system('arx fixture'), true); // the owner also manages somebody else's members
    $managedViewer = arxMember($managed, 'viewer');
    $owner->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();
    $iam = $this->steppedUpStaff('iam_admin');

    arxOpenRecovery($iam, $small, ['mode' => 'mfa_reset', 'reason' => 'Vlastník ztratil telefon, ověřeno dokladem', 'ticket_ref' => 'T-4801']);

    // the reset would let a caller into all three: everybody in all three hears it
    expect(arxMails('owner-recovery-opened'))->toContain(mb_strtolower($largeAdmin->email), mb_strtolower($largeDeveloper->email), mb_strtolower($neighbour->email), mb_strtolower($managedViewer->email));
    // …nothing leaves any of them meanwhile
    arxRefuses(fn () => app(CommandBus::class)->dispatch(new ApiTokenCommand($large->id, 'arx-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read']]), arxContext($largeAdmin, $large)), 'owner_recovery_hold');
    arxRefuses(fn () => app(CommandBus::class)->dispatch(new DataRequestCommand($managed->id, 'arx-export-'.Str::ulid(), ['op' => 'request', 'kind' => 'export']), arxContext($neighbour, $managed)), 'owner_recovery_hold');

    // …and an admin of the large organization stops the whole recovery
    app(StepUpService::class)->grant($largeAdmin, 'totp', null, '127.0.0.1');
    $this->flushHeaders()->actingAs($largeAdmin, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/organizations/{$large->id}/owner-recovery/cancel")
        ->assertOk()->assertJsonPath('recovery.state', 'cancelled');
    expect(OwnerRecovery::query()->where('state', OwnerRecovery::PENDING)->count())->toBe(0);
    $this->travel(8)->days();
    arxStaffPost($iam, "/v1/staff/customers/{$small->id}/owner-recovery/complete")->assertStatus(409)->assertJsonPath('error', 'owner_recovery_not_pending');
    expect($owner->fresh()->hasTotp())->toBeTrue();
});

it('completes an MFA recovery only while it still reaches every organization the owner could let a caller into', function () {
    [$owner, $org] = $this->customerWithOrganization();
    arxMember($org, 'org_admin');
    $owner->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();
    $iam = $this->steppedUpStaff('iam_admin');
    arxOpenRecovery($iam, $org, ['mode' => 'mfa_reset', 'reason' => 'Vlastník ztratil telefon, ověřeno dokladem', 'ticket_ref' => 'T-4802']);

    // during the week the owner founds another company: its members were never told
    app(OrganizationService::class)->create($owner, ['name' => 'Nová s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('arx fixture'));
    $this->travel(7)->days();
    $this->travel(2)->minutes();
    arxStaffPost($iam, "/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertStatus(409)->assertJsonPath('error', 'owner_recovery_stale');
    expect($owner->fresh()->hasTotp())->toBeTrue();
});

it('asks a second person before resetting the MFA of a staff account or an organization admin, and tells their organizations', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = arxMember($org, 'org_admin');
    $developer = arxMember($org, 'developer');
    $colleague = User::factory()->staff()->create();
    foreach ([$admin, $developer, $colleague] as $person) {
        $person->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_confirmed_at' => now()])->save();
    }
    $iam = $this->steppedUpStaff('iam_admin');
    $reset = fn (User $who, array $extra = []) => arxStaffPost($iam, "/v1/staff/users/{$who->id}/mfa-reset", ['reason' => 'Ztracený telefon, ověřeno'] + $extra);

    foreach ([$colleague, $admin] as $person) {
        $approval = (string) $reset($person)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
        expect($person->fresh()->hasTotp())->toBeTrue();
        secondPersonApproves($approval);
        $reset($person, ['approval_ids' => [$approval]])->assertOk()->assertJsonPath('reset', true);
        expect($person->fresh()->hasTotp())->toBeFalse();
    }
    // a member who manages nobody needs no second person, but the organization is told either way
    $reset($developer)->assertOk();
    app(OutboxPublisher::class)->relayPending();
    expect(arxMails('member-mfa-reset'))->toContain(mb_strtolower($owner->email))
        ->and(OutboxMessage::query()->where('name', 'organization.member.mfa_reset')->where('organization_id', $org->id)->count())->toBe(2);
});

it('keeps a member of staff who is a party of the organization out of its owner recovery', function () {
    [$owner, $org] = $this->customerWithOrganization();
    arxMember($org, 'viewer');
    $iam = $this->steppedUpStaff('iam_admin');
    app(OrganizationService::class)->attachMember($org, $iam, 'developer', CommandContext::system('arx fixture'), true); // the support agent is also a customer member

    // naming themselves the heir, or recovering an organization they belong to: refused (after the second person, in the handler)
    foreach ([['mode' => 'transfer', 'new_owner_user_id' => $iam->id], ['mode' => 'mfa_reset']] as $mode) {
        $body = $mode + ['reason' => 'Vlastník je nedostupný, ověřeno dokladem', 'ticket_ref' => 'T-4803'];
        $approval = (string) arxRecovery($iam, $org, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
        secondPersonApproves($approval);
        app(StepUpService::class)->grant($iam, 'totp', null, '127.0.0.1');
        arxRecovery($iam, $org, $body, $approval)->assertForbidden()->assertJsonPath('error', 'owner_recovery_party');
    }
    expect(OwnerRecovery::query()->where('organization_id', $org->id)->exists())->toBeFalse();

    // a colleague opens it; the member of staff who belongs to the organization does not complete it
    $colleague = $this->steppedUpStaff('iam_admin');
    arxOpenRecovery($colleague, $org, ['mode' => 'mfa_reset', 'reason' => 'Vlastník ztratil telefon, ověřeno dokladem', 'ticket_ref' => 'T-4804']);
    $this->travel(7)->days();
    $this->travel(2)->minutes();
    arxStaffPost($iam, "/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertForbidden()->assertJsonPath('error', 'owner_recovery_party');
    arxStaffPost($colleague, "/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertOk()->assertJsonPath('recovery.state', 'completed');
});

it('keeps one owner recovery and one ownership offer per organization even when two requests race', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $heir = arxMember($org, 'org_admin');
    $iam = $this->steppedUpStaff('iam_admin');
    $staff = new CommandContext('user', $iam->id, null, null, '127.0.0.1', 'pest', 'arx-race');
    // the other request's row lands between this one's look and its insert
    OwnerRecovery::creating(function (OwnerRecovery $recovery) use ($owner): void {
        if (DB::table('owner_recoveries')->where('organization_id', $recovery->organization_id)->doesntExist()) {
            DB::table('owner_recoveries')->insert(['id' => 'orc_'.strtolower((string) Str::ulid()), 'organization_id' => $recovery->organization_id, 'owner_user_id' => $owner->id,
                'mode' => 'mfa_reset', 'state' => 'pending', 'reason' => 'the other request', 'ticket_ref' => 'T-0', 'not_before' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
        }
    });
    arxRefuses(fn () => app(OwnerRecoveries::class)->open($org, 'mfa_reset', null, 'Vlastník ztratil telefon, ověřeno', 'T-4805', $staff), 'owner_recovery_pending');

    OwnershipTransfer::creating(function (OwnershipTransfer $transfer) use ($owner, $heir): void {
        if (DB::table('ownership_transfers')->where('organization_id', $transfer->organization_id)->doesntExist()) {
            DB::table('ownership_transfers')->insert(['id' => 'otr_'.strtolower((string) Str::ulid()), 'organization_id' => $transfer->organization_id, 'from_user_id' => $owner->id, 'to_user_id' => $heir->id,
                'state' => 'pending', 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
        }
    });
    arxRefuses(fn () => app(CommandBus::class)->dispatch(new OwnershipCommand($org->id, 'arx-own-'.Str::ulid(), ['op' => 'offer', 'user_id' => $heir->id]), arxContext($owner, $org)), 'ownership_offer_pending');

    // the database keeps it even for a writer that never asked: a second pending row is refused
    [, $other] = $this->customerWithOrganization();
    $row = fn () => DB::table('owner_recoveries')->insert(['id' => 'orc_'.strtolower((string) Str::ulid()), 'organization_id' => $other->id, 'owner_user_id' => $owner->id,
        'mode' => 'mfa_reset', 'state' => 'pending', 'reason' => 'x', 'ticket_ref' => 'T-0', 'not_before' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
    $row();
    expect($row)->toThrow(UniqueConstraintViolationException::class);
});

it('restores a snapshot once even when two restores of it race', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $member = arxMember($org, 'developer');
    arxOp($owner, $org, ['op' => 'remove_member', 'user_id' => $member->id]);
    $snapshot = arxSnapshot($org, $member, 'member_removed');
    $first = AccessSnapshot::query()->findOrFail($snapshot->id);
    $second = AccessSnapshot::query()->findOrFail($snapshot->id); // loaded by the other request before the first one committed

    app(AccessSnapshots::class)->restore($org, $first, arxContext($owner, $org));
    arxRefuses(fn () => app(AccessSnapshots::class)->restore($org, $second, arxContext($owner, $org)), 'snapshot_restored');
    expect(OutboxMessage::query()->where('name', 'organization.access.restored')->where('organization_id', $org->id)->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'organization.access.restore')->where('organization_id', $org->id)->count())->toBe(1);
});

it('records a lost grantor\'s grant once however many audit rows came in between', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$admin] = arxAdminWithGrants($org, $owner);
    arxOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);
    expect(AuditEvent::query()->where('organization_id', $org->id)->where('action', 'organization.grant.cascade.flag')->count())->toBe(3);

    // a busy organization: 600 other flags since, then the outbox delivers the loss again
    $later = now()->addMinute();
    foreach (array_chunk(range(1, 600), 200) as $chunk) {
        DB::table('audit_events')->insert(array_map(fn (int $i) => ['id' => 'aud_'.strtolower((string) Str::ulid()), 'actor_type' => 'system', 'organization_id' => $org->id, 'action' => 'organization.grant.cascade.flag',
            'result' => 'recorded', 'detail' => json_encode(['ref' => "other-{$i}", 'grantor_id' => 'usr_somebody_else']), 'prev_hash' => str_repeat('0', 64), 'hash' => hash('sha256', Str::random(40)), 'created_at' => $later], $chunk));
    }
    $this->travel(2)->minutes();
    $stats = app(GrantCascade::class)->onGrantorLoss($org, $admin->id, 'member_removed', CommandContext::system('arx replay'));

    expect($stats['flagged'])->toBe(0)
        ->and(AuditEvent::query()->where('organization_id', $org->id)->where('action', 'organization.grant.cascade.flag')->where('detail->grantor_id', $admin->id)->count())->toBe(3);
});
