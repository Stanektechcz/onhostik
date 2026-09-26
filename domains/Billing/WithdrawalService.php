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
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\Models\WalletTopup;
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
     * out, prorated by the days after the notice (the day of the notice counts as used). `to_credit` is the part that will
     * reach the account credit, `off_documents` the part that only makes an unpaid document smaller (TASK-0025 review).
     *
     * @return array{currency:string, refund:Money, to_credit:Money, off_documents:Money, lines:list<array<string,mixed>>}
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
        $split = self::split($lines);

        return ['currency' => $currency, 'refund' => Money::minor((int) array_sum(array_column($lines, 'refund_minor')), $currency),
            'to_credit' => Money::minor($split['to_credit'], $currency), 'off_documents' => Money::minor($split['off_documents'], $currency), 'lines' => $lines];
    }

    /**
     * How a refund of these lines divides, document by document, the way `InvoiceService::giveBack` divides it: only what was
     * paid for comes back to the credit; the rest of a credit note makes an unpaid document smaller and is never money.
     *
     * @param  list<array<string,mixed>>  $lines
     * @return array{to_credit:int, off_documents:int}
     */
    private static function split(array $lines): array
    {
        $byDocument = [];
        foreach ($lines as $row) {
            $byDocument[(string) $row['invoice_id']] = ($byDocument[(string) $row['invoice_id']] ?? 0) + (int) $row['refund_minor'];
        }
        $toCredit = 0;
        $offDocuments = 0;
        foreach ($byDocument as $invoiceId => $gross) {
            $document = Invoice::query()->find($invoiceId);
            if ($document === null || $gross <= 0) {
                continue;
            }
            $returnedBefore = (int) ($document->meta['overpaid_returned_minor'] ?? 0);
            $over = max(0, min($gross, (int) $document->paid_minor - max(0, (int) $document->total_minor - (int) $document->credited_minor - $gross) - $returnedBefore));
            $toCredit += $over;
            $offDocuments += $gross - $over;
        }

        return ['to_credit' => $toCredit, 'off_documents' => $offDocuments];
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
                'refund_minor' => $estimate['refund']->minor, 'basis' => ['as_of' => AccountingClock::date($sentAt), 'lines' => $estimate['lines'],
                    'estimate' => ['to_credit_minor' => $estimate['to_credit']->minor, 'off_document_minor' => $estimate['off_documents']->minor]],
            ]);
            $consent->forceFill(['evidence' => array_merge((array) $consent->evidence, ['withdrawal_id' => $withdrawal->id])])->save();
            // nothing renews while the contract is unwound, and a request to leave early with a share back is superseded by the right to leave with all of it
            $parts = Service::query()->where('family', 'addon')->where('tags->parent_service_id', $service->id)->pluck('id')->push($service->id)->all();
            Subscription::query()->whereIn('service_id', $parts)->whereNotIn('state', [Subscription::CANCELLED])->update(['auto_renew' => false]);
            // a renewal already past due would be retried from the next top-up (retryPastDue does not ask about auto-renew) and that charge is not in the frozen refund
            Subscription::query()->whereIn('service_id', $parts)->where('state', Subscription::PAST_DUE)->update(['state' => Subscription::CANCELLED]);
            ChargebackRequest::query()->where('service_id', $service->id)->whereIn('state', [ChargebackRequest::REQUESTED, ChargebackRequest::APPROVED])->update(['state' => ChargebackRequest::WITHDRAWN, 'decision_reason' => 'nahrazeno odstoupením od smlouvy '.$withdrawal->id]);
            $this->accepted($withdrawal, $context, self::label($service), $estimate['to_credit'], $estimate['off_documents'], true);

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
                'statement' => $statement !== null ? mb_substr($statement, 0, 2000) : null, 'state' => Withdrawal::SUSPENDING, 'currency' => $order->currency, 'refund_minor' => 0, 'basis' => ['order' => $order->number],
            ]);
            $ctx = CommandContext::system('withdrawal '.$withdrawal->id)->withScope($order->organization_id);
            $postpaid = $order->payment_mode === 'postpaid';
            $hold = $order->wallet_hold_id === null ? null : WalletHold::query()->find($order->wallet_hold_id);
            $freed = ! $postpaid && $hold !== null && $hold->isActive() ? (int) $hold->amount_minor : 0; // a prepaid reservation the cancellation releases is money the customer has again
            $earlier = Invoice::query()->where('type', 'credit_note')->where('order_id', $order->id)->pluck('id')->all(); // a settlement's note of the same second is not this withdrawal's
            $this->checkout->transition($order, OrderStateMachine::CANCELLED, $ctx, 'odstoupení spotřebitele '.$withdrawal->id);
            $notes = Invoice::query()->where('type', 'credit_note')->where('order_id', $order->id)->whereNotIn('id', $earlier)->get();
            $credited = (int) abs((int) $notes->sum('total_minor'));
            // what the credit notes really put back on the credit (a paid document); the rest of them only reduced what was still owed
            $returned = (int) WalletTopup::query()->whereIn('idempotency_key', $notes->flatMap(fn (Invoice $n) => ["credit-note-return:{$n->id}", "give-back:{$n->id}"])->all())->sum('amount_minor');
            $toCredit = $freed + $returned;
            $offDocuments = max(0, $credited - $returned - $freed); // a document the reservation stood behind is the freed money, not a second refund
            $basis = ['order' => $order->number, 'credit_notes' => $notes->pluck('number')->map(fn ($n) => (string) $n)->all(), 'released_hold_minor' => $freed];
            if ($toCredit + $offDocuments === 0) { // an earlier settlement (a FAILED order) had already given everything back: nothing moves now, and nobody is told it did
                $basis['already_returned'] = ['by' => 'order settlement', 'returned_minor' => (int) data_get($order->meta, 'settlement.returned_minor', 0)];
            }
            $withdrawal->forceFill(['state' => Withdrawal::REFUNDED, 'refunded_at' => now(), 'refund_minor' => $toCredit + $offDocuments, 'to_credit_minor' => $toCredit, 'off_document_minor' => $offDocuments, 'basis' => $basis])->save();
            // the confirmation of receipt names what really moved (it is known now), never the order total
            $this->accepted($withdrawal, $context, 'objednávka '.$order->number, Money::minor($toCredit, $order->currency), Money::minor($offDocuments, $order->currency), false);
            if ($toCredit + $offDocuments > 0) {
                $this->refunded($withdrawal, $ctx, 'objednávka '.$order->number);
            }

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
                    $this->holdBack($w, $service, $ctx);
                }
                $this->refund($w, $ctx, $label);
                if ($w->refunded_at === null) {
                    return $w; // a document refused its credit note: nothing is cancelled until all of the refund is made
                }
            }
            if ($w->state === Withdrawal::TERMINATING && ! $gone && ! self::cancelled($service)) {
                $operation = $w->terminate_operation_id === null ? null : Operation::query()->find($w->terminate_operation_id);
                if ($operation !== null && ! self::failed($operation)) {
                    $this->holdBack($w, $service, $ctx); // whatever the running saga wrote into the suspension record, no free resume meanwhile

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
                $this->holdBack($w, $service, $ctx); // second guard: whatever the saga wrote, no free resume
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
     * (a document not paid yet is reduced instead). Each document once: what was given back is kept per document in
     * `basis.refunded_documents`, so a retry makes only the missing ones (and `creditedByLine` caps a line at what it has
     * left). A document that refuses its credit note, or is gone, keeps the withdrawal in `suspending` with `error` set and
     * finance told — the refund is never called done while part of it is missing. `refunded_at` is set, under the row lock,
     * only once every document is done.
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
        /** @var array<string, array{credit_note:?string, to_credit_minor:int, off_document_minor:int}> $done */
        $done = (array) data_get($w->basis, 'refunded_documents', []);
        $failed = [];
        foreach ($byDocument as $invoiceId => $rows) {
            if (isset($done[$invoiceId])) {
                continue; // given back on an earlier pass
            }
            $document = Invoice::query()->find($invoiceId);
            if ($document === null) {
                $failed[$invoiceId] = 'refund_document_missing';

                continue;
            }
            $amounts = $this->creditable($document, $rows, $after);
            if ($amounts === []) {
                $done[$invoiceId] = ['credit_note' => null, 'to_credit_minor' => 0, 'off_document_minor' => 0]; // somebody credited it meanwhile: nothing is left to give

                continue;
            }
            try {
                $given = $this->invoices->giveBack($document, $amounts, 'Odstoupení od smlouvy spotřebitelem — nevyužitá část', $ctx);
            } catch (DomainError $e) {
                $failed[$invoiceId] = $e->error;
                $this->audit->record($ctx, 'billing.withdrawal.refund', 'failed', ['withdrawal' => $w->id, 'invoice' => $document->number, 'reason' => $e->error], 'withdrawal', $w->id);

                continue;
            }
            $done[$invoiceId] = ['credit_note' => (string) $given['credit_note']->number, 'to_credit_minor' => (int) $given['to_credit_minor'], 'off_document_minor' => (int) $given['off_document_minor']];
        }
        $toCredit = (int) array_sum(array_column($done, 'to_credit_minor'));
        $offDocuments = (int) array_sum(array_column($done, 'off_document_minor'));
        $basis = array_merge((array) $w->basis, ['refunded_documents' => $done, 'credit_notes' => array_values(array_filter(array_column($done, 'credit_note')))]);
        if ($failed !== []) {
            $this->refundStalled($w, $basis, $failed, $toCredit, $offDocuments, $label);

            return;
        }
        unset($basis['refund_failed']);
        $w->forceFill(['state' => Withdrawal::REFUNDED, 'refunded_at' => now(), 'refund_minor' => $toCredit + $offDocuments, 'to_credit_minor' => $toCredit, 'off_document_minor' => $offDocuments, 'error' => null, 'basis' => $basis])->save();
        $this->refunded($w, $ctx, $label);
    }

    /**
     * The gross each line of the document still gets back: what was fixed at the notice, never more than the line has left.
     *
     * @param  array<string, array<string,mixed>>  $rows
     * @return array<string, array{gross:int, period_from:string, period_to:string}>
     */
    private function creditable(Invoice $document, array $rows, string $after): array
    {
        $credited = $this->invoices->creditedByLine($document);
        $amounts = [];
        foreach ($rows as $lineId => $row) {
            $line = $document->lines()->whereKey($lineId)->first();
            $gross = $line === null ? 0 : min((int) $row['refund_minor'], (int) $line->total_minor - (int) ($credited[$lineId]['total'] ?? 0)); // what somebody credited meanwhile is not given twice
            if ($gross > 0) {
                $amounts[$lineId] = ['gross' => $gross, 'period_from' => min(max((string) $row['period_from'], $after), (string) $row['period_to']), 'period_to' => (string) $row['period_to']];
            }
        }

        return $amounts;
    }

    /**
     * Part of the refund could not be made: what was made is kept, the rest waits for the finish command, and finance is told
     * once per new reason. The service stays switched off and is not cancelled meanwhile.
     *
     * @param  array<string,mixed>  $basis
     * @param  array<string,string>  $failed  invoice id => error code
     */
    private function refundStalled(Withdrawal $w, array $basis, array $failed, int $toCredit, int $offDocuments, string $label): void
    {
        $error = mb_substr('refund:'.implode(',', array_values(array_unique($failed))), 0, 200);
        if ($w->error !== $error) {
            $numbers = Invoice::query()->whereIn('id', array_keys($failed))->pluck('number')->map(fn ($n) => (string) $n)->all();
            $this->outbox->publish(GenericEvent::of('withdrawal.stalled', 'withdrawal', $w->id, ['service_id' => $w->service_id, 'label' => $label, 'step' => 'refund', 'error' => $error, 'documents' => $numbers, 'refunded' => false], $w->organization_id));
        }
        $w->forceFill(['error' => $error, 'to_credit_minor' => $toCredit, 'off_document_minor' => $offDocuments, 'basis' => array_merge($basis, ['refund_failed' => $failed])])->save();
    }

    /** The `withdrawal` hold on a switched-off service, set again whenever it is missing (written and audited only then). */
    private function holdBack(Withdrawal $w, Service $service, CommandContext $ctx): void
    {
        $fresh = Service::query()->find($service->id);
        if ($fresh !== null && ! in_array(SuspensionHold::WITHDRAWAL, SuspensionHold::holds($fresh), true)) {
            $this->services->imposeHold($fresh, SuspensionHold::WITHDRAWAL, 'withdrawal '.$w->id, $ctx);
        }
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

    /**
     * The confirmation of receipt (a mandatory legal notice). It names only what will really reach the credit (`to_credit`)
     * and, apart from it, what only makes unpaid documents smaller (`off_documents`); `estimate` says whether the amounts are
     * the service's estimate at the notice or, for a whole order, what the cancellation already moved.
     */
    private function accepted(Withdrawal $w, CommandContext $context, string $label, Money $toCredit, Money $offDocuments, bool $estimate): void
    {
        $this->audit->record($context->withScope($w->organization_id), 'billing.withdrawal.accept', 'succeeded', ['withdrawal' => $w->id, 'channel' => $w->channel, 'sent_at' => $w->sent_at->toIso8601String(), 'estimate_minor' => $w->refund_minor, 'to_credit_minor' => $toCredit->minor, 'off_document_minor' => $offDocuments->minor], 'withdrawal', $w->id);
        $this->outbox->publish(GenericEvent::of('withdrawal.accepted', 'withdrawal', $w->id, [
            'service_id' => $w->service_id, 'label' => $label, 'order_number' => Order::query()->whereKey($w->order_id)->value('number'), 'sent_at' => $w->sent_at->toIso8601String(),
            'refund' => Money::minor((int) $w->refund_minor, $w->currency), 'to_credit' => $toCredit, 'off_documents' => $offDocuments, 'estimate' => $estimate,
            'already_returned' => data_get($w->basis, 'already_returned') !== null, 'channel' => $w->channel,
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
