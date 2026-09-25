<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Consent;
use Onhost\Domain\Orders\Models\ConsentDocument;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * A consumer's withdrawal from a distance contract within fourteen days (TASK-0025, owner decision 17), unwound in the
 * order the owner set: the service is switched off first — a refunded service must not keep running — then the paid,
 * unused part comes back to the account credit as a credit note of the very lines that were paid (never a top-up, never
 * the price list), then the service is cancelled through the ordinary saga with its final backup. A `withdrawal` hold keeps
 * the customer from resuming it for free.
 *
 * Each step runs as the platform on the authority of the consumer's notice (their step-up command, or a letter staff
 * recorded behind four eyes), once: the row is locked, the credit notes are made once, the operations carry fixed keys.
 * A step a panel or a hold refuses is kept as `error`, reported to operations and retried by onhost:withdrawals:finish;
 * the refund never waits for the cancellation, and a cancellation refused never takes the refund back.
 */
final class WithdrawalService
{
    public function __construct(
        private readonly WithdrawalPolicy $policy,
        private readonly ChargebackService $chargebacks,
        private readonly InvoiceService $invoices,
        private readonly ServiceService $services,
        private readonly CheckoutService $checkout,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * What withdrawing on `$asOf` would give back: the paid lines of the service and its add-ons whose period has not run
     * out, prorated by the days after the notice (the day of the notice counts as used).
     *
     * @return array{currency:string, refund:Money, lines:list<array<string,mixed>>}
     */
    public function estimate(Service $service, CarbonImmutable $asOf): array
    {
        $day = CarbonImmutable::parse(AccountingClock::date($asOf));
        $lines = [];
        $currency = 'CZK';
        $parts = Service::query()->where('family', 'addon')->where('tags->parent_service_id', $service->id)->get()->prepend($service);
        foreach ($parts as $part) {
            $estimate = $this->chargebacks->estimate($part, 100, $day);
            $currency = $estimate['lines'] !== [] ? $estimate['currency'] : $currency;
            foreach ($estimate['lines'] as $row) {
                $lines[$row['line_id']] ??= $row + ['service_id' => $part->id];
            }
        }
        $lines = array_values($lines);

        return ['currency' => $currency, 'refund' => Money::minor((int) array_sum(array_column($lines, 'refund_minor')), $currency), 'lines' => $lines];
    }

    public function withdrawService(Service $service, CommandContext $context, string $channel, CarbonImmutable $sentAt, ?string $statement, ?string $requestedBy): Withdrawal
    {
        $withdrawal = DB::transaction(function () use ($service, $context, $channel, $sentAt, $statement, $requestedBy) {
            $service = Service::query()->lockForUpdate()->find($service->id) ?? throw DomainError::notFound('service');
            $terms = $this->policy->forService($service, $sentAt);
            $estimate = $this->estimate($service, $sentAt);
            $consent = $this->recordConsent($terms['order'], $context, $channel, $requestedBy);
            $withdrawal = Withdrawal::query()->create([
                'organization_id' => $service->organization_id, 'order_id' => $terms['order']->id, 'order_item_id' => $terms['item']->id, 'service_id' => $service->id, 'subject_key' => 'item:'.$terms['item']->id,
                'channel' => $channel, 'requested_by' => $requestedBy, 'sent_at' => $sentAt, 'contract_start_at' => $terms['contract_start'], 'deadline_at' => $terms['deadline'], 'customer_class_at_order' => $terms['customer_class'],
                'refund_consent_id' => $consent->id, 'statement' => $statement !== null ? mb_substr($statement, 0, 2000) : null, 'state' => Withdrawal::SUSPENDING, 'currency' => $estimate['currency'],
                'refund_minor' => $estimate['refund']->minor, 'basis' => ['as_of' => AccountingClock::date($sentAt), 'lines' => $estimate['lines']],
            ]);
            $consent->forceFill(['evidence' => array_merge((array) $consent->evidence, ['withdrawal_id' => $withdrawal->id])])->save();
            // nothing renews while the contract is unwound, and a request to leave early with a share back is superseded by the right to leave with all of it
            $parts = Service::query()->where('family', 'addon')->where('tags->parent_service_id', $service->id)->pluck('id')->push($service->id)->all();
            Subscription::query()->whereIn('service_id', $parts)->whereNotIn('state', [Subscription::CANCELLED])->update(['auto_renew' => false]);
            ChargebackRequest::query()->where('service_id', $service->id)->whereIn('state', [ChargebackRequest::REQUESTED, ChargebackRequest::APPROVED])->update(['state' => ChargebackRequest::WITHDRAWN, 'decision_reason' => 'nahrazeno odstoupením od smlouvy '.$withdrawal->id]);
            $this->accepted($withdrawal, $context, self::label($service));

            return $withdrawal;
        }, 3);

        return $this->advance($withdrawal);
    }

    /** A paid order nothing of which was delivered: cancelling it credits every line (CheckoutService::transition) and frees the credit. */
    public function withdrawOrder(Order $order, CommandContext $context, string $channel, CarbonImmutable $sentAt, ?string $statement, ?string $requestedBy): Withdrawal
    {
        return DB::transaction(function () use ($order, $context, $channel, $sentAt, $statement, $requestedBy) {
            $order = Order::query()->lockForUpdate()->find($order->id) ?? throw DomainError::notFound('order');
            $terms = $this->policy->forOrder($order, $sentAt);
            $consent = $this->recordConsent($order, $context, $channel, $requestedBy);
            $withdrawal = Withdrawal::query()->create([
                'organization_id' => $order->organization_id, 'order_id' => $order->id, 'subject_key' => 'order:'.$order->id, 'channel' => $channel, 'requested_by' => $requestedBy, 'sent_at' => $sentAt,
                'contract_start_at' => $terms['contract_start'], 'deadline_at' => $terms['deadline'], 'customer_class_at_order' => $terms['customer_class'], 'refund_consent_id' => $consent->id,
                'statement' => $statement !== null ? mb_substr($statement, 0, 2000) : null, 'state' => Withdrawal::SUSPENDING, 'currency' => $order->currency, 'refund_minor' => (int) $order->total_minor, 'basis' => ['order' => $order->number],
            ]);
            $this->accepted($withdrawal, $context, 'objednávka '.$order->number);
            $ctx = CommandContext::system('withdrawal '.$withdrawal->id)->withScope($order->organization_id);
            $this->checkout->transition($order, OrderStateMachine::CANCELLED, $ctx, 'odstoupení spotřebitele '.$withdrawal->id);
            $notes = Invoice::query()->where('type', 'credit_note')->where('order_id', $order->id)->where('created_at', '>=', $withdrawal->created_at)->get();
            $credited = (int) abs((int) $notes->sum('total_minor'));
            $postpaid = $order->payment_mode === 'postpaid';
            $withdrawal->forceFill(['state' => Withdrawal::REFUNDED, 'refunded_at' => now(), 'refund_minor' => (int) $order->total_minor, 'to_credit_minor' => $postpaid ? 0 : (int) $order->total_minor, 'off_document_minor' => $postpaid ? $credited : 0,
                'basis' => ['order' => $order->number, 'credit_notes' => $notes->pluck('number')->map(fn ($n) => (string) $n)->all()]])->save();
            $this->refunded($withdrawal, $ctx, 'objednávka '.$order->number);

            return $this->complete($withdrawal, $ctx, 'objednávka '.$order->number);
        }, 3);
    }

    /**
     * Move a withdrawal as far as it can go now: switch the service off, give the money back once it is off, cancel it,
     * and note it done once the cancellation is confirmed. Called after the notice, on the service's events and hourly.
     */
    public function advance(Withdrawal $withdrawal, bool $dryRun = false): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $dryRun) {
            $w = Withdrawal::query()->lockForUpdate()->find($withdrawal->id);
            if ($w === null || $w->state === Withdrawal::COMPLETED || $w->service_id === null) {
                return $w ?? $withdrawal;
            }
            $service = Service::query()->withTrashed()->find($w->service_id);
            $ctx = CommandContext::system('withdrawal '.$w->id)->withScope($w->organization_id);
            $label = $service === null ? $w->service_id : self::label($service);
            if ($dryRun) {
                return $w;
            }
            $gone = $service === null || $service->trashed() || in_array($service->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING], true);
            if ($w->state === Withdrawal::SUSPENDING) {
                if (! $gone && ! self::isOff($service)) {
                    $this->step($w, $service, 'suspend', $ctx, $label);

                    return $w;
                }
                if (! $gone) {
                    $this->services->imposeHold($service, SuspensionHold::WITHDRAWAL, 'withdrawal '.$w->id, $ctx);
                }
                $this->refund($w, $ctx, $label);
            }
            if ($w->state === Withdrawal::TERMINATING && ! $gone && ! self::cancelled($service)) {
                $operation = $w->terminate_operation_id === null ? null : Operation::query()->find($w->terminate_operation_id);
                if ($operation !== null && ! self::failed($operation)) {
                    return $w; // the cancellation is still running
                }
                $w->forceFill(['state' => Withdrawal::REFUNDED])->save(); // it failed at the panel: ask again
            }
            if ($w->state === Withdrawal::REFUNDED && ! $gone && ! self::cancelled($service)) {
                $this->step($w, $service, 'terminate', $ctx, $label);
                if ($w->state !== Withdrawal::TERMINATING || ! self::cancelled($service->refresh())) {
                    return $w;
                }
            }
            if (! $gone) {
                $this->services->imposeHold($service, SuspensionHold::WITHDRAWAL, 'withdrawal '.$w->id, $ctx); // second guard: whatever the saga wrote, no free resume
            }

