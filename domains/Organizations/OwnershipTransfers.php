<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OwnerRecovery;
use Onhost\Domain\Organizations\Models\OwnershipTransfer;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Ownership moves in two steps (permission program I4, S1-02; audit TD-9): the owner offers it to a current member, the member
 * accepts it — in person, with a fresh step-up — and only then does the owner binding move (OrganizationService::transferOwnership,
 * which snapshots both people first). The owner may cancel the offer, the heir may decline it, and it lapses after
 * `onhost.grants.ownership_offer_days` (7). Before, one request made somebody the owner of a company — its contracts, its money,
 * its data — without a word to them. One offer at a time: a new one replaces the one before. Both people are told (events).
 *
 * TASK-0044 (D21, S1-07 red team): an owner recovery of mode transfer ends in an offer too (offerFromRecovery, `recovery_id`) — the
 * heir accepts it in person with a fresh step-up like any other, the owner being recovered cannot withdraw it (support stops the
 * recovery), and accepting it completes the recovery (OwnerRecoveries::completeByHeir). A guest is offered nothing (GrantPolicy).
 */
final class OwnershipTransfers
{
    public function __construct(
        private readonly GrantPolicy $policy,
        private readonly OrganizationService $organizations,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    public function offer(Organization $organization, User $heir, CommandContext $context): OwnershipTransfer
    {
        OwnerRecoveries::assertNoHold($organization, 'ownership');
        $this->policy->assertMayTransferOwnership($organization, $context, $heir);
        foreach (OwnershipTransfer::query()->where('organization_id', $organization->id)->where('state', OwnershipTransfer::PENDING)->get() as $earlier) {
            $this->close($organization, $earlier, OwnershipTransfer::CANCELLED, $context, 'superseded');
        }
        try {
            // one pending offer per organization, kept by the database (review round 1: two requests could both supersede the old
            // offer and each leave a new one); in a savepoint so PostgreSQL's refusal does not abort the bus transaction (25P02)
            $transfer = DB::transaction(fn () => OwnershipTransfer::query()->create([
                'organization_id' => $organization->id, 'from_user_id' => (string) $organization->owner_user_id, 'to_user_id' => $heir->id,
                'state' => OwnershipTransfer::PENDING, 'expires_at' => now()->addDays(max(1, (int) config('onhost.grants.ownership_offer_days', 7))),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw DomainError::conflict('ownership_offer_pending', 'An ownership offer was made in this organization a moment ago; cancel it first.');
        }
        $this->audit->record($context->withScope($organization->id), 'organization.ownership.offer', 'succeeded', ['transfer_id' => $transfer->id, 'to' => $heir->id], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('organization.ownership.offered', 'organization', $organization->id, [
            'transfer_id' => $transfer->id, 'from_user_id' => $transfer->from_user_id, 'to_user_id' => $heir->id, 'email' => mb_strtolower((string) $heir->email), 'name' => $heir->name,
            'expires_at' => $transfer->expires_at->toIso8601String(),
        ], $organization->id));

        return $transfer;
    }

    public function accept(Organization $organization, CommandContext $context): OwnershipTransfer
    {
        $transfer = $this->pending($organization);
        $recovery = OwnerRecoveries::ofOffer($transfer);
        if ($transfer->recovery_id === null) {
            OwnerRecoveries::assertNoHold($organization, 'ownership');
        } elseif ($recovery === null) { // the recovery that made it was stopped: its offer went with it
            throw new DomainError('ownership_offer_invalid', 'There is no ownership offer waiting in this organization.', 409);
        } elseif ($recovery->phase === OwnerRecovery::CONTESTED) {
            throw DomainError::conflict('owner_recovery_contested', 'The owner objected to this recovery; support reviews it before the ownership can be accepted.');
        }
        $heir = $this->policy->assertMayAcceptOwnership($organization, $context, $transfer);
        $previous = User::query()->find($transfer->from_user_id);
        $this->organizations->transferOwnership($organization, $heir, $context);
        $transfer->forceFill(['state' => OwnershipTransfer::ACCEPTED, 'decided_at' => now(), 'decided_by' => $heir->id])->save();
        if ($recovery !== null && $previous !== null) {
            app(OwnerRecoveries::class)->completeByHeir($organization->refresh(), $recovery, $previous, $context);
        }
        $this->audit->record($context->withScope($organization->id), 'organization.ownership.accept', 'succeeded', ['transfer_id' => $transfer->id, 'from' => $transfer->from_user_id], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('organization.ownership.accepted', 'organization', $organization->id, [
            'transfer_id' => $transfer->id, 'from_user_id' => $transfer->from_user_id, 'to_user_id' => $heir->id, 'name' => $heir->name,
            'previous_email' => mb_strtolower((string) ($previous->email ?? '')),
        ] + ($recovery !== null ? ['via' => 'owner_recovery'] : []), $organization->id));

        return $transfer;
    }

    /** The heir says no. */
    public function decline(Organization $organization, CommandContext $context): OwnershipTransfer
    {
        $transfer = $this->pending($organization);
        $person = (string) ($context->onBehalfOfUserId ?? $context->actorId);
        if ($context->actorType !== 'user' || $person !== $transfer->to_user_id) {
            throw new DomainError('owner_transfer_heir_only', 'Only the member the ownership was offered to declines it.', 403);
        }

        $recovery = OwnerRecoveries::ofOffer($transfer);
        $this->close($organization, $transfer, OwnershipTransfer::DECLINED, $context, 'declined');
        if ($recovery !== null) { // TASK-0044: the heir said no — the recovery that named them ends; support opens a new one
            app(OwnerRecoveries::class)->closeDeclined($organization, $recovery, $context);
        }

        return $transfer;
    }

    /** The owner withdraws the offer. */
    public function cancel(Organization $organization, CommandContext $context): OwnershipTransfer
    {
        $transfer = $this->pending($organization);
        $person = (string) ($context->onBehalfOfUserId ?? $context->actorId);
        if ($context->actorType !== 'system' && ($context->actorType !== 'user' || $person !== (string) $organization->owner_user_id || (string) $context->actorId !== $person)) {
            throw new DomainError('owner_transfer_only', 'Only the owner of the organization withdraws an ownership offer.', 403);
        }
        if ($transfer->recovery_id !== null && $context->actorType !== 'system') { // TASK-0044: the owner being recovered may be the one who lost the account
            throw new DomainError('owner_transfer_recovery', 'This offer was made by an owner recovery; support withdraws it.', 403);
        }

        return $this->close($organization, $transfer, OwnershipTransfer::CANCELLED, $context, 'cancelled');
    }

    /**
     * TASK-0044 (D21): the offer an owner recovery of mode transfer ends in, made by the platform in the recovery's name (support
     * cannot make the heir the owner; the heir accepts it in person). I8 and the guest rule are the same as for an owner's offer;
     * an owner's own pending offer is superseded. `$recoveryId` is the recovery's group id.
     */
    public function offerFromRecovery(Organization $organization, User $heir, string $recoveryId, CommandContext $context): OwnershipTransfer
    {
        $this->policy->assertMayTransferOwnership($organization, CommandContext::system('owner recovery '.$recoveryId), $heir);
        foreach (OwnershipTransfer::query()->where('organization_id', $organization->id)->where('state', OwnershipTransfer::PENDING)->get() as $earlier) {
            $this->close($organization, $earlier, OwnershipTransfer::CANCELLED, $context, 'superseded');
        }
        try {
            $transfer = DB::transaction(fn () => OwnershipTransfer::query()->create([ // a savepoint, as in offer() (25P02)
                'organization_id' => $organization->id, 'from_user_id' => (string) $organization->owner_user_id, 'to_user_id' => $heir->id, 'recovery_id' => $recoveryId,
                'state' => OwnershipTransfer::PENDING, 'expires_at' => now()->addDays(max(1, (int) config('onhost.grants.ownership_offer_days', 7))),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw DomainError::conflict('ownership_offer_pending', 'An ownership offer was made in this organization a moment ago; cancel it first.');
        }
        $this->audit->record($context->withScope($organization->id), 'organization.ownership.offer', 'succeeded', ['transfer_id' => $transfer->id, 'to' => $heir->id, 'recovery_id' => $recoveryId], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('organization.ownership.offered', 'organization', $organization->id, [
            'transfer_id' => $transfer->id, 'from_user_id' => $transfer->from_user_id, 'to_user_id' => $heir->id, 'email' => mb_strtolower((string) $heir->email), 'name' => $heir->name,
            'expires_at' => $transfer->expires_at->toIso8601String(), 'via' => 'owner_recovery', 'recovery_id' => $recoveryId,
        ], $organization->id));

        return $transfer;
    }

    /** TASK-0044: the recovery that made this offer was stopped — the offer goes with it. */
    public function withdrawRecoveryOffer(OwnershipTransfer $transfer, CommandContext $context): void
    {
        $organization = Organization::query()->find($transfer->organization_id);
        if ($organization !== null && $transfer->state === OwnershipTransfer::PENDING) {
            $this->close($organization, $transfer, OwnershipTransfer::CANCELLED, $context, 'owner_recovery_cancelled');
        }
    }

    public function current(Organization $organization): ?OwnershipTransfer
    {
        return OwnershipTransfer::query()->where('organization_id', $organization->id)->where('state', OwnershipTransfer::PENDING)->where('expires_at', '>', now())->latest('created_at')->first();
    }

    /** @return array<string,mixed> */
    public static function present(OwnershipTransfer $transfer): array
    {
        return ['id' => $transfer->id, 'state' => $transfer->state, 'from_user_id' => $transfer->from_user_id, 'to_user_id' => $transfer->to_user_id, 'recovery_id' => $transfer->recovery_id,
            'expires_at' => $transfer->expires_at->toIso8601String(), 'decided_at' => $transfer->decided_at?->toIso8601String(), 'created_at' => $transfer->created_at?->toIso8601String()];
    }

    /** The pending, unexpired offer; one that lapsed is closed on the way (it never needed a job to stop counting). */
    private function pending(Organization $organization): OwnershipTransfer
    {
        $transfer = OwnershipTransfer::query()->where('organization_id', $organization->id)->where('state', OwnershipTransfer::PENDING)->latest('created_at')->first();
        if ($transfer !== null && $transfer->expires_at->isPast()) {
            $transfer->forceFill(['state' => OwnershipTransfer::EXPIRED, 'decided_at' => now()])->save();
            $transfer = null;
        }
        if ($transfer === null) {
            throw new DomainError('ownership_offer_invalid', 'There is no ownership offer waiting in this organization.', 409);
        }

        return $transfer;
    }

    private function close(Organization $organization, OwnershipTransfer $transfer, string $state, CommandContext $context, string $why): OwnershipTransfer
    {
        $transfer->forceFill(['state' => $state, 'decided_at' => now(), 'decided_by' => $context->actorType === 'system' ? null : ($context->onBehalfOfUserId ?? $context->actorId)])->save();
        $this->audit->record($context->withScope($organization->id), 'organization.ownership.'.($state === OwnershipTransfer::DECLINED ? 'decline' : 'cancel'), 'succeeded', ['transfer_id' => $transfer->id, 'why' => $why], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of($state === OwnershipTransfer::DECLINED ? 'organization.ownership.declined' : 'organization.ownership.cancelled', 'organization', $organization->id, [
            'transfer_id' => $transfer->id, 'from_user_id' => $transfer->from_user_id, 'to_user_id' => $transfer->to_user_id, 'why' => $why,
        ], $organization->id));

        return $transfer;
    }
}
