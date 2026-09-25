<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
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
 * terminated.
 *
 * What is returned is computed from the DOCUMENTS the customer paid — the lines of the service whose period has not run
 * out — never from the price list. It used to be `subscription.amount_minor × the unused share`: the list price of ONE
 * period without VAT. Somebody who had paid twelve months in advance got a share of one month back; everybody lost the
 * VAT they had paid; and somebody who had bought with an 80 % code got back more than they had ever paid. The return is
 * a credit note for exactly those lines and amounts, and the money goes back against the revenue and the VAT it had
 * earned — it used to be booked as money arriving at a bank called "chargeback", as purchased credit that could then be
 * paid out in cash even when the service had been bought with bonus credit.
 */
final class ChargebackService
{
    public const SETTING_PERCENT = 'chargeback.percent';

    public function __construct(
        private readonly SettingsStore $settings,
        private readonly InvoiceService $invoices,
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
     * What a cancellation now would return: for every document line of the service whose period has not run out, the
     * unused days of what the line has left (after earlier credit notes), times the share. Amounts are gross — what the
     * customer paid. Today counts as used.
     *
     * @return array{currency:string, period_end:?string, unused_minor:int, percent:int, refund_minor:int, subscription_id:?string, lines:list<array{line_id:string, invoice_id:string, number:?string, paid:bool, period_from:string, period_to:string, days:int, days_left:int, left_minor:int, unused_minor:int, refund_minor:int}>}
     */
    public function estimate(Service $service, ?int $percent = null, ?CarbonImmutable $asOf = null): array
    {
        $percent ??= $this->percent();
        $subscription = Subscription::query()->where('service_id', $service->id)->whereNotIn('state', [Subscription::CANCELLED])->orderByDesc('created_at')->first();
        $today = $asOf?->startOfDay() ?? CarbonImmutable::parse(AccountingClock::date()); // a withdrawal counts from the day the notice was sent (TASK-0025)
        $items = OrderItem::query()->where('service_id', $service->id)->get();
        $itemIds = $items->pluck('id')->all();
        // a change of the billing period was priced MINUS the unused rest of the period before it (PlanChangeService): every line
        // written before that order is settled, its remaining days must not come back a second time
        $periodChanges = $items->filter(fn (OrderItem $i) => (bool) data_get($i->config, 'plan_change.period_change', false))->pluck('id')->all();
        $settledBefore = $periodChanges === [] ? null : InvoiceLine::query()->whereIn('order_item_id', $periodChanges)->whereNull('corrects_line_id')->max('created_at');
        $documents = Invoice::query()->where('organization_id', $service->organization_id)->whereIn('type', ['statement', 'invoice'])->whereIn('state', [Invoice::ISSUED, Invoice::OVERDUE, Invoice::PAID])->get()->keyBy('id');
        $rows = [];
        $currency = (string) ($subscription->currency ?? 'CZK');
        if ($documents->isNotEmpty()) {
            $lines = InvoiceLine::query()->whereIn('invoice_id', $documents->keys()->all())->whereNull('corrects_line_id')->whereNotNull('period_to')->where('period_to', '>=', $today->toDateString())->where('total_minor', '>', 0)
                ->where(fn ($q) => $q->where('service_id', $service->id)->when($itemIds !== [], fn ($q) => $q->orWhereIn('order_item_id', $itemIds)))->orderBy('period_from')->get();
            foreach ($lines as $line) {
                if ($settledBefore !== null && $line->created_at !== null && $line->created_at->lt(Carbon::parse($settledBefore))) {
                    continue;
                }
                /** @var Invoice $document */
                $document = $documents->get($line->invoice_id);
                $credited = $this->invoices->creditedByLine($document)[$line->id]['total'] ?? 0;
                $left = (int) $line->total_minor - $credited;
                $from = CarbonImmutable::parse(($line->period_from ?? $document->supply_date ?? $today)->toDateString());
                $to = CarbonImmutable::parse($line->period_to->toDateString());
                $days = max(1, (int) round($from->diffInDays($to, true)) + 1);
                $daysLeft = $from->greaterThan($today) ? $days : max(0, min($days, (int) round($today->diffInDays($to, true))));
                $unused = min($left, (int) round((int) $line->total_minor * $daysLeft / $days));
                if ($unused <= 0) {
                    continue;
                }
                $currency = (string) $document->currency;
                $rows[] = ['line_id' => (string) $line->id, 'invoice_id' => (string) $document->id, 'number' => $document->number, 'paid' => $document->state === Invoice::PAID, 'period_from' => $from->toDateString(), 'period_to' => $to->toDateString(),
                    'days' => $days, 'days_left' => $daysLeft, 'left_minor' => $left, 'unused_minor' => $unused, 'refund_minor' => (int) round($unused * $percent / 100)];
            }
        }

        return ['currency' => $currency, 'period_end' => $subscription?->current_period_end?->toIso8601String(), 'unused_minor' => (int) array_sum(array_column($rows, 'unused_minor')), 'percent' => $percent,
            'refund_minor' => (int) array_sum(array_column($rows, 'refund_minor')), 'subscription_id' => $subscription?->id, 'lines' => $rows];
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
        self::assertNotWithdrawn($service->id);
        if (! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED], true)) {
            throw new DomainError('service_state_invalid', "A chargeback cannot be requested while the service is {$service->state}.", 409, ['state' => $service->state]);
        }
        if ($this->open($service) !== null) {
            throw new DomainError('chargeback_already_open', 'A chargeback request for this service is already waiting for a decision.', 409);
        }
        $estimate = $this->estimate($service);
        if ($estimate['subscription_id'] === null || $estimate['lines'] === []) {
            throw new DomainError('chargeback_no_subscription', 'The service has no paid period to return; nothing to refund. It can be cancelled the ordinary way.', 422);
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
        self::assertNotWithdrawn((string) $request->service_id);
        $service = Service::query()->withTrashed()->find($request->service_id);
        $estimate = $service !== null ? $this->estimate($service) : null;
        $request->forceFill([
            'state' => $decision === 'approve' ? ChargebackRequest::APPROVED : ChargebackRequest::REJECTED, 'decision_reason' => $reason !== null ? mb_substr($reason, 0, 2000) : null, 'decided_by' => $context->actorId, 'decided_at' => now(),
            'percent' => $this->percent(), 'unused_minor' => $estimate['unused_minor'] ?? $request->unused_minor, 'refund_minor' => $estimate['refund_minor'] ?? $request->refund_minor,
        ])->save();
        $this->audit->record($context->withScope($request->organization_id), 'chargeback.decide', 'succeeded', ['chargeback' => $request->id, 'decision' => $decision, 'reason' => $reason, 'percent' => $request->percent], 'service', $request->service_id);
        $this->outbox->publish(GenericEvent::of($decision === 'approve' ? 'chargeback.approved' : 'chargeback.rejected', 'chargeback', $request->id, ['service_id' => $request->service_id, 'label' => $service?->label ?: ($service?->name ?? ''), 'reason' => $reason, 'percent' => $request->percent, 'refund' => Money::minor($request->refund_minor, $request->currency)], $request->organization_id));

        return $request;
    }

