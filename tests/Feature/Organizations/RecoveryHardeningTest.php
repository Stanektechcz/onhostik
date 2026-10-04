<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Commands\ApiTokenCommand;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\StepUpGrant;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Commands\OwnershipCommand;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Domain\Organizations\Models\AccessSnapshot;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\OwnerRecovery;
use Onhost\Domain\Organizations\Models\OwnershipTransfer;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Organizations\OwnerRecoveries;
use Onhost\Domain\Services\Commands\ServiceAccessCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0044 (permission program Slice 1, the S1-07 red-team MEDIUMs of the breach register "Still open after Slice 1"): the second
 * person of an owner recovery is no party of it; a transfer ends in an offer the heir accepts in person; the person being recovered
 * cannot quietly cancel a transfer; a reset of anybody holding a HIGH key takes a second person; a restore never pulls back
 * somebody who left on their own or whose account is closed; a disabled service account grants nothing; a token ends with the
 * bindings behind its scopes.
 */

function orhMember(Organization $org, string $role, ?CarbonInterface $until = null): User
{
    $user = User::factory()->create();
    app(OrganizationService::class)->attachMember($org, $user, $role, CommandContext::system('orh fixture'), true, $until);

    return $user;
}

function orhContext(User $actor, ?Organization $org, bool $stepUp = true): CommandContext
{
    if ($stepUp) {
        app(StepUpService::class)->grant($actor, 'totp', null, '127.0.0.1');
    } else {
        StepUpGrant::query()->where('user_id', $actor->id)->delete();
    }

    return new CommandContext('user', $actor->id, $org?->id, null, '127.0.0.1', 'pest', 'orh-session');
}

function orhOp(User $actor, Organization $org, array $payload, bool $stepUp = true): mixed
{
    return app(CommandBus::class)->dispatch(new OrganizationCommand($org->id, 'orh-'.Str::ulid(), $payload), orhContext($actor, $org, $stepUp));
}

function orhOwnership(User $actor, Organization $org, string $op, bool $stepUp = true): mixed
{
    return app(CommandBus::class)->dispatch(new OwnershipCommand($org->id, 'orh-own-'.Str::ulid(), ['op' => $op]), orhContext($actor, $org, $stepUp));
}

function orhRefuses(Closure $attempt, string $error): void
{
    try {
        $attempt();
    } catch (DomainError $e) {
        expect($e->error)->toBe($error, $e->getMessage());

        return;
    }
    test()->fail("Expected the refusal {$error}; the attempt went through.");
}

/** A ticket of the organization — the evidence an owner recovery names (TASK-0044: nothing else is accepted). */
function orhTicket(Organization $org): string
{
    return (string) Ticket::query()->create(['number' => 'TK-2026-'.random_int(10000, 99999), 'organization_id' => $org->id, 'email' => 'owner@example.test', 'subject' => 'Ztracený přístup vlastníka'])->number;
}

function orhRecovery(User $staff, Organization $org, array $body, ?string $approvalId = null)
{
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');

    return test()->flushHeaders()->actingAs($staff, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())
        ->postJson("/v1/staff/customers/{$org->id}/owner-recovery", $body + ($approvalId !== null ? ['approval_ids' => [$approvalId]] : []));
}

