<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Settings\SettingsStore;

/**
 * Chargeback in credit: a customer asks to leave a paid service early; technical support approves or rejects; after
 * an approval the customer cancels the service from the panel and a configurable share (staff set it in the console,
 * default 70 %) of the unused, already paid period comes back as wallet credit — never as money. The refund is
 * computed when the cancellation starts (so a later renewal cannot change it) and booked when the service is
 * terminated, through the ordinary wallet top-up path (ledger, receipt-free, audited).
 */
final class ChargebackService
{
    public const SETTING_PERCENT = 'chargeback.percent';

    public function __construct(
        private readonly SettingsStore $settings,
        private readonly WalletService $wallets,
        private readonly ServiceService $services,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** The share of the unused period returned as credit, as staff set it (bounded 0–100). */
    public function percent(): int
    {
        $set = $this->settings->get(self::SETTING_PERCENT);

        return max(0, min(100, (int) ($set !== null ? $set : config('onhost.chargeback.percent', 70))));
    }

    public function setPercent(int $percent, ?string $by = null): int
    {
        if ($percent < 0 || $percent > 100) {
            throw new DomainError('chargeback_percent_invalid', 'The chargeback share must be between 0 and 100 percent.', 422, ['field' => 'percent']);
        }
        $this->settings->set(self::SETTING_PERCENT, $percent, $by);

        return $this->percent();
    }

    /**
     * What a cancellation now would return: the unused rest of the current paid period times the share.
     *
     * @return array{currency:string, period_end:?string, unused_minor:int, percent:int, refund_minor:int, subscription_id:?string}
     */
    public function estimate(Service $service, ?int $percent = null): array
    {
        $percent ??= $this->percent();
        $subscription = Subscription::query()->where('service_id', $service->id)->whereNotIn('state', [Subscription::CANCELLED])->orderByDesc('created_at')->first();
        if ($subscription === null || $subscription->current_period_end === null || $subscription->current_period_start === null) {
            return ['currency' => (string) ($subscription?->currency ?? 'CZK'), 'period_end' => null, 'unused_minor' => 0, 'percent' => $percent, 'refund_minor' => 0, 'subscription_id' => $subscription?->id];
        }
        $total = max(1, $subscription->current_period_start->diffInSeconds($subscription->current_period_end, true));
        $left = max(0, (int) now()->diffInSeconds($subscription->current_period_end, false));
        $unused = (int) round((int) $subscription->amount_minor * min(1.0, $left / $total));
        $refund = (int) round($unused * $percent / 100);

        return ['currency' => (string) $subscription->currency, 'period_end' => $subscription->current_period_end->toIso8601String(), 'unused_minor' => $unused, 'percent' => $percent, 'refund_minor' => $refund, 'subscription_id' => $subscription->id];
    }

    public function open(Service $service): ?ChargebackRequest
    {
        return ChargebackRequest::query()->where('service_id', $service->id)->whereIn('state', ChargebackRequest::OPEN)->orderByDesc('created_at')->first();
    }

    public function latest(Service $service): ?ChargebackRequest
    {
        return ChargebackRequest::query()->where('service_id', $service->id)->orderByDesc('created_at')->first();
    }

    public function request(Service $service, ?User $user, string $reason, CommandContext $context): ChargebackRequest
    {
        if (! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED], true)) {
            throw new DomainError('service_state_invalid', "A chargeback cannot be requested while the service is {$service->state}.", 409, ['state' => $service->state]);
        }
        if ($this->open($service) !== null) {
            throw new DomainError('chargeback_already_open', 'A chargeback request for this service is already waiting for a decision.', 409);
        }
        $estimate = $this->estimate($service);
        if ($estimate['subscription_id'] === null) {
            throw new DomainError('chargeback_no_subscription', 'The service has no paid period to return; nothing to refund.', 422);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new DomainError('chargeback_reason_required', 'Tell support in a sentence why you want to leave the service.', 422, ['field' => 'reason']);
        }
        $request = ChargebackRequest::query()->create([
            'organization_id' => $service->organization_id, 'service_id' => $service->id, 'requested_by' => $user?->id, 'state' => ChargebackRequest::REQUESTED, 'reason' => mb_substr($reason, 0, 2000),
            'percent' => $estimate['percent'], 'currency' => $estimate['currency'], 'unused_minor' => $estimate['unused_minor'], 'refund_minor' => $estimate['refund_minor'],
        ]);
        $scoped = $context->withScope($service->organization_id, $service->project_id);
        $this->audit->record($scoped, 'chargeback.request', 'succeeded', ['chargeback' => $request->id, 'estimate' => $estimate], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('chargeback.requested', 'chargeback', $request->id, ['service_id' => $service->id, 'label' => $service->label ?: $service->name, 'reason' => $request->reason, 'refund' => Money::minor($estimate['refund_minor'], $estimate['currency']), 'percent' => $estimate['percent']], $service->organization_id));

