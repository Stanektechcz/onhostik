<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * The decision on a credit order somebody placed who may not spend the credit (owner decision 20, TASK-0021). The order waits
 * in NEW with `meta.approval.state = pending` — nothing reserved, no document, nothing provisioned. The owner or a billing admin
 * approves it (the credit is reserved and the order paid exactly as if they had placed it: the risk hold, the document and the
 * fulfilment follow as usual) or rejects it (the order is cancelled, nothing was charged, the promo use comes back). An order
 * nobody decided is cancelled by the nightly commerce housekeeping after `onhost.orders.credit_approval.expire_days`.
 *
 * This is the customer's own approval inside the organization, not the staff four-eyes (Identity\Authorization\ApprovalService).
 */
final class CreditOrderApprovals
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CreditOrderPolicy $policy,
        private readonly WalletService $wallets,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** @return array<string,mixed> what an order that waits remembers about who placed it */
    public static function opened(CommandContext $context): array
    {
        $requester = $context->onBehalfOfUserId ?? $context->actorId;
        $name = $requester !== null && in_array($context->actorType, ['user', 'ai'], true) ? User::query()->whereKey($requester)->value('name') : null;

        return array_filter([
            'state' => 'pending', 'requester_id' => $requester, 'requester_type' => $context->actorType, 'requester_name' => is_string($name) ? $name : null,
            'project_id' => $context->projectId, // the budget of the requester's project is asked again when the order is approved
            'opened_at' => now()->toIso8601String(),
        ], fn ($v) => $v !== null);
    }

    /** @return array<string,mixed> the order's approval record, empty when it never waited for one */
    public static function of(Order $order): array
    {
        $approval = data_get($order->meta, 'approval');

        return is_array($approval) ? $approval : [];
    }

    public static function isPending(Order $order): bool
    {
        return $order->state === OrderStateMachine::NEW && ((self::of($order)['state'] ?? null) === 'pending');
    }

    public function decide(Order $order, User $decider, string $decision, ?string $reason, CommandContext $context): Order
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new DomainError('order_approval_decision_invalid', 'Decision must be approve or reject.', 422, ['field' => 'decision']);
        }
        $reason = $reason !== null ? mb_substr(trim($reason), 0, 250) : null;
        if ($decision === 'reject' && ($reason === null || $reason === '')) {
            throw new DomainError('reason_required', 'Uveďte důvod zamítnutí; dostane ho člen, který objednávku zadal.', 422, ['field' => 'reason']);
        }

        return DB::transaction(function () use ($order, $decider, $decision, $reason, $context) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! self::isPending($order)) {
                throw new DomainError('order_not_awaiting_approval', 'The order is not waiting for an approval.', 409, ['state' => $order->state, 'approval' => self::of($order)['state'] ?? null]);
            }
            $organization = Organization::query()->findOrFail($order->organization_id);
            if (! $this->policy->mayApprove($decider, $organization)) {
                throw new DomainError('approver_lacks_permission', 'Objednávku placenou z kreditu schvaluje vlastník organizace nebo fakturační správce.', 403, ['permission' => CreditOrderPolicy::PERMISSION]);
            }
            $approval = self::of($order);
            $scoped = $context->withScope($order->organization_id, isset($approval['project_id']) ? (string) $approval['project_id'] : null);

            return $decision === 'approve' ? $this->approve($order, $approval, $decider, $scoped) : $this->reject($order, $approval, $decider, (string) $reason, $scoped);
        }, 3);
    }

    /**
     * Cancels the orders nobody decided within `$days`; returns how many. Unapproved orders are unpaid by definition, so the
     * nightly `onhost:commerce:prune` runs this with the unpaid-order expiry (CommerceHousekeeping::expireUnpaid).
     */
    public function expirePending(?int $days = null): int
    {
        $days = max(1, $days ?? (int) config('onhost.orders.credit_approval.expire_days', 7));
        $expired = 0;
        $due = Order::query()->where('state', OrderStateMachine::NEW)->where('meta->approval->state', 'pending')->where('placed_at', '<', now()->subDays($days))->orderBy('placed_at')->limit(500)->get();
        foreach ($due as $order) {
            try {
                $context = CommandContext::system('credit order approval expiry')->withScope($order->organization_id);
                $approval = array_merge(self::of($order), ['state' => 'expired', 'decided_at' => now()->toIso8601String()]);
                $order->forceFill(['meta' => array_merge((array) $order->meta, ['approval' => $approval])])->save();
                $this->checkout->transition($order, OrderStateMachine::CANCELLED, $context, "neschváleno do {$days} dnů");
                $this->outbox->publish(GenericEvent::of('order.approval.expired', 'order', $order->id, ['number' => $order->number, 'requester_id' => $approval['requester_id'] ?? null, 'days' => $days], $order->organization_id));
                $expired++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $expired;
    }

    /** @param array<string,mixed> $approval */
    private function approve(Order $order, array $approval, User $decider, CommandContext $context): Order
    {
        if ($order->payment_mode === 'postpaid' && $this->wallets->approvedCreditLine($order->organization_id, $order->currency)->isZero()) {
            throw new DomainError('postpaid_not_approved', 'Postpaid billing requires an approved credit line.', 403);
        }
        $approval = array_merge($approval, ['state' => 'approved', 'decided_by' => $decider->id, 'decider_name' => $decider->name, 'decided_at' => now()->toIso8601String()]);
        $order->forceFill(['meta' => array_merge((array) $order->meta, ['approval' => $approval])])->save();
        $this->audit->record($context, 'order.approval.decide', 'succeeded', ['number' => $order->number, 'decision' => 'approve', 'total' => $order->total()], 'order', $order->id);
        $this->outbox->publish(GenericEvent::of('order.approval.approved', 'order', $order->id, ['number' => $order->number, 'decider' => $decider->name, 'requester_id' => $approval['requester_id'] ?? null], $order->organization_id));

        // an insufficient credit or an exceeded budget throws here and rolls the whole decision back: the order keeps waiting
        return $this->checkout->payFromCredit($order, $context);
    }

    /** @param array<string,mixed> $approval */
    private function reject(Order $order, array $approval, User $decider, string $reason, CommandContext $context): Order
    {
        $approval = array_merge($approval, ['state' => 'rejected', 'decided_by' => $decider->id, 'decider_name' => $decider->name, 'decided_at' => now()->toIso8601String(), 'reason' => $reason]);
        $order->forceFill(['meta' => array_merge((array) $order->meta, ['approval' => $approval])])->save();
        $this->audit->record($context, 'order.approval.decide', 'succeeded', ['number' => $order->number, 'decision' => 'reject', 'reason' => $reason], 'order', $order->id);
        $cancelled = $this->checkout->transition($order, OrderStateMachine::CANCELLED, $context, 'neschváleno: '.$reason);
        $this->outbox->publish(GenericEvent::of('order.approval.rejected', 'order', $order->id, ['number' => $order->number, 'reason' => $reason, 'decider' => $decider->name, 'requester_id' => $approval['requester_id'] ?? null], $order->organization_id));

        return $cancelled;
    }
}