    /**
     * The customer cancels an approved request's service: the refund is fixed now, the service terminates through
     * the ordinary saga (final backup included), and the credit is booked when the termination is confirmed.
     */
    public function cancelService(ChargebackRequest $request, CommandContext $context, ?string $authorizedPermission = null): ChargebackRequest
    {
        return DB::transaction(function () use ($request, $context, $authorizedPermission) {
            // the service row first, then the request — the order a withdrawal takes them in (it writes WITHDRAWN under the service
            // lock); the request is read again under its lock, so a copy from before a withdrawal cannot overwrite what it wrote
            $service = Service::query()->lockForUpdate()->find($request->service_id);
            $request = ChargebackRequest::query()->lockForUpdate()->find($request->id) ?? throw DomainError::notFound('chargeback');
            if ($request->state !== ChargebackRequest::APPROVED) {
                throw new DomainError('chargeback_not_approved', 'Support has not approved this request yet.', 409, ['state' => $request->state]);
            }
            if ($service === null) {
                throw DomainError::notFound('service');
            }
            self::assertNotWithdrawn($service->id);
            $estimate = $this->estimate($service, $request->percent);
            $request->forceFill(['unused_minor' => $estimate['unused_minor'], 'refund_minor' => $estimate['refund_minor'], 'currency' => $estimate['currency'], 'cancelled_at' => now(), 'basis' => $estimate['lines']])->save(); // the lines and amounts are fixed now: a renewal or a day more cannot change them
            if ($service->state === ServiceStateMachine::TERMINATED) {
                return $this->settle($request, $context);
            }
            $operation = $this->services->requestAction($service, 'terminate', $context, "chargeback:{$request->id}:terminate", ['reason' => 'chargeback '.$request->id, 'final_backup' => true], authorizedPermission: $authorizedPermission);
            $request->forceFill(['state' => ChargebackRequest::CANCELLING, 'operation_id' => $operation->id])->save();
            $this->audit->record($context->withScope($service->organization_id, $service->project_id), 'chargeback.cancel', 'succeeded', ['chargeback' => $request->id, 'operation_id' => $operation->id, 'refund_minor' => $request->refund_minor], 'service', $service->id);

            return $request;
        }, 3);
    }

    /**
     * A consumer who withdrew from the contract has had the whole unused part back (TASK-0025, review round 2): a chargeback on
     * top of it would count the days already used as unused and give a share of them back a second time. A withdrawal row
     * stays for good, so this holds however far the unwinding got (a stalled one included).
     *
     * @throws DomainError `withdrawn` (409)
     */
    private static function assertNotWithdrawn(string $serviceId): void
    {
        if (Withdrawal::query()->where('service_id', $serviceId)->exists()) {
            throw new DomainError('withdrawn', 'Od smlouvy k této službě bylo odstoupeno a nevyužitá část už byla vrácena; chargeback k ní nelze žádat ani vyřídit.', 409);
        }
    }

