<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Commands\MfaResetCommandHandler;
use Onhost\Domain\Identity\Models\StepUpGrant;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\OwnerRecovery;
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

    public function open(Organization $organization, string $mode, ?string $newOwnerId, string $reason, string $ticketRef, CommandContext $context): OwnerRecovery
    {
        if (! in_array($mode, OwnerRecovery::MODES, true)) {
            throw new DomainError('owner_recovery_mode_invalid', 'mode must be mfa_reset or transfer.', 422, ['field' => 'mode']);
        }
        if (mb_strlen(trim($reason)) < 10 || trim($ticketRef) === '') {
            throw new DomainError('owner_recovery_basis_required', 'Say why (at least 10 characters) and name the ticket the owner\'s identity was checked in.', 422, ['field' => 'reason']);
        }
        $owner = User::query()->find((string) $organization->owner_user_id) ?? throw DomainError::notFound('owner');
        $reach = self::reach($owner, $organization, $mode);
        self::assertNotParty($context, $owner, $mode === 'transfer' ? $newOwnerId : null, $reach);
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
                'organization_id' => $organizationId, 'owner_user_id' => $owner->id, 'mode' => $mode, 'new_owner_user_id' => $mode === 'transfer' ? $newOwnerId : null,
                'state' => OwnerRecovery::PENDING, 'reason' => mb_substr(trim($reason), 0, 2000), 'ticket_ref' => mb_substr(trim($ticketRef), 0, 60),
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

    /** Anybody who may manage the members (an org_admin, the owner) of ANY organization it reaches, or support, stops it while it waits. */
    public function cancel(Organization $organization, CommandContext $context): OwnerRecovery
    {
        $recovery = self::pending($organization) ?? throw new DomainError('owner_recovery_not_pending', 'No owner recovery is under way in this organization.', 409);
        $by = $context->onBehalfOfUserId ?? $context->actorId;
        foreach (self::group($recovery) as $row) {
            $row->forceFill(['state' => OwnerRecovery::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $by])->save();
            $this->audit->record($context->withScope($row->organization_id), 'organization.owner_recovery.cancel', 'succeeded', ['recovery_id' => $row->id, 'group_id' => $row->group_id, 'cancelled_in' => $organization->id], 'organization', $row->organization_id);
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.cancelled', 'organization', $row->organization_id, ['recovery_id' => $row->id, 'mode' => $row->mode, 'cancelled_by' => $row->cancelled_by], $row->organization_id));
        }

        return $recovery->refresh();
    }

    public function complete(Organization $organization, CommandContext $context): OwnerRecovery
    {
        $recovery = self::pending($organization) ?? throw new DomainError('owner_recovery_not_pending', 'No owner recovery is under way in this organization.', 409);
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
        if ($recovery->mode === 'mfa_reset') {
            app(MfaResetCommandHandler::class)->reset($owner, $context, 'owner recovery '.$recovery->id, $covered);
        } else {
            $heir = User::query()->find((string) $recovery->new_owner_user_id) ?? throw DomainError::notFound('member');
            app(GrantPolicy::class)->assertMayTransferOwnership($named, CommandContext::system('owner recovery '.$recovery->id), $heir); // still a current member (I8)
            app(OrganizationService::class)->transferOwnership($named, $heir, $context);
            $this->takeOutPreviousOwner($named->refresh(), $owner, $context);
        }
        foreach ($rows as $row) {
            $row->forceFill(['state' => OwnerRecovery::COMPLETED, 'completed_at' => now(), 'completed_by' => $context->actorId])->save();
            $this->audit->record($context->withScope($row->organization_id), 'organization.owner_recovery.complete', 'succeeded', ['recovery_id' => $row->id, 'group_id' => $row->group_id, 'mode' => $row->mode], 'organization', $row->organization_id);
            $this->outbox->publish(GenericEvent::of('organization.owner_recovery.completed', 'organization', $row->organization_id, ['recovery_id' => $row->id, 'mode' => $row->mode, 'new_owner_user_id' => $row->new_owner_user_id], $row->organization_id));
        }

        return $recovery->refresh();
    }

    /** @return array<string,mixed> what a member of the organization and support see (the reason stays with support) */
    public static function present(OwnerRecovery $recovery, bool $staff = false): array
    {
        return ['id' => $recovery->id, 'state' => $recovery->state, 'mode' => $recovery->mode, 'new_owner_user_id' => $recovery->new_owner_user_id,
            'not_before' => $recovery->not_before->toIso8601String(), 'created_at' => $recovery->created_at?->toIso8601String(),
            'cancelled_at' => $recovery->cancelled_at?->toIso8601String(), 'completed_at' => $recovery->completed_at?->toIso8601String()]
            + ($staff ? ['reason' => $recovery->reason, 'ticket_ref' => $recovery->ticket_ref, 'requested_by' => $recovery->requested_by, 'group_id' => $recovery->group_id,
                'organization_ids' => array_map(fn (OwnerRecovery $row) => $row->organization_id, self::group($recovery, pendingOnly: false))] : []);
    }

    /**
     * S1-07 red team (TASK-0042, D21): a transfer is the recovery of an owner who is gone for good — or whose account somebody
     * else now holds. transferOwnership keeps the previous owner as org_admin, which left that account managing the members with
     * its web sessions, its API tokens and a fresh step-up: whoever hijacked it stayed in. So the account goes: removed like any
     * member (a snapshot first, so the new owner — and only a role that covers support's change, i.e. the owner — can give it
     * back), its tokens of the organization revoked by the member listener, and its step-up grants ended now, everywhere (a
     * step-up is minutes long and says "this is the person", which is what is in doubt). Its web sessions reach nothing here once
     * the membership is gone; its other organizations are not this recovery's to touch.
     */
    private function takeOutPreviousOwner(Organization $organization, User $previous, CommandContext $context): void
    {
        app(OrganizationService::class)->removeMember($organization, $previous, $context, 'owner_recovery');
        StepUpGrant::query()->where('user_id', $previous->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        app(Authorizer::class)->forget($previous);
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
        $actorId = (string) ($context->onBehalfOfUserId ?? $context->actorId);
        $actor = User::query()->find($actorId);
        $party = $actorId === $owner->id || ($heirId !== null && $actorId === $heirId)
            || OrganizationMembership::query()->where('user_id', $actorId)->whereIn('organization_id', $organizationIds)->exists()
            || ($actor !== null && collect($organizationIds)->contains(fn (string $id) => app(Authorizer::class)->belongsTo($actor, $id)));
        if ($party) {
            throw new DomainError('owner_recovery_party', 'You are a party of an organization this recovery reaches (its member, its owner or the heir); a colleague who is not opens or completes it.', 403);
        }
    }
}
