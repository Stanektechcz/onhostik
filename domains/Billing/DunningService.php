<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Onhost\Domain\Billing\Models\DunningAction;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\MailHealth;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Dunning (blueprint §65): DUE → OVERDUE_NOTICE → GRACE → SUSPENDED → TERMINATION_SCHEDULED → TERMINATED.
 * Payment delinquency never destroys data by itself: suspension keeps the resource, termination runs the
 * normal saga with a final backup and the retention window. Domains are never touched here (§65.2).
 */
final class DunningService
{
    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public function open(string $organizationId, ?string $invoiceId, ?string $serviceId, \DateTimeInterface $dueAt): DunningCase
    {
        $query = DunningCase::query()->where('organization_id', $organizationId)->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED]);
        $existing = $invoiceId !== null ? (clone $query)->where('invoice_id', $invoiceId)->first() : ($serviceId !== null ? (clone $query)->where('service_id', $serviceId)->whereNull('invoice_id')->first() : (clone $query)->whereNull('invoice_id')->whereNull('service_id')->first());
        if ($existing !== null) {
            return $existing;
        }
        $case = DunningCase::query()->create(['organization_id' => $organizationId, 'invoice_id' => $invoiceId, 'service_id' => $serviceId, 'state' => DunningCase::DUE, 'due_at' => $dueAt, 'next_action_at' => now(), 'notices_sent' => []]);
        $this->outbox->publish(GenericEvent::of('dunning.opened', 'dunning_case', $case->id, ['invoice_id' => $invoiceId, 'service_id' => $serviceId, 'due_at' => $case->due_at->toIso8601String()], $organizationId));

        return $case;
    }

    /** @return array{cases:int, notices:int, suspended:int, scheduled:int, terminated:int, resolved:int} */
    public function tick(?CommandContext $context = null): array
    {
        $context ??= CommandContext::system('dunning');
        $policy = (array) config('onhost.billing.dunning');
        $notices = array_map('intval', (array) ($policy['overdue_notice_days'] ?? [3, 7, 14]));
        sort($notices);
        $stats = ['cases' => 0, 'notices' => 0, 'suspended' => 0, 'scheduled' => 0, 'terminated' => 0, 'resolved' => 0];
        // while the platform's mail does not leave, the reminders did not arrive: nobody is suspended or terminated for not answering them (H24)
        $enforce = ! app(MailHealth::class)->failing();
        $cases = DunningCase::query()->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED])->where(fn ($q) => $q->whereNull('next_action_at')->orWhere('next_action_at', '<=', now()))->orderBy('due_at')->limit(500)->get();
        foreach ($cases as $case) {
            $stats['cases']++;
            if ($case->invoice_id !== null) {
                $invoice = Invoice::query()->find($case->invoice_id);
                if ($invoice === null || in_array($invoice->state, [Invoice::PAID, Invoice::CANCELLED, Invoice::CREDITED], true)) {
                    $this->resolveCase($case, $context, 'invoice settled');
                    $stats['resolved']++;

                    continue;
                }
            }
            $days = (int) $case->due_at->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);
            $sent = array_map('intval', (array) ($case->notices_sent ?? []));
            foreach ($notices as $day) {
                if ($days >= $day && ! in_array($day, $sent, true)) {
                    $sent[] = $day;
                    $this->act($case, 'notice', ['day' => $day, 'days_overdue' => $days], $context);
                    $stats['notices']++;
                    if ($case->state === DunningCase::DUE) {
                        $this->transition($case, DunningCase::OVERDUE_NOTICE, $context);
                    }
                }
            }
            $case->forceFill(['notices_sent' => $sent])->save();
            $graceStart = (int) ($policy['grace_days'] ?? 14);
            $suspendAt = (int) ($policy['suspend_after_days'] ?? 30);
            $terminateAt = (int) ($policy['terminate_after_days'] ?? 60);
            if ($case->state === DunningCase::OVERDUE_NOTICE && $days >= $graceStart) {
                $this->transition($case, DunningCase::GRACE, $context);
            }
            if ($enforce && in_array($case->state, [DunningCase::OVERDUE_NOTICE, DunningCase::GRACE], true) && $days >= $suspendAt) {
                $this->suspend($case, $context);
                $stats['suspended']++;
            }
            if ($enforce && $case->state === DunningCase::SUSPENDED && $days >= $terminateAt - 7) {
                $case->forceFill(['termination_at' => $case->due_at->copy()->addDays($terminateAt)])->save();
                $this->transition($case, DunningCase::TERMINATION_SCHEDULED, $context, ['termination_at' => $case->termination_at->toIso8601String()]);
                $this->act($case, 'schedule_termination', ['termination_at' => $case->termination_at->toIso8601String()], $context);
                $stats['scheduled']++;
            }
            if ($enforce && $case->state === DunningCase::TERMINATION_SCHEDULED && $case->termination_at !== null && $case->termination_at <= now()) {
                $this->terminate($case, $context);
                $stats['terminated']++;
            }
            $case->forceFill(['next_action_at' => now()->addDay()->startOfDay()->addHours(6)])->save();
        }

        return $stats;
    }

    /** Payment received: close matching cases, lift suspensions and limits. */
    public function resolve(string $organizationId, ?string $invoiceId, ?string $serviceId, CommandContext $context): int
    {
        $query = DunningCase::query()->where('organization_id', $organizationId)->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED]);
        if ($invoiceId !== null) {
            $query->where('invoice_id', $invoiceId);
        } elseif ($serviceId !== null) {
            $query->where(fn ($q) => $q->where('service_id', $serviceId)->orWhereNull('service_id'));
        }
        $count = 0;
        foreach ($query->get() as $case) {
            $this->resolveCase($case, $context, 'paid');
            $count++;
        }

        return $count;
    }

    private function resolveCase(DunningCase $case, CommandContext $context, string $reason): void
    {
        $wasSuspended = in_array($case->state, [DunningCase::SUSPENDED, DunningCase::TERMINATION_SCHEDULED], true);
        $case->forceFill(['state' => DunningCase::RESOLVED, 'resolved_at' => now(), 'next_action_at' => null])->save();
        $this->act($case, 'resolve', ['reason' => $reason], $context);
        if ($wasSuspended && $case->service_id !== null) {
            $service = Service::query()->find($case->service_id);
            if ($service !== null && $service->state === ServiceStateMachine::SUSPENDED && ($service->suspended_reason ?? '') !== 'dunning' && in_array(SuspensionHold::PAYMENT, SuspensionHold::holds($service), true)) {
                // the site was down for another reason before it fell overdue (the customer's own pause, a quarantine): the money lifts its own hold only
                app(ServiceService::class)->liftHold($service, SuspensionHold::PAYMENT, CommandContext::system('dunning resolved')->withScope($service->organization_id));
            } elseif ($service !== null && $service->state === ServiceStateMachine::SUSPENDED && ($service->suspended_reason ?? '') === 'dunning') {
                try {
                    // `lift` names the one hold the payment answers: a quarantine on the same service stays, and so does the suspension (H17)
                    app(ServiceService::class)->requestAction($service, 'resume', CommandContext::system('dunning resolved')->withScope($service->organization_id), "dunning_resume:{$case->id}", ['reason' => 'dunning resolved', 'lift' => SuspensionHold::PAYMENT]);
                    $this->act($case, 'resume', ['service_id' => $service->id], $context);
                } catch (DomainError $e) {
                    $this->act($case, 'resume', ['service_id' => $service->id, 'error' => $e->error], $context);
                }
            }
        }
        $organization = Organization::query()->find($case->organization_id);
        if ($organization !== null && data_get($organization->settings, 'billing.limited') && ! DunningCase::query()->where('organization_id', $organization->id)->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED])->exists()) {
            $organization->forceFill(['settings' => array_replace((array) $organization->settings, ['billing' => array_replace((array) data_get($organization->settings, 'billing', []), ['limited' => false])])])->save();
            $this->outbox->publish(GenericEvent::of('organization.limit_lifted', 'organization', $organization->id, [], $organization->id));
        }
        $this->outbox->publish(GenericEvent::of('dunning.resolved', 'dunning_case', $case->id, ['reason' => $reason, 'service_id' => $case->service_id, 'invoice_id' => $case->invoice_id], $case->organization_id));
    }

    private function suspend(DunningCase $case, CommandContext $context): void
    {
        $this->transition($case, DunningCase::SUSPENDED, $context);
        $case->forceFill(['suspended_at' => now()])->save();
        if ($case->service_id !== null) {
            $service = Service::query()->find($case->service_id);
            if ($service !== null && $service->state === ServiceStateMachine::SUSPENDED) { // already down (paused by the customer, quarantined): it must not come back while unpaid
                app(ServiceService::class)->imposeHold($service, SuspensionHold::PAYMENT, 'dunning', CommandContext::system('dunning')->withScope($service->organization_id));
            }
            if ($service !== null && in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
                try {
                    app(ServiceService::class)->requestAction($service, 'suspend', CommandContext::system('dunning')->withScope($service->organization_id), "dunning_suspend:{$case->id}", ['reason' => 'dunning']);
                    $this->act($case, 'suspend', ['service_id' => $service->id], $context);
                } catch (DomainError $e) {
                    $this->act($case, 'suspend', ['service_id' => $service->id, 'error' => $e->error], $context);
                }
            }

            return;
        }
        $organization = Organization::query()->find($case->organization_id);
        if ($organization !== null) {
            $organization->forceFill(['settings' => array_replace((array) $organization->settings, ['billing' => array_replace((array) data_get($organization->settings, 'billing', []), ['limited' => true, 'limited_at' => now()->toIso8601String()])])])->save();
            $this->act($case, 'limit', ['organization_id' => $organization->id], $context);
            $this->outbox->publish(GenericEvent::of('organization.limited', 'organization', $organization->id, ['reason' => 'unpaid invoices'], $organization->id));
        }
    }

    private function terminate(DunningCase $case, CommandContext $context): void
    {
        if ($case->service_id !== null) {
            $service = Service::query()->find($case->service_id);
            if ($service !== null && $service->state === ServiceStateMachine::SUSPENDED) {
                try {
                    app(ServiceService::class)->requestAction($service, 'terminate', CommandContext::system('dunning termination')->withScope($service->organization_id), "dunning_terminate:{$case->id}", ['reason' => 'unpaid after dunning', 'final_backup' => true]);
                    $this->act($case, 'terminate', ['service_id' => $service->id], $context);
                } catch (DomainError $e) {
                    $this->act($case, 'terminate', ['service_id' => $service->id, 'error' => $e->error], $context);

                    return; // keep the case open; an operator sees the failed action
                }
            }
        }
        $this->transition($case, DunningCase::TERMINATED, $context);
    }

    private function transition(DunningCase $case, string $to, CommandContext $context, array $meta = []): void
    {
        DunningCase::machine()->assertTransition($case->state, $to);
        $from = $case->state;
        $case->forceFill(['state' => $to])->save();
        $this->audit->record($context->withScope($case->organization_id), 'dunning.transition', 'succeeded', array_merge(['from' => $from, 'to' => $to], $meta), 'dunning_case', $case->id);
        $this->outbox->publish(GenericEvent::of('dunning.'.strtolower($to), 'dunning_case', $case->id, array_merge(['from' => $from, 'invoice_id' => $case->invoice_id, 'service_id' => $case->service_id, 'due_at' => $case->due_at->toIso8601String()], $meta), $case->organization_id));
    }

    private function act(DunningCase $case, string $action, array $meta, CommandContext $context): DunningAction
    {
        $row = DunningAction::query()->create(['case_id' => $case->id, 'action' => $action, 'performed_at' => now(), 'result' => isset($meta['error']) ? 'failed' : 'ok', 'meta' => $meta]);
        if ($action === 'notice') {
            $this->outbox->publish(GenericEvent::of('dunning.notice', 'dunning_case', $case->id, array_merge($meta, ['invoice_id' => $case->invoice_id, 'service_id' => $case->service_id, 'due_at' => $case->due_at->toIso8601String()]), $case->organization_id));
        }

        return $row;
    }
}