function orhOpen(User $staff, Organization $org, array $body): OwnerRecovery
{
    $approval = (string) orhRecovery($staff, $org, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    secondPersonApproves($approval);
    orhRecovery($staff, $org, $body, $approval)->assertCreated();
    app(OutboxPublisher::class)->relayPending();

    return OwnerRecovery::query()->where('organization_id', $org->id)->latest('created_at')->firstOrFail();
}

function orhStaff(User $staff, string $method, string $path, array $body = [])
{
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');

    return test()->flushHeaders()->actingAs($staff, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())->json($method, $path, $body);
}

function orhWeekPasses(): void
{
    test()->travel(7)->days();
    test()->travel(2)->minutes();
}

beforeEach(function () {
    Http::fake();
});

// ── (1) the second person of an owner recovery is no party of it ─────────────────────────────────────────────────────────

it('refuses a second person who is a party of an organization the owner recovery reaches, when approving and when it runs', function () {
    [$owner, $org] = $this->customerWithOrganization();
    orhMember($org, 'viewer');
    $iam = $this->steppedUpStaff('iam_admin');
    $party = $this->staff('iam_admin');
    app(OrganizationService::class)->attachMember($org, $party, 'developer', CommandContext::system('orh fixture'), true); // support, and a customer member there
    $body = ['mode' => 'mfa_reset', 'reason' => 'Vlastník ztratil telefon, ověřeno dokladem', 'ticket_ref' => orhTicket($org)];

    $approval = (string) orhRecovery($iam, $org, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    orhRefuses(fn () => secondPersonApproves($approval, $party), 'owner_recovery_party');
    expect(Approval::query()->findOrFail($approval)->state)->toBe('pending');

    // a colleague who was no party approves it, then joins the organization before the repeat: the opening refuses them
    $colleague = $this->staff('iam_admin');
    secondPersonApproves($approval, $colleague);
    app(OrganizationService::class)->attachMember($org, $colleague, 'viewer', CommandContext::system('orh fixture'), true);
    orhRecovery($iam, $org, $body, $approval)->assertForbidden()->assertJsonPath('error', 'owner_recovery_party');
    expect(OwnerRecovery::query()->where('organization_id', $org->id)->exists())->toBeFalse();

    // …and one who became a party during the week does not let it complete
    $third = $this->staff('iam_admin');
    $open = (string) orhRecovery($iam, $org, $body)->assertForbidden()->json('approval_id');
    secondPersonApproves($open, $third);
    orhRecovery($iam, $org, $body, $open)->assertCreated();
    app(OrganizationService::class)->attachMember($org, $third, 'viewer', CommandContext::system('orh fixture'), true);
    orhWeekPasses();
    orhStaff($iam, 'POST', "/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertForbidden()->assertJsonPath('error', 'owner_recovery_party');
});

// ── (2) a transfer ends in an offer the heir accepts in person ──────────────────────────────────────────────────────────────

it('ends a transfer recovery in an ownership offer that the heir accepts in person, with a fresh step-up', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $heir = orhMember($org, 'org_admin');
    $iam = $this->steppedUpStaff('iam_admin');

    orhOpen($iam, $org, ['mode' => 'transfer', 'new_owner_user_id' => $heir->id, 'reason' => 'Účet vlastníka byl převzat útočníkem', 'ticket_ref' => orhTicket($org)]);
    orhWeekPasses();
    orhStaff($iam, 'POST', "/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertOk()
        ->assertJsonPath('recovery.state', 'pending')->assertJsonPath('recovery.phase', 'offered');
    app(OutboxPublisher::class)->relayPending();

    // nothing moved yet: the heir is asked, and whoever holds the owner's account can neither withdraw the offer nor act meanwhile
    $transfer = OwnershipTransfer::query()->where('organization_id', $org->id)->where('state', OwnershipTransfer::PENDING)->firstOrFail();
    expect($org->fresh()->owner_user_id)->toBe($owner->id)
        ->and($transfer->to_user_id)->toBe($heir->id)
        ->and($transfer->recovery_id)->not->toBeNull()
        ->and(MailOutbox::query()->where('template_key', 'ownership-offered')->where('to', $heir->email)->exists())->toBeTrue();
    orhRefuses(fn () => orhOwnership($owner, $org, 'cancel'), 'owner_transfer_recovery');
    orhRefuses(fn () => app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'orh-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read']]), orhContext($owner, $org)), 'owner_recovery_hold');

    // the heir accepts only with a fresh step-up, in person
    orhRefuses(fn () => orhOwnership($heir, $org, 'accept', stepUp: false), 'step_up_required');
    orhOwnership($heir, $org, 'accept');
    app(OutboxPublisher::class)->relayPending();

    expect($org->fresh()->owner_user_id)->toBe($heir->id)
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $owner->id)->exists())->toBeFalse()
        ->and(OwnerRecovery::query()->where('organization_id', $org->id)->value('state'))->toBe(OwnerRecovery::COMPLETED)
        ->and(StepUpGrant::query()->where('user_id', $owner->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(OutboxMessage::query()->where('name', 'organization.owner_recovery.completed')->where('organization_id', $org->id)->exists())->toBeTrue();
});

