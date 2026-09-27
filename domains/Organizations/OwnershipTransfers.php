<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
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
        $transfer = OwnershipTransfer::query()->create([
            'organization_id' => $organization->id, 'from_user_id' => (string) $organization->owner_user_id, 'to_user_id' => $heir->id,
            'state' => OwnershipTransfer::PENDING, 'expires_at' => now()->addDays(max(1, (int) config('onhost.grants.ownership_offer_days', 7))),
        ]);
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
        OwnerRecoveries::assertNoHold($organization, 'ownership');
        $heir = $this->policy->assertMayAcceptOwnership($organization, $context, $transfer);
        $previous = User::query()->find($transfer->from_user_id);
        $this->organizations->transferOwnership($organization, $heir, $context);
        $transfer->forceFill(['state' => OwnershipTransfer::ACCEPTED, 'decided_at' => now(), 'decided_by' => $heir->id])->save();
        $this->audit->record($context->withScope($organization->id), 'organization.ownership.accept', 'succeeded', ['transfer_id' => $transfer->id, 'from' => $transfer->from_user_id], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('organization.ownership.accepted', 'organization', $organization->id, [
            'transfer_id' => $transfer->id, 'from_user_id' => $transfer->from_user_id, 'to_user_id' => $heir->id, 'name' => $heir->name,
            'previous_email' => mb_strtolower((string) ($previous->email ?? '')),
        ], $organization->id));

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

        return $this->close($organization, $transfer, OwnershipTransfer::DECLINED, $context, 'declined');
    }

    /** The owner withdraws the offer. */
    public function cancel(Organization $organization, CommandContext $context): OwnershipTransfer
    {
        $transfer = $this->pending($organization);
        $person = (string) ($context->onBehalfOfUserId ?? $context->actorId);
        if ($context->actorType !== 'system' && ($context->actorType !== 'user' || $person !== (string) $organization->owner_user_id || (string) $context->actorId !== $person)) {
            throw new DomainError('owner_transfer_only', 'Only the owner of the organization withdraws an ownership offer.', 403);
        }

        return $this->close($organization, $transfer, OwnershipTransfer::CANCELLED, $context, 'cancelled');
    }

    public function current(Organization $organization): ?OwnershipTransfer
    {
        return OwnershipTransfer::query()->where('organization_id', $organization->id)->where('state', OwnershipTransfer::PENDING)->where('expires_at', '>', now())->latest('created_at')->first();
    }

    /** @return array<string,mixed> */
    public static function present(OwnershipTransfer $transfer): array
    {
        return ['id' => $transfer->id, 'state' => $transfer->state, 'from_user_id' => $transfer->from_user_id, 'to_user_id' => $transfer->to_user_id,
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
