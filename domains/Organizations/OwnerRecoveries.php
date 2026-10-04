<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Commands\MfaResetCommandHandler;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\SessionKill;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\OwnerRecovery;
use Onhost\Domain\Organizations\Models\OwnershipTransfer;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * A customer owner who genuinely lost access — the phone with the authenticator, the mailbox, the person themselves — is recovered
 * in the open (permission program D21, S1-02). Without a designed path staff improvise with `iam.mfa.reset`, the main
 * social-engineering route to taking over a company's account: a caller who knows enough about the owner asks support to "reset
 * the authenticator", and the caller is in. So:
 *  · opening a recovery is CRITICAL (OwnerRecoveryCommand: a second person approves it; the sole approver waits the time lock),
 *    with a reason and a ticket;
 *  · it runs only after `onhost.grants.owner_recovery_days` (never below 7) of notice to every member and the owner's own address,
 *    and any org_admin — or the owner, who is then evidently not lost — cancels it meanwhile;
 *  · while it waits, nothing that would let a caller walk away with the organization leaves it: no API token, no data export,
 *    no ownership offer, no restore of an access snapshot (assertNoHold);
 *  · it then either resets the owner's MFA (mode mfa_reset — they sign in with their password and enrol again) or hands the
 *    ownership to a named current member (mode transfer — the owner is gone for good, so their account leaves the organization
 *    with its tokens and step-up: takeOutPreviousOwner);
 *  · `iam.mfa.reset` of a customer owner outside it is refused (MfaResetCommandHandler).
 *
 * Review round 1 (TASK-0042): the MFA is the PERSON's, not the organization's. A recovery opened on a small organization reset the
 * owner everywhere — the large company they also own, the neighbour's organization they manage — whose members were never told,
 * never held and could never cancel. So a recovery of mode mfa_reset reaches (reach()) every organization the person owns or
 * manages the members of: one row in each, tied by group_id, each told, each held, and a cancel in any of them stops them all.
 * It completes only while that is still the whole reach. And a member of staff who is a party of any of them — its member, the
 * heir, the owner — neither opens nor completes it: the command has no organization scope, so P0-16's second person for staff in
 * their own organization never applied.
 *
 * TASK-0044 (the S1-07 red-team MEDIUMs of the breach register, "Still open after Slice 1"):
 *  · the SECOND person is no party either (assertApproverNotParty, asked by ApprovalService::decide; the opening, a continue and the
 *    completion ask it again of whoever decided the approvals they carry) — the heir or a member who is also staff approved it;
 *  · a transfer ends in an OFFER the heir accepts in person with a fresh step-up (offerToHeir → OwnershipTransfers::accept →
 *    completeByHeir), and only while the heir is still a member and not a guest (`owner_recovery_stale`) — before, support made
 *    somebody the owner of a company without a word from them, and the heir's role was looked at only on the day it was opened;
 *  · the person being recovered — in the hijack case, the account in the attacker's hands — no longer stops a transfer by
 *    cancelling it: their objection sends it to a staff review (phase `contested`) that only a second person continues, with the
 *    evidence written down; anybody else who cancels says why; the second stop within the window alerts staff;
 *  · the ticket named is a ticket of the organization, kept by its number — a free-text "T-1" was evidence of nothing.
 */
final class OwnerRecoveries
{
    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public static function delayDays(): int
    {
        return max(7, (int) config('onhost.grants.owner_recovery_days', 7));
    }

    public static function pending(Organization $organization): ?OwnerRecovery
    {
        return OwnerRecovery::query()->where('organization_id', $organization->id)->where('state', OwnerRecovery::PENDING)->latest('created_at')->first();
    }

    /** While a recovery waits, `$what` does not leave the organization (D21: "no billing/credential export during the lock"). */
    public static function assertNoHold(Organization $organization, string $what): void
    {
        $pending = self::pending($organization);
        if ($pending !== null) {
            throw new DomainError('owner_recovery_hold', 'An owner recovery is under way in this organization; until it is completed or cancelled this is not possible.', 409, ['hold' => $what, 'recovery_id' => $pending->id, 'not_before' => $pending->not_before->toIso8601String()]);
        }
    }