it('offers the ownership only to a heir who is still a member and not a guest, and never to a guest otherwise', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $heir = orhMember($org, 'org_admin');
    $iam = $this->steppedUpStaff('iam_admin');
    orhOpen($iam, $org, ['mode' => 'transfer', 'new_owner_user_id' => $heir->id, 'reason' => 'Vlastník zemřel, doloženo úmrtním listem', 'ticket_ref' => orhTicket($org)]);

    // during the week the heir is made a guest: the recovery names somebody who no longer holds the organization
    orhOp($owner, $org, ['op' => 'change_role', 'user_id' => $heir->id, 'role' => 'guest']);
    orhWeekPasses();
    orhStaff($iam, 'POST', "/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertStatus(409)->assertJsonPath('error', 'owner_recovery_stale');
    expect(OwnershipTransfer::query()->where('organization_id', $org->id)->exists())->toBeFalse();

    // an owner's own offer to a guest is refused too
    [$owner2, $org2] = $this->customerWithOrganization();
    $guest = orhMember($org2, 'guest');
    orhRefuses(fn () => app(CommandBus::class)->dispatch(new OwnershipCommand($org2->id, 'orh-own-'.Str::ulid(), ['op' => 'offer', 'user_id' => $guest->id]), orhContext($owner2, $org2)), 'owner_transfer_guest');
});

// ── (3) cancelling: the person recovered is reviewed, everybody else gives a reason, repeats alert staff, a real ticket ─────

it('sends the recovered owner\'s cancel of a transfer to a staff review that only a second person continues', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $heir = orhMember($org, 'org_admin');
    $iam = $this->steppedUpStaff('iam_admin');
    orhOpen($iam, $org, ['mode' => 'transfer', 'new_owner_user_id' => $heir->id, 'reason' => 'Účet vlastníka byl převzat útočníkem', 'ticket_ref' => orhTicket($org)]);

    // the owner's account — perhaps in the attacker's hands — objects: the recovery waits for staff instead of closing
    $answer = orhOp($owner, $org, ['op' => 'cancel_owner_recovery']);
    expect($answer['recovery']['state'])->toBe('pending')->and($answer['recovery']['phase'])->toBe('contested')
        ->and(OutboxMessage::query()->where('name', 'organization.owner_recovery.contested')->where('organization_id', $org->id)->exists())->toBeTrue();
    orhRefuses(fn () => orhOp($owner, $org, ['op' => 'cancel_owner_recovery']), 'owner_recovery_contested');
    orhWeekPasses();
    orhStaff($iam, 'POST', "/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertStatus(409)->assertJsonPath('error', 'owner_recovery_contested');

    // continuing takes the evidence and a second person
    orhStaff($iam, 'POST', "/v1/staff/customers/{$org->id}/owner-recovery/continue", ['evidence' => 'krátce'])->assertStatus(422);
    $evidence = ['evidence' => 'Videohovor s jednatelem, OP ověřen, výpis z OR 2026-09-20'];
    $approval = (string) orhStaff($iam, 'POST', "/v1/staff/customers/{$org->id}/owner-recovery/continue", $evidence)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    secondPersonApproves($approval);
    orhStaff($iam, 'POST', "/v1/staff/customers/{$org->id}/owner-recovery/continue", $evidence + ['approval_ids' => [$approval]])->assertOk()->assertJsonPath('recovery.phase', null);
    expect(OwnerRecovery::query()->where('organization_id', $org->id)->value('review_evidence'))->toBe($evidence['evidence'])
        ->and(AuditEvent::query()->where('action', 'organization.owner_recovery.continue')->where('organization_id', $org->id)->exists())->toBeTrue();

    // reviewed: the owner's second objection no longer stops it, and support completes it
    orhRefuses(fn () => orhOp($owner, $org, ['op' => 'cancel_owner_recovery']), 'owner_recovery_reviewed');
    orhStaff($iam, 'POST', "/v1/staff/customers/{$org->id}/owner-recovery/complete")->assertOk()->assertJsonPath('recovery.phase', 'offered');
});