    /** Called when a service is terminated: a chargeback waiting for it gets its credit. */
    public function settleForService(string $serviceId, CommandContext $context): ?ChargebackRequest
    {
        $request = ChargebackRequest::query()->where('service_id', $serviceId)->where('state', ChargebackRequest::CANCELLING)->first();

        return $request === null ? null : $this->settle($request, $context);
    }

    /**
     * The credit notes and the money, once. The request row is locked and its state read again inside the transaction: an
     * event delivered twice must not give the same share back twice (a second credit note of the same amount would still
     * fit into what the line has left).
     */
    private function settle(ChargebackRequest $request, CommandContext $context): ChargebackRequest
    {
        return DB::transaction(function () use ($request, $context) {
            $request = ChargebackRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->state === ChargebackRequest::REFUNDED) {
                return $request;
            }
            $scoped = $context->withScope($request->organization_id);
            $basis = array_values(array_filter((array) (data_get($request->basis, 'lines') ?? $request->basis), 'is_array'));
            $byDocument = [];
            foreach ($basis as $row) {
                if ((int) ($row['refund_minor'] ?? 0) > 0) {
                    $byDocument[(string) $row['invoice_id']][(string) $row['line_id']] = ['gross' => (int) $row['refund_minor'], 'period_from' => max((string) $row['period_from'], AccountingClock::date($request->cancelled_at)), 'period_to' => (string) $row['period_to']];
                }
            }
            $toCredit = 0;
            $offDocuments = 0;
            $notes = [];
            foreach ($byDocument as $invoiceId => $amounts) {
                $document = Invoice::query()->find($invoiceId);
                if ($document === null) {
                    continue;
                }
                try {
                    $given = $this->invoices->giveBack($document, $amounts, "Zrušení služby — vráceno {$request->percent} % nevyužitého období", $scoped);
                } catch (DomainError $e) { // somebody credited the line in the meantime: what is gone is not given back twice, the rest of the request still settles
                    $this->audit->record($scoped, 'chargeback.refund', 'failed', ['chargeback' => $request->id, 'invoice' => $document->number, 'reason' => $e->error], 'service', $request->service_id);

                    continue;
                }
                $toCredit += $given['to_credit_minor'];
                $offDocuments += $given['off_document_minor'];
                $notes[] = (string) $given['credit_note']->number;
            }
            $request->forceFill(['state' => ChargebackRequest::REFUNDED, 'refunded_at' => now(), 'refund_minor' => $toCredit + $offDocuments, 'basis' => ['lines' => $basis, 'credit_notes' => $notes, 'to_credit_minor' => $toCredit, 'off_document_minor' => $offDocuments]])->save();
            $service = Service::query()->withTrashed()->find($request->service_id);
            $this->audit->record($scoped, 'chargeback.refund', 'succeeded', ['chargeback' => $request->id, 'refund_minor' => $request->refund_minor, 'to_credit_minor' => $toCredit, 'off_document_minor' => $offDocuments, 'credit_notes' => $notes, 'currency' => $request->currency], 'service', $request->service_id);
            $this->outbox->publish(GenericEvent::of('chargeback.refunded', 'chargeback', $request->id, ['service_id' => $request->service_id, 'label' => $service?->label ?: ($service?->name ?? ''), 'refund' => Money::minor($request->refund_minor, $request->currency), 'percent' => $request->percent, 'credit_notes' => $notes, 'to_credit' => Money::minor($toCredit, $request->currency), 'off_documents' => Money::minor($offDocuments, $request->currency)], $request->organization_id));

            return $request;
        }, 3);
    }

    /** @return array<string,mixed> */
    public function present(ChargebackRequest $request): array
    {
        return [
            'id' => $request->id, 'service_id' => $request->service_id, 'organization_id' => $request->organization_id, 'state' => $request->state, 'reason' => $request->reason, 'decision_reason' => $request->decision_reason,
            'percent' => $request->percent, 'currency' => $request->currency, 'unused' => Money::minor((int) $request->unused_minor, $request->currency), 'refund' => Money::minor((int) $request->refund_minor, $request->currency),
            'requested_at' => $request->created_at?->toIso8601String(), 'decided_at' => $request->decided_at?->toIso8601String(), 'cancelled_at' => $request->cancelled_at?->toIso8601String(), 'refunded_at' => $request->refunded_at?->toIso8601String(), 'operation_id' => $request->operation_id,
            'credit_notes' => array_values((array) data_get($request->basis, 'credit_notes', [])), 'to_credit' => Money::minor((int) data_get($request->basis, 'to_credit_minor', 0), $request->currency), 'off_documents' => Money::minor((int) data_get($request->basis, 'off_document_minor', 0), $request->currency),
        ];
    }
}