    /**
     * The organizations whose members `$userId` manages by their role there (organization.members.manage — org_admin, a role an
     * IAM edit gave it). What a caller holding their second factor could do to other people's access; the owner's own ones included.
     *
     * @return list<string>
     */
    public static function managedOrganizationIds(string $userId): array
    {
        return OrganizationMembership::query()->where('user_id', $userId)->current()->get()
            ->filter(fn (OrganizationMembership $m) => in_array('organization.members.manage', GrantPolicy::permissionsOf((string) $m->role_key) ?? [], true))
            ->map(fn (OrganizationMembership $m) => (string) $m->organization_id)->values()->all();
    }

    /**
     * What a recovery of `$mode` reaches: a transfer only the organization named; an MFA reset every organization the person
     * owns or manages, the named one first.
     *
     * @return list<string>
     */
    public static function reach(User $person, Organization $named, string $mode): array
    {
        if ($mode !== 'mfa_reset') {
            return [$named->id];
        }
        $owned = Organization::query()->where('owner_user_id', $person->id)->pluck('id')->map(fn ($id) => (string) $id)->all();

        return array_values(array_unique([$named->id, ...$owned, ...self::managedOrganizationIds($person->id)]));
    }

    /** TASK-0044: the ticket of `$organization` a recovery names — by its number (TK-2026-0001) or its id; null for anything else. */
    public static function ticketOf(Organization $organization, string $reference): ?Ticket
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        return Ticket::query()->where('organization_id', $organization->id)->where(fn ($q) => $q->where('number', $reference)->orWhere('id', $reference))->first();
    }

    public function open(Organization $organization, string $mode, ?string $newOwnerId, string $reason, string $ticketRef, CommandContext $context): OwnerRecovery
    {
        if (! in_array($mode, OwnerRecovery::MODES, true)) {
            throw new DomainError('owner_recovery_mode_invalid', 'mode must be mfa_reset or transfer.', 422, ['field' => 'mode']);
        }
        if (mb_strlen(trim($reason)) < 10 || trim($ticketRef) === '') {
            throw new DomainError('owner_recovery_basis_required', 'Say why (at least 10 characters) and name the ticket the owner\'s identity was checked in.', 422, ['field' => 'reason']);
        }
        // TASK-0044 (D21): the evidence is a ticket OF THIS organization — the conversation support can be asked about later
        $ticket = self::ticketOf($organization, $ticketRef)
            ?? throw new DomainError('owner_recovery_ticket_invalid', 'Name a support ticket of this organization — the one the owner\'s identity was checked in.', 422, ['field' => 'ticket_ref']);
        $owner = User::query()->find((string) $organization->owner_user_id) ?? throw DomainError::notFound('owner');
        $reach = self::reach($owner, $organization, $mode);
        $heirId = $mode === 'transfer' ? $newOwnerId : null;
        self::assertNotParty($context, $owner, $heirId, $reach);
        self::assertApproversNotParty($context->verifiedApprovalIds, $owner, $heirId, $reach);
        foreach ($reach as $organizationId) {
            if (OwnerRecovery::query()->where('organization_id', $organizationId)->where('state', OwnerRecovery::PENDING)->exists()) {
                throw DomainError::conflict('owner_recovery_pending', 'An owner recovery is already under way in this organization'.($organizationId === $organization->id ? '.' : ' or in another one this recovery reaches.'), ['organization_id' => $organizationId]);
            }
        }
        if ($mode === 'transfer') {
            $heir = $newOwnerId === null ? null : User::query()->find($newOwnerId);
            $member = $heir === null ? null : OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $heir->id)->current()->first();
            if ($member === null || $heir?->id === $owner->id || $member->role_key === 'guest') {
                throw DomainError::notFound('member'); // the new owner is somebody already in the organization, never a stranger (I8)
            }
        }
        $groupId = (new OwnerRecovery)->newUniqueId();
        $notBefore = now()->addDays(self::delayDays());
        try {
            // in a savepoint: a request that raced this one holds a pending row since the look above; on PostgreSQL the refused
            // insert must not abort the surrounding bus transaction (SQLSTATE 25P02)
            $rows = DB::transaction(fn () => array_map(fn (string $organizationId) => OwnerRecovery::query()->create([
                'id' => $organizationId === $organization->id ? $groupId : (new OwnerRecovery)->newUniqueId(), 'group_id' => $groupId,
                'organization_id' => $organizationId, 'owner_user_id' => $owner->id, 'mode' => $mode, 'new_owner_user_id' => $heirId,
                'state' => OwnerRecovery::PENDING, 'reason' => mb_substr(trim($reason), 0, 2000), 'ticket_ref' => mb_substr((string) $ticket->number, 0, 60),
                'requested_by' => $context->actorId, 'approval_ids' => $context->verifiedApprovalIds, 'not_before' => $notBefore,
            ]), $reach));
        } catch (UniqueConstraintViolationException) {
            throw DomainError::conflict('owner_recovery_pending', 'An owner recovery was opened in this organization a moment ago.');
        }
        foreach ($rows as $recovery) {
            $subject = (string) Organization::query()->whereKey($recovery->organization_id)->value('owner_user_id') === $owner->id ? 'owner' : 'admin';
            $this->audit->record($context->withScope($recovery->organization_id), 'organization.owner_recovery.open', 'succeeded', ['recovery_id' => $recovery->id, 'group_id' => $groupId, 'mode' => $mode, 'ticket_ref' => $recovery->ticket_ref, 'not_before' => $recovery->not_before->toIso8601String()], 'organization', $recovery->organization_id);
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.opened', 'organization', $recovery->organization_id, [
                'recovery_id' => $recovery->id, 'group_id' => $groupId, 'named_organization_id' => $organization->id, 'subject' => $subject, 'mode' => $mode, 'owner_user_id' => $owner->id,
                'new_owner_user_id' => $recovery->new_owner_user_id, 'not_before' => $recovery->not_before->toIso8601String(), 'ticket_ref' => $recovery->ticket_ref,
            ], $recovery->organization_id));
        }

        return $rows[0];
    }

    /**
     * Anybody who may manage the members (an org_admin, the owner) of ANY organization it reaches, or support, stops it while it waits.
     *
     * TASK-0044 (S1-07 red team): who stops it decides what a stop is. The person being recovered stopping a TRANSFER is an
     * objection, not an end — in the hijack case it is the attacker keeping the company — so it goes to a staff review (contest());
     * an MFA reset stopped by the person themselves ends (they are evidently not lost). Anybody else says why (`reason`, at least 10
     * characters), which the organization and support read. The system (a lapsed offer) needs no reason.
     */
    public function cancel(Organization $organization, CommandContext $context, ?string $reason = null): OwnerRecovery
    {
        $recovery = self::pending($organization) ?? throw new DomainError('owner_recovery_not_pending', 'No owner recovery is under way in this organization.', 409);
        $by = $context->actorType === 'system' ? null : (string) ($context->onBehalfOfUserId ?? $context->actorId);
        $reason = trim((string) $reason);
        $byOwner = $context->actorType === 'user' && $by === $recovery->owner_user_id;
        if ($byOwner) {
            if ($recovery->mode === 'transfer') {
                return $this->contest($organization, $recovery, (string) $by, $context);
            }
        } elseif ($by !== null && mb_strlen($reason) < 10) {
            throw new DomainError('owner_recovery_cancel_reason', 'Say why the recovery is stopped (at least 10 characters); the organization and support read it.', 422, ['field' => 'reason']);
        }
        $this->close($organization, $recovery, $by, $reason === '' ? null : mb_substr($reason, 0, 1000), $context);
        // TASK-0067 (PR #53 review, LOW): the owner ending an MFA reset of their own account is either the owner who was never lost —
        // or whoever holds the account keeping it. Staff hear of it at the first stop, not at the second
        $this->alertOnRepeats($organization, firstStopAlerts: $byOwner);

        return $recovery->refresh();
    }

    /**
     * TASK-0044: support continues a recovery the person recovered objected to — CRITICAL (OwnerRecoveryCommand `continue`: a
     * second person approves it, and neither of them is a party), with what was checked written down. A transfer whose offer is
     * still waiting goes back to waiting for the heir; anything else waits for its date as before.
     */
    public function continue(Organization $organization, string $evidence, CommandContext $context): OwnerRecovery
    {
        $recovery = self::pending($organization) ?? throw new DomainError('owner_recovery_not_pending', 'No owner recovery is under way in this organization.', 409);
        if ($recovery->phase !== OwnerRecovery::CONTESTED) {
            throw DomainError::conflict('owner_recovery_not_contested', 'Only a recovery the person being recovered objected to is continued; this one waits for its date.');
        }
        $evidence = trim($evidence);
        if (mb_strlen($evidence) < 10) {
            throw new DomainError('owner_recovery_evidence_required', 'Say what was checked (at least 10 characters) before the recovery continues.', 422, ['field' => 'evidence']);
        }
        $rows = self::group($recovery);
        $owner = User::query()->find($recovery->owner_user_id) ?? throw DomainError::notFound('owner');
        $covered = array_map(fn (OwnerRecovery $row) => $row->organization_id, $rows);
        self::assertNotParty($context, $owner, $recovery->new_owner_user_id, $covered);
        self::assertApproversNotParty($context->verifiedApprovalIds, $owner, $recovery->new_owner_user_id, $covered);
        $offered = self::liveOffer($recovery) !== null;
        $by = (string) ($context->onBehalfOfUserId ?? $context->actorId);
        foreach ($rows as $row) {
            $row->forceFill(['phase' => $offered ? OwnerRecovery::OFFERED : null, 'review_evidence' => mb_substr($evidence, 0, 2000), 'reviewed_by' => $by, 'reviewed_at' => now()])->save();
            $this->audit->record($context->withScope($row->organization_id), 'organization.owner_recovery.continue', 'succeeded', ['recovery_id' => $row->id, 'group_id' => $row->group_id, 'evidence' => mb_substr($evidence, 0, 500), 'approval_ids' => $context->verifiedApprovalIds], 'organization', $row->organization_id);
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.continued', 'organization', $row->organization_id, ['recovery_id' => $row->id, 'mode' => $row->mode, 'not_before' => $row->not_before->toIso8601String()], $row->organization_id));
        }

        return $recovery->refresh();
    }

    public function complete(Organization $organization, CommandContext $context): OwnerRecovery
    {
        $recovery = self::pending($organization) ?? throw new DomainError('owner_recovery_not_pending', 'No owner recovery is under way in this organization.', 409);
        if ($recovery->phase === OwnerRecovery::CONTESTED) {
            throw DomainError::conflict('owner_recovery_contested', 'The person being recovered objected to it; support reviews it and a second person continues it first.');
        }
        if ($recovery->not_before->isFuture()) {
            throw new DomainError('owner_recovery_locked', 'The notice period of this owner recovery has not passed yet.', 409, ['not_before' => $recovery->not_before->toIso8601String()]);
        }
        $rows = self::group($recovery);
        $named = Organization::query()->find($rows[0]->organization_id) ?? throw DomainError::notFound('organization'); // the one support named, whichever row was asked
        if ((string) $named->owner_user_id !== $recovery->owner_user_id) {
            throw new DomainError('owner_recovery_stale', 'The organization has another owner since this recovery was opened; cancel it.', 409);
        }
        $owner = User::query()->find($recovery->owner_user_id) ?? throw DomainError::notFound('owner');
        $covered = array_map(fn (OwnerRecovery $row) => $row->organization_id, $rows);
        $unwarned = array_values(array_diff(self::reach($owner, $named, $recovery->mode), $covered));
        if ($unwarned !== []) {
            throw new DomainError('owner_recovery_stale', 'Since this recovery was opened the person owns or manages another organization, whose members were never told; cancel it and open a new one.', 409, ['organization_ids' => $unwarned]);
        }
        self::assertNotParty($context, $owner, $recovery->new_owner_user_id, $covered);
        // TASK-0044: whoever approved the opening is asked again — a second person who became a member or the heir meanwhile is a party now
        self::assertApproversNotParty(array_values(array_map('strval', (array) $rows[0]->approval_ids)), $owner, $recovery->new_owner_user_id, $covered);
        if ($recovery->mode === 'transfer') {
            return $this->offerToHeir($named, $recovery, $rows, $owner, $context);
        }
        app(MfaResetCommandHandler::class)->reset($owner, $context, 'owner recovery '.$recovery->id, $covered);
        foreach ($rows as $row) {
            $row->forceFill(['state' => OwnerRecovery::COMPLETED, 'completed_at' => now(), 'completed_by' => $context->actorId])->save();
            $this->audit->record($context->withScope($row->organization_id), 'organization.owner_recovery.complete', 'succeeded', ['recovery_id' => $row->id, 'group_id' => $row->group_id, 'mode' => $row->mode], 'organization', $row->organization_id);
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.completed', 'organization', $row->organization_id, ['recovery_id' => $row->id, 'mode' => $row->mode, 'new_owner_user_id' => $row->new_owner_user_id], $row->organization_id));
        }

        return $recovery->refresh();
    }

    /** TASK-0044: the pending recovery whose offer this is (null when it was stopped since, or for an owner's own offer). */
    public static function ofOffer(OwnershipTransfer $transfer): ?OwnerRecovery
    {
        if ($transfer->recovery_id === null) {
            return null;
        }

        return OwnerRecovery::query()->where('organization_id', $transfer->organization_id)->where('state', OwnerRecovery::PENDING)
            ->where(fn ($q) => $q->where('group_id', $transfer->recovery_id)->orWhere('id', $transfer->recovery_id))->first();
    }

    /**
     * TASK-0044: the heir accepted the recovery's offer in person (OwnershipTransfers::accept moved the owner binding): the recovery
     * is complete, and the previous owner's account leaves the organization (takeOutPreviousOwner).
     */
    public function completeByHeir(Organization $organization, OwnerRecovery $recovery, User $previous, CommandContext $context): void
    {
        $this->takeOutPreviousOwner($organization, $previous, $context);
        foreach (self::group($recovery) as $row) {
            $row->forceFill(['state' => OwnerRecovery::COMPLETED, 'completed_at' => now(), 'completed_by' => $context->onBehalfOfUserId ?? $context->actorId])->save();
            $this->audit->record($context->withScope($row->organization_id), 'organization.owner_recovery.complete', 'succeeded', ['recovery_id' => $row->id, 'group_id' => $row->group_id, 'mode' => $row->mode, 'accepted_by' => $row->new_owner_user_id], 'organization', $row->organization_id);
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.completed', 'organization', $row->organization_id, ['recovery_id' => $row->id, 'mode' => $row->mode, 'new_owner_user_id' => $row->new_owner_user_id], $row->organization_id));
        }
    }

    /** TASK-0044: the heir declined the recovery's offer — the recovery ends; support opens a new one naming somebody else. */
    public function closeDeclined(Organization $organization, OwnerRecovery $recovery, CommandContext $context): void
    {
        $this->close($organization, $recovery, (string) ($context->onBehalfOfUserId ?? $context->actorId), 'heir_declined', $context, withdrawOffer: false);
    }

    /** @return array<string,mixed> what a member of the organization and support see (the reason stays with support) */
    public static function present(OwnerRecovery $recovery, bool $staff = false): array
    {
        return ['id' => $recovery->id, 'state' => $recovery->state, 'phase' => $recovery->phase, 'mode' => $recovery->mode, 'new_owner_user_id' => $recovery->new_owner_user_id,
            'not_before' => $recovery->not_before->toIso8601String(), 'created_at' => $recovery->created_at?->toIso8601String(), 'contested_at' => $recovery->contested_at?->toIso8601String(),
            'cancelled_at' => $recovery->cancelled_at?->toIso8601String(), 'completed_at' => $recovery->completed_at?->toIso8601String()]
            + ($staff ? ['reason' => $recovery->reason, 'ticket_ref' => $recovery->ticket_ref, 'requested_by' => $recovery->requested_by, 'group_id' => $recovery->group_id,
                'cancel_reason' => $recovery->cancel_reason, 'review_evidence' => $recovery->review_evidence, 'reviewed_by' => $recovery->reviewed_by,
                'organization_ids' => array_map(fn (OwnerRecovery $row) => $row->organization_id, self::group($recovery, pendingOnly: false))] : []);
    }

    /**
     * TASK-0044 (re-review of `df1f6d3`, D21): the second person of an owner recovery — asked when they approve it
     * (ApprovalService::decide). assertNotParty kept a party out of opening and completing it, but the approval asked only "not
     * the requester" and "holds iam.mfa.reset": the heir, or a member of a reached organization who is also staff, approved the
     * recovery of an account into their own hands. Applies to opening a recovery and to continuing a contested one.
     */
    public static function assertApproverNotParty(Approval $approval, User $decider): void
    {
        $organization = Organization::query()->find((string) data_get($approval->payload, 'command.payload.organization_id', ''));
        if ($organization === null) {
            return;
        }
        if ($approval->action === 'identity.owner_recovery.continue') {
            $recovery = self::pending($organization);
            $owner = $recovery === null ? null : User::query()->find($recovery->owner_user_id);
            if ($recovery === null || $owner === null) {
                return;
            }
            $heirId = $recovery->new_owner_user_id;
            $reach = array_map(fn (OwnerRecovery $row) => $row->organization_id, self::group($recovery));
        } else {
            $owner = User::query()->find((string) $organization->owner_user_id);
            if ($owner === null) {
                return;
            }
            $mode = (string) data_get($approval->payload, 'command.payload.mode', 'mfa_reset');
            $heir = data_get($approval->payload, 'command.payload.new_owner_user_id');
            $heirId = $mode === 'transfer' && is_string($heir) && $heir !== '' ? $heir : null;
            $reach = self::reach($owner, $organization, $mode);
        }
        if (self::isParty($decider->id, $owner, $heirId, $reach)) {
            throw new DomainError('owner_recovery_party', 'You are a party of an organization this recovery reaches (its member, its owner or the heir); a colleague who is not approves it.', 403);
        }
    }

    /**
     * S1-07 red team (TASK-0042, D21): a transfer is the recovery of an owner who is gone for good — or whose account somebody
     * else now holds. transferOwnership keeps the previous owner as org_admin, which left that account managing the members with
     * its web sessions, its API tokens and a fresh step-up: whoever hijacked it stayed in. So the account goes: removed like any
     * member (a snapshot first, so the new owner — and only a role that covers the owner's change, i.e. the owner — can give it
     * back), its tokens of the organization revoked by the member listener, and its step-up grants ended now, everywhere (a
     * step-up is minutes long and says "this is the person", which is what is in doubt). Its other organizations are not this
     * recovery's to touch.
     *
     * TASK-0044 (D20, S1-08): its live channels end at once too, not when the outbox delivers the removal — every web session
     * (the account is the one in doubt), the console tickets it holds for this organization and the consoles it has open here
     * (SessionKill). Runs in the heir's acceptance, so the snapshot names the new owner as its taker.
     */
    private function takeOutPreviousOwner(Organization $organization, User $previous, CommandContext $context): void
    {
        app(OrganizationService::class)->removeMember($organization, $previous, $context, 'owner_recovery');
        app(SessionKill::class)->end($previous, 'owner_recovery', $organization->id, $context);
        app(Authorizer::class)->forget($previous);
    }

    /**
     * TASK-0044: the notice ran out — the heir is offered the ownership (an OwnershipTransfer from the recovery) and the recovery
     * waits for them in phase `offered`, the hold still on. Only while the heir is still a current member, not a guest and an active
     * account: the role was looked at when it was opened, and a week is long enough to be made a guest or leave.
     *
     * @param  list<OwnerRecovery>  $rows
     */
    private function offerToHeir(Organization $named, OwnerRecovery $recovery, array $rows, User $owner, CommandContext $context): OwnerRecovery
    {
        if (self::liveOffer($recovery) !== null) {
            throw DomainError::conflict('owner_recovery_offered', 'The heir was offered the ownership already; the recovery completes when they accept it.');
        }
        $heir = User::query()->find((string) $recovery->new_owner_user_id);
        $member = $heir === null ? null : OrganizationMembership::query()->where('organization_id', $named->id)->where('user_id', $heir->id)->current()->first();
        if ($heir === null || $member === null || $member->role_key === 'guest' || $heir->id === $owner->id || ! $heir->isActive()) {
            throw new DomainError('owner_recovery_stale', 'The heir this recovery names is no longer a member of the organization (or holds only shared services); cancel it and open a new one.', 409, ['field' => 'new_owner_user_id']);
        }
        $transfer = app(OwnershipTransfers::class)->offerFromRecovery($named, $heir, self::key($recovery), $context);
        foreach ($rows as $row) {
            $row->forceFill(['phase' => OwnerRecovery::OFFERED])->save();
            $this->audit->record($context->withScope($row->organization_id), 'organization.owner_recovery.offer', 'succeeded', ['recovery_id' => $row->id, 'transfer_id' => $transfer->id, 'heir' => $heir->id], 'organization', $row->organization_id);
        }

        return $recovery->refresh();
    }

    /** The pending, unexpired offer this recovery made, if any. */
    private static function liveOffer(OwnerRecovery $recovery): ?OwnershipTransfer
    {
        return OwnershipTransfer::query()->where('recovery_id', self::key($recovery))->where('state', OwnershipTransfer::PENDING)->where('expires_at', '>', now())->first();
    }

    private static function key(OwnerRecovery $recovery): string
    {
        return (string) ($recovery->group_id ?? $recovery->id);
    }

    /**
     * TASK-0044: the person being recovered objected to a transfer. Nothing closes: the recovery (and its hold) waits for staff, who
     * cancel it or — a second person approving, the evidence written down — continue it. Once reviewed, the same account's second
     * objection is refused: support decides it.
     */
    private function contest(Organization $organization, OwnerRecovery $recovery, string $by, CommandContext $context): OwnerRecovery
    {
        if ($recovery->phase === OwnerRecovery::CONTESTED) {
            throw DomainError::conflict('owner_recovery_contested', 'Your objection is with support already; a second person decides it with them.');
        }
        if ($recovery->reviewed_at !== null) {
            throw DomainError::conflict('owner_recovery_reviewed', 'Support reviewed this recovery with a second person after your objection; write to support if it is wrong.');
        }
        foreach (self::group($recovery) as $row) {
            $row->forceFill(['phase' => OwnerRecovery::CONTESTED, 'contested_at' => now(), 'contested_by' => $by])->save();
            $this->audit->record($context->withScope($row->organization_id), 'organization.owner_recovery.contest', 'succeeded', ['recovery_id' => $row->id, 'group_id' => $row->group_id, 'mode' => $row->mode], 'organization', $row->organization_id);
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.contested', 'organization', $row->organization_id, ['recovery_id' => $row->id, 'mode' => $row->mode, 'contested_by' => $by], $row->organization_id));
        }
        $this->alertOnRepeats($organization);

        return $recovery->refresh();
    }

    /** Every row of the recovery closed as cancelled, with who and why; a pending offer it made is withdrawn with it. */
    private function close(Organization $organization, OwnerRecovery $recovery, ?string $by, ?string $reason, CommandContext $context, bool $withdrawOffer = true): void
    {
        foreach (self::group($recovery) as $row) {
            $row->forceFill(['state' => OwnerRecovery::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $by, 'cancel_reason' => $reason])->save();
            $this->audit->record($context->withScope($row->organization_id), 'organization.owner_recovery.cancel', 'succeeded', ['recovery_id' => $row->id, 'group_id' => $row->group_id, 'cancelled_in' => $organization->id, 'reason' => $reason], 'organization', $row->organization_id);
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.cancelled', 'organization', $row->organization_id, ['recovery_id' => $row->id, 'mode' => $row->mode, 'cancelled_by' => $row->cancelled_by, 'reason' => $reason], $row->organization_id));
        }
        if ($withdrawOffer && ($offer = self::liveOffer($recovery)) !== null) {
            app(OwnershipTransfers::class)->withdrawRecoveryOffer($offer, $context);
        }
    }

    /**
     * TASK-0044: the second stop of an owner recovery in one organization within the window (cancelled, or objected to by the
     * person recovered) tells staff at once — whoever is stopping every attempt may be the one holding the account.
     */
    private function alertOnRepeats(Organization $organization, bool $firstStopAlerts = false): void
    {
        $days = max(1, (int) config('onhost.grants.owner_recovery_cancel_window_days', 30));
        $since = now()->subDays($days);
        $stopped = OwnerRecovery::query()->where('organization_id', $organization->id)
            ->where(fn ($q) => $q->where('cancelled_at', '>=', $since)->orWhere('contested_at', '>=', $since))->count();
        if ($stopped >= ($firstStopAlerts ? 1 : max(2, (int) config('onhost.grants.owner_recovery_cancel_alert', 2)))) {
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.cancels_repeated', 'organization', $organization->id, ['count' => $stopped, 'window_days' => $days], $organization->id));
        }
    }

    /** @return list<OwnerRecovery> the rows of one recovery, one per organization it reaches, the named organization's first */
    private static function group(OwnerRecovery $recovery, bool $pendingOnly = true): array
    {
        if ($recovery->group_id === null) {
            return [$recovery];
        }

        return OwnerRecovery::query()->where('group_id', $recovery->group_id)->when($pendingOnly, fn ($q) => $q->where('state', OwnerRecovery::PENDING))->get()
            ->sortBy(fn (OwnerRecovery $row) => $row->id === $row->group_id ? 0 : 1)->values()->all();
    }

    /**
     * Support who are a party of an organization the recovery reaches — its member (a binding of their own there, P0-16's test),
     * the owner being recovered, the heir — neither open nor complete it: they would be recovering an account into their own hands.
     *
     * @param  list<string>  $organizationIds
     */
    private static function assertNotParty(CommandContext $context, User $owner, ?string $heirId, array $organizationIds): void
    {
        if ($context->actorType !== 'user') {
            return;
        }
        if (self::isParty((string) ($context->onBehalfOfUserId ?? $context->actorId), $owner, $heirId, $organizationIds)) {
            throw new DomainError('owner_recovery_party', 'You are a party of an organization this recovery reaches (its member, its owner or the heir); a colleague who is not opens or completes it.', 403);
        }
    }

    /**
     * TASK-0044: whoever decided the approvals a step carries is no party either (asked again at the step: a colleague who approved
     * and joined the organization, or became the heir, before the repeat).
     *
     * @param  list<string>  $approvalIds
     * @param  list<string>  $organizationIds
     */
    private static function assertApproversNotParty(array $approvalIds, User $owner, ?string $heirId, array $organizationIds): void
    {
        if ($approvalIds === []) {
            return;
        }
        foreach (Approval::query()->whereIn('id', $approvalIds)->whereNotNull('decided_by')->pluck('decided_by') as $decider) {
            if (self::isParty((string) $decider, $owner, $heirId, $organizationIds)) {
                throw new DomainError('owner_recovery_party', 'The second person who approved this is a party of an organization it reaches (its member, its owner or the heir); a colleague who is not approves it again.', 403);
            }
        }
    }

    /** @param list<string> $organizationIds */
    private static function isParty(string $personId, User $owner, ?string $heirId, array $organizationIds): bool
    {
        $person = User::query()->find($personId);

        return $personId === $owner->id || ($heirId !== null && $personId === $heirId)
            || OrganizationMembership::query()->where('user_id', $personId)->whereIn('organization_id', $organizationIds)->exists()
            || ($person !== null && collect($organizationIds)->contains(fn (string $id) => app(Authorizer::class)->belongsTo($person, $id)));
    }
}