it('asks everybody else who cancels a recovery for the reason, and alerts staff when an organization keeps cancelling them', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = orhMember($org, 'org_admin');
    $iam = $this->steppedUpStaff('iam_admin');
    $body = ['mode' => 'mfa_reset', 'reason' => 'Vlastník ztratil telefon, ověřeno dokladem', 'ticket_ref' => orhTicket($org)];

    orhOpen($iam, $org, $body);
    orhRefuses(fn () => orhOp($admin, $org, ['op' => 'cancel_owner_recovery']), 'owner_recovery_cancel_reason');
    orhOp($admin, $org, ['op' => 'cancel_owner_recovery', 'reason' => 'Vlastník je na dovolené, telefon má u sebe']);
    expect(OwnerRecovery::query()->where('organization_id', $org->id)->value('cancel_reason'))->toBe('Vlastník je na dovolené, telefon má u sebe')
        ->and(OutboxMessage::query()->where('name', 'organization.owner_recovery.cancels_repeated')->exists())->toBeFalse();

    // support gives a reason too
    orhOpen($iam, $org, $body);
    orhStaff($iam, 'DELETE', "/v1/staff/customers/{$org->id}/owner-recovery")->assertStatus(422);
    orhStaff($iam, 'DELETE', "/v1/staff/customers/{$org->id}/owner-recovery", ['reason' => 'Zákazník se ozval, přístup má'])->assertOk()->assertJsonPath('recovery.state', 'cancelled');

    // the second cancel within the window already tells staff: somebody may be stopping every attempt
    expect(OutboxMessage::query()->where('name', 'organization.owner_recovery.cancels_repeated')->where('organization_id', $org->id)->exists())->toBeTrue();
});

it('accepts only a ticket of the organization as the evidence an owner recovery names', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $iam = $this->steppedUpStaff('iam_admin');
    $staff = new CommandContext('user', $iam->id, null, null, '127.0.0.1', 'pest', 'orh-staff');
    $open = fn (string $ref) => app(OwnerRecoveries::class)->open($org, 'mfa_reset', null, 'Vlastník ztratil telefon, ověřeno dokladem', $ref, $staff);

    orhRefuses(fn () => $open('T-4711'), 'owner_recovery_ticket_invalid');
    orhRefuses(fn () => $open(orhTicket($other)), 'owner_recovery_ticket_invalid');
    $ticket = Ticket::query()->where('number', orhTicket($org))->firstOrFail();
    expect($open($ticket->id)->ticket_ref)->toBe($ticket->number); // by id or by number; the number is what is kept
});

// ── (4) an MFA reset of anybody holding a HIGH key takes a second person ────────────────────────────────────────────────────

it('asks a second person before resetting the MFA of anybody holding a high customer key or a console in any organization', function () {
    [, $org] = $this->customerWithOrganization();
    [, $elsewhere] = $this->customerWithOrganization();
    $developer = orhMember($org, 'developer');         // consoles on every service
    $billing = orhMember($org, 'billing_admin');       // payment methods (HIGH)
    $operator = orhMember($org, 'viewer');             // a viewer here…
    app(OrganizationService::class)->attachMember($elsewhere, $operator, 'cloud_operator', CommandContext::system('orh fixture'), true); // …deletes VMs there
    $viewer = orhMember($org, 'viewer');
    $iam = $this->steppedUpStaff('iam_admin');
    $reset = fn (User $who) => orhStaff($iam, 'POST', "/v1/staff/users/{$who->id}/mfa-reset", ['reason' => 'Ztracený telefon, ověřeno']);

    foreach ([$developer, $billing, $operator] as $person) {
        $reset($person)->assertForbidden()->assertJsonPath('error', 'approval_required');
    }
    $reset($viewer)->assertOk()->assertJsonPath('reset', true);
});

// ── (5) a restore never pulls back somebody who left or whose account is closed; the person is told ────────────────────────

it('restores neither somebody who left on their own nor a disabled account, and tells the person restored in person', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $leaver = orhMember($org, 'org_admin');
    orhOp($leaver, $org, ['op' => 'remove_member', 'user_id' => $leaver->id]); // leaving is the one change of your own
    $left = AccessSnapshot::query()->where('organization_id', $org->id)->where('user_id', $leaver->id)->where('reason', 'member_removed')->firstOrFail();
    orhRefuses(fn () => orhOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => $left->id]), 'snapshot_self_leave');

    $closed = orhMember($org, 'developer');
    orhOp($owner, $org, ['op' => 'remove_member', 'user_id' => $closed->id]);
    $closed->forceFill(['state' => 'disabled'])->save();
    $snapshot = AccessSnapshot::query()->where('organization_id', $org->id)->where('user_id', $closed->id)->where('reason', 'member_removed')->firstOrFail();
    orhRefuses(fn () => orhOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => $snapshot->id]), 'snapshot_person_inactive');

    $member = orhMember($org, 'developer');
    orhOp($owner, $org, ['op' => 'remove_member', 'user_id' => $member->id]);
    $back = AccessSnapshot::query()->where('organization_id', $org->id)->where('user_id', $member->id)->where('reason', 'member_removed')->firstOrFail();
    orhOp($owner, $org, ['op' => 'restore_access', 'snapshot_id' => $back->id]);
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'access-restored')->where('to', $member->email)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('user_id', $member->id)->where('event', 'organization.access.restored')->exists())->toBeTrue();
});