        return $request;
    }

    /** Technical support decides; the share in force at the approval is the one the customer gets. */
    public function decide(ChargebackRequest $request, string $decision, ?string $reason, CommandContext $context): ChargebackRequest
    {
        if ($request->state !== ChargebackRequest::REQUESTED) {
            throw new DomainError('chargeback_not_pending', 'The request is not waiting for a decision.', 409, ['state' => $request->state]);
        }
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new DomainError('chargeback_decision_invalid', 'Decision must be approve or reject.', 422, ['field' => 'decision']);
        }
        $service = Service::query()->withTrashed()->find($request->service_id);
        $estimate = $service !== null ? $this->estimate($service) : null;
        $request->forceFill([
            'state' => $decision === 'approve' ? ChargebackRequest::APPROVED : ChargebackRequest::REJECTED, 'decision_reason' => $reason !== null ? mb_substr($reason, 0, 2000) : null, 'decided_by' => $context->actorId, 'decided_at' => now(),
            'percent' => $this->percent(), 'unused_minor' => $estimate['unused_minor'] ?? $request->unused_minor, 'refund_minor' => $estimate !== null ? (int) round($estimate['unused_minor'] * $this->percent() / 100) : $request->refund_minor,
        ])->save();
        $this->audit->record($context->withScope($request->organization_id), 'chargeback.decide', 'succeeded', ['chargeback' => $request->id, 'decision' => $decision, 'reason' => $reason, 'percent' => $request->percent], 'service', $request->service_id);
        $this->outbox->publish(GenericEvent::of($decision === 'approve' ? 'chargeback.approved' : 'chargeback.rejected', 'chargeback', $request->id, ['service_id' => $request->service_id, 'label' => $service?->label ?: ($service?->name ?? ''), 'reason' => $reason, 'percent' => $request->percent, 'refund' => Money::minor($request->refund_minor, $request->currency)], $request->organization_id));

        return $request;
    }

    /**
     * The customer cancels an approved request's service: the refund is fixed now, the service terminates through
     * the ordinary saga (final backup included), and the credit is booked when the termination is confirmed.
     */
    public function cancelService(ChargebackRequest $request, CommandContext $context): ChargebackRequest
    {
        if ($request->state !== ChargebackRequest::APPROVED) {
            throw new DomainError('chargeback_not_approved', 'Support has not approved this request yet.', 409, ['state' => $request->state]);
        }
        $service = Service::query()->find($request->service_id);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        $estimate = $this->estimate($service, $request->percent);
        $request->forceFill(['unused_minor' => $estimate['unused_minor'], 'refund_minor' => $estimate['refund_minor'], 'currency' => $estimate['currency'], 'cancelled_at' => now()])->save();
        if ($service->state === ServiceStateMachine::TERMINATED) {
            return $this->settle($request, $context);
        }
        $operation = $this->services->requestAction($service, 'terminate', $context, "chargeback:{$request->id}:terminate", ['reason' => 'chargeback '.$request->id, 'final_backup' => true]);
        $request->forceFill(['state' => ChargebackRequest::CANCELLING, 'operation_id' => $operation->id])->save();
        $this->audit->record($context->withScope($service->organization_id, $service->project_id), 'chargeback.cancel', 'succeeded', ['chargeback' => $request->id, 'operation_id' => $operation->id, 'refund_minor' => $request->refund_minor], 'service', $service->id);

        return $request;
    }

    /** Called when a service is terminated: a chargeback waiting for it gets its credit. */
    public function settleForService(string $serviceId, CommandContext $context): ?ChargebackRequest
    {
        $request = ChargebackRequest::query()->where('service_id', $serviceId)->where('state', ChargebackRequest::CANCELLING)->first();

        return $request === null ? null : $this->settle($request, $context);
    }

    private function settle(ChargebackRequest $request, CommandContext $context): ChargebackRequest
    {
        if ($request->refund_minor > 0) {
            $this->wallets->topup($request->organization_id, Money::minor($request->refund_minor, $request->currency), 'chargeback', "chargeback:{$request->id}", $context->withScope($request->organization_id), null, "Vrácení kreditu za zrušení služby ({$request->percent} % nevyužitého období)", false, null, 'chargeback');
        }
        $request->forceFill(['state' => ChargebackRequest::REFUNDED, 'refunded_at' => now()])->save();
        $service = Service::query()->withTrashed()->find($request->service_id);
        $this->audit->record($context->withScope($request->organization_id), 'chargeback.refund', 'succeeded', ['chargeback' => $request->id, 'refund_minor' => $request->refund_minor, 'currency' => $request->currency], 'service', $request->service_id);
        $this->outbox->publish(GenericEvent::of('chargeback.refunded', 'chargeback', $request->id, ['service_id' => $request->service_id, 'label' => $service?->label ?: ($service?->name ?? ''), 'refund' => Money::minor($request->refund_minor, $request->currency), 'percent' => $request->percent], $request->organization_id));

        return $request;
    }

    /** @return array<string,mixed> */
    public function present(ChargebackRequest $request): array
    {
        return [
            'id' => $request->id, 'service_id' => $request->service_id, 'organization_id' => $request->organization_id, 'state' => $request->state, 'reason' => $request->reason, 'decision_reason' => $request->decision_reason,
            'percent' => $request->percent, 'currency' => $request->currency, 'unused' => Money::minor((int) $request->unused_minor, $request->currency), 'refund' => Money::minor((int) $request->refund_minor, $request->currency),
            'requested_at' => $request->created_at?->toIso8601String(), 'decided_at' => $request->decided_at?->toIso8601String(), 'cancelled_at' => $request->cancelled_at?->toIso8601String(), 'refunded_at' => $request->refunded_at?->toIso8601String(), 'operation_id' => $request->operation_id,
        ];
    }
}