            return $this->complete($w, $ctx, $label);
        }, 3);
    }

    /**
     * Withdrawals still being unwound, each moved as far as it can go (onhost:withdrawals:finish, hourly). It never
     * creates a withdrawal — it only finishes those consumers asked for.
     *
     * @return array{open:int, moved:int, completed:int, waiting:list<array{id:string, state:string, error:?string}>}
     */
    public function finishPending(bool $dryRun = false, int $limit = 200): array
    {
        $stats = ['open' => 0, 'moved' => 0, 'completed' => 0, 'waiting' => []];
        foreach (Withdrawal::query()->whereIn('state', Withdrawal::OPEN)->orderBy('created_at')->limit(max(1, $limit))->get() as $row) {
            $stats['open']++;
            $before = $row->state;
            try {
                $after = $this->advance($row, $dryRun);
            } catch (Throwable $e) {
                report($e);
                $stats['waiting'][] = ['id' => (string) $row->id, 'state' => $before, 'error' => mb_substr($e->getMessage(), 0, 120)];

                continue;
            }
            $stats['moved'] += $after->state !== $before ? 1 : 0;
            $stats['completed'] += $after->state === Withdrawal::COMPLETED ? 1 : 0;
            if ($after->state !== Withdrawal::COMPLETED) {
                $stats['waiting'][] = ['id' => (string) $after->id, 'state' => $after->state, 'error' => $after->error];
            }
        }

        return $stats;
    }

    /** A service event: the withdrawal waiting for it moves on. Never throws — the relay delivers the event to every listener. */
    public function onServiceEvent(string $serviceId): void
    {
        $withdrawal = Withdrawal::query()->where('service_id', $serviceId)->whereIn('state', Withdrawal::OPEN)->first();
        if ($withdrawal === null) {
            return;
        }
        try {
            $this->advance($withdrawal);
        } catch (Throwable $e) {
            report($e); // the hourly finish command tries again
        }
    }

    /** @return array<string,mixed> */
    public function present(Withdrawal $w): array
    {
        return [
            'id' => $w->id, 'state' => $w->state, 'organization_id' => $w->organization_id, 'order_id' => $w->order_id, 'order_number' => Order::query()->whereKey($w->order_id)->value('number'), 'service_id' => $w->service_id,
            'channel' => $w->channel, 'sent_at' => $w->sent_at->toIso8601String(), 'contract_start_at' => $w->contract_start_at->toIso8601String(), 'deadline_at' => $w->deadline_at->toIso8601String(), 'customer_class_at_order' => $w->customer_class_at_order,
            'refund' => Money::minor((int) $w->refund_minor, $w->currency), 'to_credit' => Money::minor((int) $w->to_credit_minor, $w->currency), 'off_documents' => Money::minor((int) $w->off_document_minor, $w->currency),
            'credit_notes' => array_values((array) data_get($w->basis, 'credit_notes', [])), 'suspend_operation_id' => $w->suspend_operation_id, 'terminate_operation_id' => $w->terminate_operation_id, 'error' => $w->error,
            'created_at' => $w->created_at?->toIso8601String(), 'refunded_at' => $w->refunded_at?->toIso8601String(), 'completed_at' => $w->completed_at?->toIso8601String(),
        ];
    }

    /**
     * The credit notes for exactly the unused part of each paid line fixed at the notice, and the money back to the credit
     * (a document not paid yet is reduced instead). Once: `refunded_at` is set under the row lock.
     */
    private function refund(Withdrawal $w, CommandContext $ctx, string $label): void
    {
        if ($w->refunded_at !== null) {
            $w->forceFill(['state' => Withdrawal::REFUNDED])->save();

            return;
        }
        $after = CarbonImmutable::parse((string) data_get($w->basis, 'as_of', AccountingClock::date($w->sent_at)))->addDay()->toDateString(); // the day of the notice counts as used
        $byDocument = [];
        foreach ((array) data_get($w->basis, 'lines', []) as $row) {
            if (is_array($row) && (int) ($row['refund_minor'] ?? 0) > 0) {
                $byDocument[(string) $row['invoice_id']][(string) $row['line_id']] = $row;
            }
        }
        $toCredit = 0;
        $offDocuments = 0;
        $notes = [];
        foreach ($byDocument as $invoiceId => $rows) {
            $document = Invoice::query()->find($invoiceId);
            if ($document === null) {
                continue;
            }
            $credited = $this->invoices->creditedByLine($document);
            $amounts = [];
            foreach ($rows as $lineId => $row) {
                $line = $document->lines()->whereKey($lineId)->first();
                $gross = $line === null ? 0 : min((int) $row['refund_minor'], (int) $line->total_minor - (int) ($credited[$lineId]['total'] ?? 0)); // what somebody credited meanwhile is not given twice
                if ($gross > 0) {
                    $amounts[$lineId] = ['gross' => $gross, 'period_from' => min(max((string) $row['period_from'], $after), (string) $row['period_to']), 'period_to' => (string) $row['period_to']];
                }
            }
            if ($amounts === []) {
                continue;
            }
            try {
                $given = $this->invoices->giveBack($document, $amounts, 'Odstoupení od smlouvy spotřebitelem — nevyužitá část', $ctx);
            } catch (DomainError $e) {
                $this->audit->record($ctx, 'billing.withdrawal.refund', 'failed', ['withdrawal' => $w->id, 'invoice' => $document->number, 'reason' => $e->error], 'withdrawal', $w->id);

                continue;
            }
            $toCredit += $given['to_credit_minor'];
            $offDocuments += $given['off_document_minor'];
            $notes[] = (string) $given['credit_note']->number;
        }
        $w->forceFill(['state' => Withdrawal::REFUNDED, 'refunded_at' => now(), 'refund_minor' => $toCredit + $offDocuments, 'to_credit_minor' => $toCredit, 'off_document_minor' => $offDocuments, 'error' => null,
            'basis' => array_merge((array) $w->basis, ['credit_notes' => $notes])])->save();
        $this->refunded($w, $ctx, $label);
    }

    /** Ask for the service's suspension or cancellation, as the platform; a refusal is kept and reported, never thrown. */
    private function step(Withdrawal $w, Service $service, string $action, CommandContext $ctx, string $label): void
    {
        $column = $action === 'suspend' ? 'suspend_operation_id' : 'terminate_operation_id';
        $current = $w->{$column} === null ? null : Operation::query()->find($w->{$column});
        if ($current !== null && ! self::failed($current) && $current->state !== Operation::SUCCEEDED) {
            return; // asked already, still running
        }
        $attempt = (int) data_get($w->basis, "attempts.{$action}", 0) + ($current !== null ? 1 : 0);
        $key = "withdrawal:{$w->id}:{$action}".($attempt > 0 ? ":{$attempt}" : '');
        try {
            $params = ['reason' => 'withdrawal '.$w->id] + ($action === 'terminate' ? ['final_backup' => true] : []);
            $operation = $this->services->requestAction($service, $action, $ctx, $key, $params);
        } catch (DomainError $e) {
            if ($w->error !== $e->error) { // told once per new reason, not every hour
                $this->outbox->publish(GenericEvent::of('withdrawal.stalled', 'withdrawal', $w->id, ['service_id' => $w->service_id, 'label' => $label, 'step' => $action, 'error' => $e->error, 'refunded' => $w->refunded_at !== null], $w->organization_id));
            }
            $w->forceFill(['error' => mb_substr($e->error, 0, 200)])->save();
            $this->audit->record($ctx, "billing.withdrawal.{$action}", 'failed', ['withdrawal' => $w->id, 'error' => $e->error], 'service', $service->id);

            return;
        }
        $w->forceFill([$column => $operation->id, 'error' => null, 'state' => $action === 'terminate' ? Withdrawal::TERMINATING : $w->state, 'basis' => array_merge((array) $w->basis, ['attempts' => array_merge((array) data_get($w->basis, 'attempts', []), [$action => $attempt])])])->save();
        $this->audit->record($ctx, "billing.withdrawal.{$action}", 'succeeded', ['withdrawal' => $w->id, 'operation_id' => $operation->id], 'service', $service->id);
    }

    private function recordConsent(Order $order, CommandContext $context, string $channel, ?string $requestedBy): Consent
    {
        $document = ConsentDocument::current('withdrawal_waiver');

        return Consent::query()->create([
            'organization_id' => $order->organization_id, 'user_id' => $channel === Withdrawal::PANEL ? $requestedBy : null, 'order_id' => $order->id, 'kind' => 'withdrawal_refund_to_credit',
            'document_key' => 'withdrawal_waiver', 'document_version' => (string) ($document->version ?? 'unversioned'), 'document_hash' => $document?->hash, 'document_url' => $document->url ?? WithdrawalPolicy::TERMS_URL,
            'ip' => $context->ip, 'user_agent' => $context->userAgent !== null ? mb_substr($context->userAgent, 0, 250) : null, 'accepted_at' => now(),
            'evidence' => ['channel' => $channel, 'recorded_by' => $context->actorType.($context->actorId !== null ? ':'.$context->actorId : ''), 'request_id' => $context->requestId, 'correlation_id' => $context->correlationId],
        ]);
    }

    private function accepted(Withdrawal $w, CommandContext $context, string $label): void
    {
        $this->audit->record($context->withScope($w->organization_id), 'billing.withdrawal.accept', 'succeeded', ['withdrawal' => $w->id, 'channel' => $w->channel, 'sent_at' => $w->sent_at->toIso8601String(), 'estimate_minor' => $w->refund_minor], 'withdrawal', $w->id);
        $this->outbox->publish(GenericEvent::of('withdrawal.accepted', 'withdrawal', $w->id, [
            'service_id' => $w->service_id, 'label' => $label, 'order_number' => Order::query()->whereKey($w->order_id)->value('number'), 'sent_at' => $w->sent_at->toIso8601String(),
            'refund' => Money::minor((int) $w->refund_minor, $w->currency), 'channel' => $w->channel,
        ], $w->organization_id));
    }

    private function refunded(Withdrawal $w, CommandContext $ctx, string $label): void
    {
        $this->audit->record($ctx, 'billing.withdrawal.refund', 'succeeded', ['withdrawal' => $w->id, 'refund_minor' => $w->refund_minor, 'to_credit_minor' => $w->to_credit_minor, 'off_document_minor' => $w->off_document_minor, 'credit_notes' => data_get($w->basis, 'credit_notes', []), 'currency' => $w->currency], 'withdrawal', $w->id);
        $this->outbox->publish(GenericEvent::of('withdrawal.refunded', 'withdrawal', $w->id, [
            'service_id' => $w->service_id, 'label' => $label, 'refund' => Money::minor((int) $w->refund_minor, $w->currency), 'to_credit' => Money::minor((int) $w->to_credit_minor, $w->currency),
            'off_documents' => Money::minor((int) $w->off_document_minor, $w->currency), 'credit_notes' => array_values((array) data_get($w->basis, 'credit_notes', [])),
        ], $w->organization_id));
    }

    private function complete(Withdrawal $w, CommandContext $ctx, string $label): Withdrawal
    {
        $w->forceFill(['state' => Withdrawal::COMPLETED, 'completed_at' => now(), 'error' => null])->save();
        $this->audit->record($ctx, 'billing.withdrawal.complete', 'succeeded', ['withdrawal' => $w->id], 'withdrawal', $w->id);
        $this->outbox->publish(GenericEvent::of('withdrawal.completed', 'withdrawal', $w->id, ['service_id' => $w->service_id, 'label' => $label, 'refund' => Money::minor((int) $w->refund_minor, $w->currency)], $w->organization_id));

        return $w;
    }

    /** Not running any more: suspended (by anybody) or never came up. */
    private static function isOff(Service $service): bool
    {
        return in_array($service->state, [ServiceStateMachine::SUSPENDED, ServiceStateMachine::FAILED], true);
    }

    /** Cancelled: switched off with its removal scheduled (the terminate saga's end), or already removed. */
    private static function cancelled(Service $service): bool
    {
        return $service->state === ServiceStateMachine::SUSPENDED && $service->terminate_at !== null;
    }

    private static function failed(Operation $operation): bool
    {
        return in_array($operation->state, [Operation::FAILED, Operation::CANCELLED, Operation::COMPENSATED], true);
    }

    private static function label(Service $service): string
    {
        return (string) ($service->label ?: ($service->hostname ?: $service->name));
    }
}
