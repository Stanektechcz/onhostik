<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Onhost\Domain\Identity\Commands\MfaResetCommandHandler;
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
 *    ownership to a named current member (mode transfer — the owner is gone for good);
 *  · `iam.mfa.reset` of a customer owner outside it is refused (MfaResetCommandHandler).
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

    public function open(Organization $organization, string $mode, ?string $newOwnerId, string $reason, string $ticketRef, CommandContext $context): OwnerRecovery
    {
        if (! in_array($mode, OwnerRecovery::MODES, true)) {
            throw new DomainError('owner_recovery_mode_invalid', 'mode must be mfa_reset or transfer.', 422, ['field' => 'mode']);
        }
        if (mb_strlen(trim($reason)) < 10 || trim($ticketRef) === '') {
            throw new DomainError('owner_recovery_basis_required', 'Say why (at least 10 characters) and name the ticket the owner\'s identity was checked in.', 422, ['field' => 'reason']);
        }
        if (self::pending($organization) !== null) {
            throw DomainError::conflict('owner_recovery_pending', 'An owner recovery is already under way in this organization.');
        }
        $owner = User::query()->find((string) $organization->owner_user_id) ?? throw DomainError::notFound('owner');
        if ($mode === 'transfer') {
            $heir = $newOwnerId === null ? null : User::query()->find($newOwnerId);
            $member = $heir === null ? null : OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $heir->id)->current()->first();
            if ($member === null || $heir?->id === $owner->id || $member->role_key === 'guest') {
                throw DomainError::notFound('member'); // the new owner is somebody already in the organization, never a stranger (I8)
            }
        }
        $recovery = OwnerRecovery::query()->create([
            'organization_id' => $organization->id, 'owner_user_id' => $owner->id, 'mode' => $mode, 'new_owner_user_id' => $mode === 'transfer' ? $newOwnerId : null,
            'state' => OwnerRecovery::PENDING, 'reason' => mb_substr(trim($reason), 0, 2000), 'ticket_ref' => mb_substr(trim($ticketRef), 0, 60),
            'requested_by' => $context->actorId, 'approval_ids' => $context->verifiedApprovalIds, 'not_before' => now()->addDays(self::delayDays()),
        ]);
        $this->audit->record($context->withScope($organization->id), 'organization.owner_recovery.open', 'succeeded', ['recovery_id' => $recovery->id, 'mode' => $mode, 'ticket_ref' => $recovery->ticket_ref, 'not_before' => $recovery->not_before->toIso8601String()], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('organization.owner_recovery.opened', 'organization', $organization->id, [
            'recovery_id' => $recovery->id, 'mode' => $mode, 'owner_user_id' => $owner->id, 'new_owner_user_id' => $recovery->new_owner_user_id, 'not_before' => $recovery->not_before->toIso8601String(), 'ticket_ref' => $recovery->ticket_ref,
        ], $organization->id));

        return $recovery;
    }

    /** Anybody who may manage the members (an org_admin, the owner) or support stops it while it waits. */
    public function cancel(Organization $organization, CommandContext $context): OwnerRecovery
    {
        $recovery = self::pending($organization) ?? throw new DomainError('owner_recovery_not_pending', 'No owner recovery is under way in this organization.', 409);
        $recovery->forceFill(['state' => OwnerRecovery::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $context->onBehalfOfUserId ?? $context->actorId])->save();
        $this->audit->record($context->withScope($organization->id), 'organization.owner_recovery.cancel', 'succeeded', ['recovery_id' => $recovery->id], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('organization.owner_recovery.cancelled', 'organization', $organization->id, ['recovery_id' => $recovery->id, 'mode' => $recovery->mode, 'cancelled_by' => $recovery->cancelled_by], $organization->id));

        return $recovery;
    }

    public function complete(Organization $organization, CommandContext $context): OwnerRecovery
    {
        $recovery = self::pending($organization) ?? throw new DomainError('owner_recovery_not_pending', 'No owner recovery is under way in this organization.', 409);
        if ($recovery->not_before->isFuture()) {
            throw new DomainError('owner_recovery_locked', 'The notice period of this owner recovery has not passed yet.', 409, ['not_before' => $recovery->not_before->toIso8601String()]);
        }
        if ((string) $organization->owner_user_id !== $recovery->owner_user_id) {
            throw new DomainError('owner_recovery_stale', 'The organization has another owner since this recovery was opened; cancel it.', 409);
        }
        $owner = User::query()->find($recovery->owner_user_id) ?? throw DomainError::notFound('owner');
        if ($recovery->mode === 'mfa_reset') {
            app(MfaResetCommandHandler::class)->reset($owner, $context, 'owner recovery '.$recovery->id);
        } else {
            $heir = User::query()->find((string) $recovery->new_owner_user_id) ?? throw DomainError::notFound('member');
            app(GrantPolicy::class)->assertMayTransferOwnership($organization, CommandContext::system('owner recovery '.$recovery->id), $heir); // still a current member (I8)
            app(OrganizationService::class)->transferOwnership($organization, $heir, $context);
        }
        $recovery->forceFill(['state' => OwnerRecovery::COMPLETED, 'completed_at' => now(), 'completed_by' => $context->actorId])->save();
        $this->audit->record($context->withScope($organization->id), 'organization.owner_recovery.complete', 'succeeded', ['recovery_id' => $recovery->id, 'mode' => $recovery->mode], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('organization.owner_recovery.completed', 'organization', $organization->id, ['recovery_id' => $recovery->id, 'mode' => $recovery->mode, 'new_owner_user_id' => $recovery->new_owner_user_id], $organization->id));

        return $recovery;
    }

    /** @return array<string,mixed> what a member of the organization and support see (the reason stays with support) */
    public static function present(OwnerRecovery $recovery, bool $staff = false): array
    {
        return ['id' => $recovery->id, 'state' => $recovery->state, 'mode' => $recovery->mode, 'new_owner_user_id' => $recovery->new_owner_user_id,
            'not_before' => $recovery->not_before->toIso8601String(), 'created_at' => $recovery->created_at?->toIso8601String(),
            'cancelled_at' => $recovery->cancelled_at?->toIso8601String(), 'completed_at' => $recovery->completed_at?->toIso8601String()]
            + ($staff ? ['reason' => $recovery->reason, 'ticket_ref' => $recovery->ticket_ref, 'requested_by' => $recovery->requested_by] : []);
    }
}