// ── (6) a service account grants only while it is active; its bindings are somebody's grants too ─────────────────────────────

it('lets a service account grant only while it is active, and counts its bindings among a lost grantor\'s grants', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $account = ServiceAccount::query()->create(['organization_id' => $org->id, 'name' => 'terraform', 'state' => 'active', 'created_by' => $owner->id]);
    PolicyBinding::query()->create(['principal_type' => 'service_account', 'principal_id' => $account->id, 'role_key' => 'org_admin', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id, 'granted_by' => $owner->id]);
    $asAccount = new CommandContext('service_account', $account->id, $org->id);
    $policy = app(GrantPolicy::class);
    $invitation = OrganizationInvitation::query()->create(['organization_id' => $org->id, 'email' => 'dev@example.test', 'role_key' => 'viewer', 'token_hash' => hash('sha256', Str::random(40)), 'invited_by' => $account->id, 'expires_at' => now()->addDays(7)]);

    $policy->assertMayInvite($org, $asAccount, 'viewer');
    expect($policy->backs($org, $invitation))->toBeTrue();

    $account->forceFill(['state' => 'disabled'])->save();
    orhRefuses(fn () => $policy->assertMayInvite($org, $asAccount, 'viewer'), 'grantor_inactive');
    expect($policy->backs($org, $invitation))->toBeFalse();

    // an admin gave a pipeline developer rights and was then removed: the pipeline's binding is one of their grants
    $admin = orhMember($org, 'org_admin');
    $pipeline = ServiceAccount::query()->create(['organization_id' => $org->id, 'name' => 'ci', 'state' => 'active', 'created_by' => $admin->id]);
    $binding = PolicyBinding::query()->create(['principal_type' => 'service_account', 'principal_id' => $pipeline->id, 'role_key' => 'developer', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id, 'granted_by' => $admin->id]);
    orhOp($owner, $org, ['op' => 'remove_member', 'user_id' => $admin->id]);
    app(OutboxPublisher::class)->relayPending();
    expect(collect($policy->dependents($org, $admin->id))->firstWhere('ref', (string) $binding->getKey()))->toMatchArray(['kind' => 'service_account_role', 'user_id' => $pipeline->id, 'role' => 'developer'])
        ->and(AuditEvent::query()->where('action', 'organization.grant.cascade.flag')->where('detail->ref', (string) $binding->getKey())->exists())->toBeTrue();
});

// ── (7) a token ends with the bindings behind its scopes ────────────────────────────────────────────────────────────────────

it('ends a token no later than the bindings that carry the scopes it asks for', function () {
    $this->freezeSecond();
    [$owner, $org] = $this->customerWithOrganization();
    DB::table('role_permissions')->insertOrIgnore(['role_key' => 'viewer', 'permission_key' => 'api_token.manage']); // an IAM edit: viewers make tokens
    $member = orhMember($org, 'viewer');
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'compute', 'name' => 'VPS', 'hostname' => 'orh-'.Str::lower(Str::random(8)).'.cz',
        'state' => ServiceStateMachine::ACTIVE, 'desired_spec' => [], 'entitlements' => [], 'tags' => [], 'health' => []]);
    $until = now()->addDays(10)->startOfSecond();
    app(CommandBus::class)->dispatch(new ServiceAccessCommand($org->id, 'orh-share-'.Str::ulid(), ['op' => 'share', 'service_id' => $service->id, 'email' => $member->email, 'capabilities' => ['console'], 'access_until' => $until->toIso8601String()]), orhContext($owner, $org));
    $token = fn (array $scopes) => app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'orh-token-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => $scopes, 'expires_in_days' => 365]), orhContext($member, $org));

    expect($token(['services:read', 'services:console'])['expires_at'])->toBe($until->toIso8601String()) // the console comes from a share that ends on the 10th
        ->and($token(['services:read'])['expires_at'])->toBe(now()->addDays(365)->toIso8601String());   // reading comes from the membership, which does not end
});
