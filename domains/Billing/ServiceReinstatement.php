<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\RatedUsage;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\LegalHold;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Pay and restore (owner decision 23, TASK-0025). A service that ended — cancelled by dunning, deactivated because its
 * subscription ran out, or cancelled by the customer — waits out its restore window switched off. Until now only a
 * suspension for an unpaid invoice came back by itself once paid; after a cancellation nothing did: a paid invoice on a
 * TERMINATED case restored nothing and the purge then removed a paid service, an expired subscription could be neither
 * paid for nor resumed, and a cancellation the customer took back ran unbilled for good.
 *
 * Here the customer (or their payment) brings it back: what it owes is worked out (`quote`), paid — an overdue invoice
 * through the ordinary invoice payment, a new period from the credit — and the service comes back through the ordinary
 * resume, which lifts only the `payment` hold. Money never lifts a quarantine or a staff hold, never touches a service
 * that is gone, and is taken once. Everything here is behind the default-off automation rule `services.reinstate`.
 */
final class ServiceReinstatement
{
    public const RULE = 'services.reinstate';

    private const MONEY_ERRORS = ['insufficient_funds', 'budget_exceeded', 'budget_single_service_exceeded'];

    private const REFUSALS = [
        'disabled' => 'Obnovení po úhradě zatím není zapnuté; napište prosím podpoře.',
        'not_cancelled' => 'Služba není zrušená — není co obnovovat.',
        'window_closed' => 'Lhůta na obnovení služby už uplynula.',
        'service_state' => 'Službu v tomto stavu nelze obnovit.',
        'addon' => 'Doplněk se obnovuje se službou, ke které patří.',
        'included_child' => 'Tento web patří k jiné službě; obnovte prosím tu.',
        'legal_hold' => 'Služba je pod právním zadržením; obnovit ji může jen podpora.',
        'held' => 'Službu drží blokace, kterou platba nezruší (porušení podmínek nebo zásah našeho týmu). Napište prosím podpoře.',
        'no_subscription' => 'Služba nemá předplatné, které by šlo obnovit; napište prosím podpoře.',
        'operation_in_progress' => 'Na službě právě běží jiná operace; zkuste to prosím za chvíli.',
    ];

    public function __construct(
        private readonly AutomationLedger $ledger,
        private readonly SubscriptionService $subscriptions,
        private readonly WalletService $wallets,
        private readonly RatingService $rating,
        private readonly DunningService $dunning,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    public function enabled(): bool
    {
        return $this->ledger->enabled(self::RULE);
    }

    /** Null when the service can be brought back now, otherwise the reason it cannot (a key of REFUSALS). */
    public function eligibility(Service $service): ?string
    {
        if (! $this->enabled()) {
            return 'disabled';
        }
        if ($service->trashed() || in_array($service->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING], true)) {
            return 'window_closed'; // purged: only the archive is left, restored into a new service
        }
        if ($service->terminate_at === null || ! is_array(data_get($service->tags, 'deletion'))) {
            return 'not_cancelled';
        }
        if (! $service->terminate_at->isFuture()) {
            return 'window_closed';
        }
        if ($service->state !== ServiceStateMachine::SUSPENDED) {
            return 'service_state';
        }
        if ($service->family === 'addon') {
            return 'addon';
        }
        if ((string) data_get($service->tags, 'included.ended_by', '') !== '') {
            return 'included_child';
        }
        if (LegalHold::coversService($service)) {
            return 'legal_hold';
        }
        if (array_diff(SuspensionHold::holds($service), [SuspensionHold::PAYMENT]) !== []) {
            return 'held';
        }
        if ($this->subscriptionOf($service) === null) {
            return 'no_subscription';
        }
        if (Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists()) {
            return 'operation_in_progress';
        }

        return null;
    }

    /**
     * What bringing the service back costs now. Overdue invoices are paid through the invoice payment; a new period (when
     * the paid one ran out, or was given back by a chargeback or a credit note) and unpaid usage are charged here.
     *
     * @return array{eligible:bool, reason:?string, grace_until:?string, outstanding_invoices:list<array{id:string, number:?string, outstanding:Money}>, unpaid_usage:Money, covered_until:?string, renewal:?array{amount:Money, net:Money, tax:Money, period_from:string, period_to:string}, total_due:Money, wallet_available:Money, shortfall:Money, mode:string, addons_not_restored:list<string>, requested_at:?string}
     */
    public function quote(Service $service): array
    {
        $reason = $this->eligibility($service);
        $subscription = $this->subscriptionOf($service);
        $organization = Organization::query()->find($service->organization_id);
        $currency = (string) ($subscription->currency ?? $organization->currency ?? 'CZK');
        $invoices = $this->outstandingInvoices($service, $currency);
        $unpaid = Money::minor((int) RatedUsage::query()->where('service_id', $service->id)->whereNull('charged_transaction_id')->where('amount_minor', '>', 0)->sum('amount_minor'), $currency);
        $covered = $this->coveredUntil($service, $subscription);
        $metered = SubscriptionService::isMetered(Product::query()->where('key', $service->product_key)->first());
        $renewal = null;
        if ($subscription !== null && ! $metered && ($covered === null || ! $covered->isFuture())) {
            $price = $this->subscriptions->restartPrice($subscription, $service);
            $renewal = ['amount' => $price['gross'], 'net' => $price['net'], 'tax' => $price['tax'], 'period_from' => $price['start']->toIso8601String(), 'period_to' => $price['end']->toIso8601String()];
        }
        $invoicesDue = array_sum(array_map(fn (array $i) => $i['outstanding']->minor, $invoices));
        $chargeNow = $unpaid->minor + ($renewal['amount']->minor ?? 0);
        $postpaid = $organization !== null && $organization->billing_mode === 'postpaid' && $this->wallets->approvedCreditLine($organization->id, $currency)->isPositive();
        $available = 0;
        if ($organization !== null) {
            $balances = $this->wallets->balances($organization, $currency);
            $available = $balances['available']->add($balances['credit_line'])->minor; // what a charge may use: promo credit does not pay a renewal
        }

        return [
            'eligible' => $reason === null, 'reason' => $reason, 'grace_until' => $service->terminate_at?->toIso8601String(),
            'outstanding_invoices' => $invoices, 'unpaid_usage' => $unpaid, 'covered_until' => $covered?->toIso8601String(), 'renewal' => $renewal,
            'total_due' => Money::minor($invoicesDue + $chargeNow, $currency), 'wallet_available' => Money::minor(max(0, $available), $currency),
            'shortfall' => Money::minor($postpaid ? 0 : max(0, $chargeNow - max(0, $available)), $currency),
            'mode' => $renewal === null ? 'covered' : ($postpaid ? 'postpaid' : 'wallet'),
            'addons_not_restored' => Service::query()->where('family', 'addon')->where('tags->parent_service_id', $service->id)->whereNotNull('terminate_at')->pluck('name')->map(fn ($n) => (string) $n)->all(),
            'requested_at' => data_get($service->tags, 'reinstatement.requested_at'),
        ];
    }

    /**
     * Bring the service back, paying what it owes. Runs inside the bus's transaction (the customer's command) or its own
     * (a payment arrived); the service row is locked, so two requests cannot both charge.
     *
     * @return array<string,mixed> state `restoring` | `awaiting_payment` | `awaiting_invoices` | `resume_failed`
     *
     * @throws DomainError `reinstatement_refused` (409, `reason`)
     */
    public function reinstate(Service $service, CommandContext $context, string $key): array
    {
        return DB::transaction(function () use ($service, $context, $key) {
            $service = Service::query()->lockForUpdate()->find($service->id);
            if ($service === null) {
                throw DomainError::notFound('service');
            }
            $reason = $this->eligibility($service);
            if ($reason !== null) {
                throw new DomainError('reinstatement_refused', self::REFUSALS[$reason] ?? $reason, 409, ['reason' => $reason]);
            }
            $quote = $this->quote($service);
            if ($quote['outstanding_invoices'] !== []) { // an invoice is paid through the invoice payment; the restore follows it
                $this->recordWish($service, $context, $quote, $key);

                return ['state' => 'awaiting_invoices', 'invoices' => $quote['outstanding_invoices'], 'total_due' => $quote['total_due'], 'grace_until' => $quote['grace_until']];
            }
            if ($quote['unpaid_usage']->isPositive()) {
                $this->rating->chargeDeferred($service->organization_id, $context);
                if (RatedUsage::query()->where('service_id', $service->id)->whereNull('charged_transaction_id')->where('amount_minor', '>', 0)->exists()) {
                    $this->recordWish($service, $context, $quote, $key);

                    return ['state' => 'awaiting_payment', 'cause' => 'credit', 'shortfall' => $quote['shortfall'], 'total_due' => $quote['total_due'], 'grace_until' => $quote['grace_until']];
                }
            }
            $subscription = $this->subscriptionOf($service);
            $cancellation = (string) data_get($service->tags, 'deletion.operation_id', '') ?: (string) $service->terminate_at?->timestamp;
            try {
                // one key per cancellation: a second request, a retried event and the customer's click can never charge twice
                $billing = $this->subscriptions->reinstate($subscription, $service, $context, "sub_reinstate:{$subscription->id}:{$cancellation}", ! $this->refundedSinceCancellation($service));
            } catch (DomainError $e) {
                if (! in_array($e->error, self::MONEY_ERRORS, true)) {
                    throw $e;
                }
                $this->recordWish($service, $context, $quote, $key);
                $this->outbox->publish(GenericEvent::of('service.reinstatement.awaiting_payment', 'service', $service->id, ['label' => self::label($service), 'amount' => $quote['total_due'], 'shortfall' => $quote['shortfall'], 'grace_until' => $quote['grace_until'], 'cause' => $e->error === 'insufficient_funds' ? 'credit' : 'budget'], $service->organization_id));

                return ['state' => 'awaiting_payment', 'cause' => $e->error === 'insufficient_funds' ? 'credit' : 'budget', 'shortfall' => $quote['shortfall'], 'total_due' => $quote['total_due'], 'grace_until' => $quote['grace_until']];
            }

            return $this->bringBack($service, $context, $billing, $cancellation);
        }, 3);
    }

    /**
     * An invoice was paid. A service a TERMINATED dunning case cancelled for this invoice comes back when nothing more is
     * owed (the paid invoice covers its period), or when the customer asked for the restore; otherwise the customer hears
     * what is still missing. Never throws: a listener that fails would deliver the payment event again to every listener.
     */
    public function afterInvoicePaid(string $organizationId, string $invoiceId, CommandContext $context): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        $dunned = DunningCase::query()->where('organization_id', $organizationId)->where('invoice_id', $invoiceId)->where('state', DunningCase::TERMINATED)->whereNotNull('service_id')->pluck('service_id')->all();
        $restored = 0;
        foreach ($this->candidates($organizationId, $dunned) as $service) {
            $restored += $this->settle($service, $context, 'invoice:'.$invoiceId, in_array($service->id, $dunned, true));
        }

        return $restored;
    }

    /** Credit arrived: a restore the customer asked for and could not pay goes ahead now. A top-up alone never charges a service nobody asked to bring back. */
    public function afterTopUp(string $organizationId, CommandContext $context): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        $restored = 0;
        foreach ($this->candidates($organizationId, []) as $service) {
            $restored += $this->settle($service, $context, 'topup', false);
        }

        return $restored;
    }

    /**
     * The customer may not undo a cancellation for free that they were paid to give up, or whose paid time has run out
     * (TASK-0025): a chargeback returned the unused period, so resuming would keep both the refund and the service; and a
     * resume after the period ended would run unbilled. Staff and the platform are not asked here.
     *
     * @throws DomainError `chargeback_cancelled` (409) with the rule off, `reinstatement_payment_required` (402) with it on
     */
    public function assertCustomerMayResume(Service $service, CommandContext $context): void
    {
        if (self::actsForPlatform($context) || $service->terminate_at === null || ! is_array(data_get($service->tags, 'deletion'))) {
            return;
        }
        if (in_array(SuspensionHold::WITHDRAWAL, SuspensionHold::holds($service), true)) {
            return; // withdrawn and refunded: no price brings it back — the hold refuses the resume with its own message
        }
        if ($this->enabled()) {
            $quote = $this->quote($service);
            if ($quote['total_due']->isPositive()) {
                throw new DomainError('reinstatement_payment_required', 'Služba je zrušená a zaplacené období skončilo'.($quote['outstanding_invoices'] !== [] ? ' nebo zbývá uhradit fakturu' : '').'. Obnovíte ji zaplacením: '.$quote['total_due']->format('cs').'.', 402, ['quote' => $quote, 'pay' => "/v1/services/{$service->id}/reinstate"]);
            }

            return;
        }
        if ($this->chargebackEndedThisCancellation($service)) {
            throw new DomainError('chargeback_cancelled', 'Služba byla zrušena s vrácením kreditu (chargeback); obnovit ji může jen podpora.', 409, ['grace_until' => $service->terminate_at->toIso8601String()]);
        }
    }

    /**
     * A carried site is never purged while the service that carries it is no longer being deleted: the parent was paid for
     * and brought back, and its resume brings the site back too — the nightly purge must not win that race.
     *
     * @throws DomainError `parent_reinstated` (409)
     */
    public function assertPurgeAllowed(Service $service): void
    {
        $parentId = (string) data_get($service->tags, 'included.ended_by', '');
        if ($parentId === '' || ! $this->enabled()) {
            return;
        }
        $parent = Service::query()->find($parentId);
        if ($parent !== null && $parent->terminate_at === null && ! in_array($parent->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING], true)) {
            throw new DomainError('parent_reinstated', 'Služba, ke které web patří, byla obnovena; web se s ní vrací a nelze jej odstranit.', 409, ['parent_service_id' => $parent->id]);
        }
    }

    /** A refund returned (part of) the paid period after this cancellation began: that period does not cover a restore any more. */
    public function refundedSinceCancellation(Service $service): bool
    {
        if ($this->chargebackEndedThisCancellation($service)) {
            return true;
        }
        $since = self::cancellationStarted($service);
        if ($since === null) {
            return false;
        }
        $lines = InvoiceLine::query()->where('service_id', $service->id)->whereNull('corrects_line_id')->select('id');

        return InvoiceLine::query()->whereIn('corrects_line_id', $lines)->where('created_at', '>=', $since)->exists();
    }

    /**
     * @param  array<string,mixed>  $billing  from SubscriptionService::reinstate
     * @return array<string,mixed>
     */
    private function bringBack(Service $service, CommandContext $context, array $billing, string $cancellation): array
    {
        $services = app(ServiceService::class);
        $tags = (array) $service->tags;
        unset($tags['reinstatement']);
        $service->forceFill(['tags' => $tags])->save();
        $services->undoScheduledDeletion($service, $context); // first: from now on the purge cannot take a paid service, whatever the resume does
        $operationId = null;
        $failure = null;
        try {
            // the ordinary resume, as the platform: it lifts the `payment` hold and nothing else, and brings the carried sites back
            $operationId = $services->requestAction($service, 'resume', CommandContext::system('reinstated after payment')->withScope($service->organization_id), "reinstate:{$service->id}:{$cancellation}", ['reason' => 'reinstated after payment', 'lift' => SuspensionHold::PAYMENT])->id;
        } catch (DomainError $e) {
            $failure = $e->error; // the money and the undone deletion stay; with no hold left the customer or staff can resume
        }
        foreach (DunningCase::query()->where('service_id', $service->id)->where('state', DunningCase::TERMINATED)->get() as $case) {
            $this->dunning->noteReinstated($case, $service->id, $context);
        }
        $detail = ['operation_id' => $operationId, 'billing' => $billing['result'], 'document_id' => $billing['document_id'], 'amount' => $billing['amount'], 'period_end' => $billing['period_end'], 'error' => $failure];
        $this->audit->record($context->withScope($service->organization_id), 'service.reinstate', $failure === null ? 'succeeded' : 'failed', $detail, 'service', $service->id);
        $this->outbox->publish($failure === null
            ? GenericEvent::of('service.reinstated', 'service', $service->id, ['label' => self::label($service), 'amount' => $billing['amount'], 'billing' => $billing['result'], 'document_id' => $billing['document_id'], 'period_end' => $billing['period_end'], 'operation_id' => $operationId], $service->organization_id)
            : GenericEvent::of('service.reinstatement.failed', 'service', $service->id, ['label' => self::label($service), 'error' => $failure, 'billing' => $billing['result'], 'document_id' => $billing['document_id']], $service->organization_id));

        return ['state' => $failure === null ? 'restoring' : 'resume_failed', 'operation_id' => $operationId, 'billing' => $billing['result'], 'document_id' => $billing['document_id'], 'charged' => $billing['amount'], 'period_end' => $billing['period_end'], 'error' => $failure];
    }

    /**
     * One service after a payment; 1 when it came back.
     */
    private function settle(Service $service, CommandContext $context, string $trigger, bool $dunnedForThis): int
    {
        try {
            $quote = $this->quote($service);
            if (! $quote['eligible'] || $quote['outstanding_invoices'] !== []) {
                return 0;
            }
            $wish = data_get($service->tags, 'reinstatement');
            $chargeNow = $quote['total_due']->minor;
            if (is_array($wish)) {
                if ($chargeNow > (int) ($wish['quoted_total_minor'] ?? 0) && $chargeNow > 0) { // the price moved since the customer asked: they are told, not charged
                    $this->outbox->publish(GenericEvent::of('service.reinstatement.awaiting_payment', 'service', $service->id, ['label' => self::label($service), 'amount' => $quote['total_due'], 'shortfall' => $quote['shortfall'], 'grace_until' => $quote['grace_until'], 'cause' => 'price'], $service->organization_id));

                    return 0;
                }
                if ($quote['shortfall']->isPositive()) {
                    return 0; // still short; the next top-up tries again
                }
            } elseif (! $dunnedForThis) {
                return 0; // nobody asked, and it was not this payment the service was cancelled for
            } elseif ($chargeNow > 0) {
                $this->outbox->publish(GenericEvent::of('service.reinstatement.awaiting_payment', 'service', $service->id, ['label' => self::label($service), 'amount' => $quote['total_due'], 'shortfall' => $quote['shortfall'], 'grace_until' => $quote['grace_until'], 'cause' => 'period_ended'], $service->organization_id));

                return 0;
            }
            $result = $this->reinstate($service, $context, "reinstate:{$trigger}:{$service->id}");

            return in_array($result['state'] ?? '', ['restoring', 'resume_failed'], true) ? 1 : 0;
        } catch (DomainError $e) {
            $this->audit->record($context->withScope($service->organization_id), 'service.reinstate', 'failed', ['trigger' => $trigger, 'error' => $e->error], 'service', $service->id);

            return 0;
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Services of the organization still in their window that a payment could concern: those named, and those whose
     * customer asked for a restore.
     *
     * @param  list<string>  $named
     * @return list<Service>
     */
    private function candidates(string $organizationId, array $named): array
    {
        return Service::query()->where('organization_id', $organizationId)->whereNotNull('terminate_at')->where('terminate_at', '>', now())
            ->where('state', ServiceStateMachine::SUSPENDED)->orderBy('terminate_at')->limit(200)->get()
            ->filter(fn (Service $s) => in_array($s->id, $named, true) || is_array(data_get($s->tags, 'reinstatement')))->values()->all();
    }

    /** @param array<string,mixed> $quote */
    private function recordWish(Service $service, CommandContext $context, array $quote, string $key): void
    {
        $tags = (array) $service->tags;
        $tags['reinstatement'] = [
            'requested_at' => data_get($tags, 'reinstatement.requested_at') ?? now()->toIso8601String(), 'by' => $context->actorType.($context->actorId !== null ? ':'.$context->actorId : ''),
            'quoted_total_minor' => $quote['total_due']->minor, 'currency' => $quote['total_due']->currency->value, 'key' => mb_substr($key, 0, 160),
        ];
        $service->forceFill(['tags' => $tags])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.reinstate.requested', 'succeeded', ['total_due' => $quote['total_due'], 'shortfall' => $quote['shortfall'], 'invoices' => count($quote['outstanding_invoices'])], 'service', $service->id);
    }

    private function subscriptionOf(Service $service): ?Subscription
    {
        return Subscription::query()->where('service_id', $service->id)->orderByDesc('created_at')->first();
    }

    /** @return list<array{id:string, number:?string, outstanding:Money}> */
    private function outstandingInvoices(Service $service, string $currency): array
    {
        $ids = DunningCase::query()->where('service_id', $service->id)->whereNotNull('invoice_id')->pluck('invoice_id')
            ->merge(InvoiceLine::query()->where('service_id', $service->id)->whereNull('corrects_line_id')->pluck('invoice_id'))->unique()->values()->all();
        if ($ids === []) {
            return [];
        }
        $rows = [];
        foreach (Invoice::query()->where('organization_id', $service->organization_id)->where('type', 'invoice')->whereIn('state', [Invoice::ISSUED, Invoice::OVERDUE])->whereIn('id', $ids)->orderBy('issued_at')->get() as $invoice) {
            $left = $invoice->outstanding();
            if ($left->isPositive()) {
                $rows[] = ['id' => (string) $invoice->id, 'number' => $invoice->number, 'outstanding' => $left];
            }
        }

        return $rows;
    }

    private function coveredUntil(Service $service, ?Subscription $subscription): ?Carbon
    {
        $end = $subscription?->current_period_end;
        if ($end === null) {
            return null;
        }

        return $this->refundedSinceCancellation($service) ? (self::cancellationStarted($service) ?? now())->min($end) : $end;
    }

    private function chargebackEndedThisCancellation(Service $service): bool
    {
        $operation = (string) (self::cancellation($service)['operation_id'] ?? '');
        $since = self::cancellationStarted($service);

        return ChargebackRequest::query()->where('service_id', $service->id)->whereIn('state', [ChargebackRequest::CANCELLING, ChargebackRequest::REFUNDED])
            ->where(function ($q) use ($operation, $since) {
                $q->when($operation !== '', fn ($w) => $w->orWhere('operation_id', $operation))
                    ->when($since !== null, fn ($w) => $w->orWhere(fn ($x) => $x->whereNotNull('cancelled_at')->where('cancelled_at', '>=', $since->copy()->subDay())));
            })->when($operation === '' && $since === null, fn ($q) => $q->whereRaw('1 = 0'))->exists();
    }

    /** When this cancellation began: the suspension that preceded it (a withdrawal suspends first) or the cancellation itself. */
    private static function cancellationStarted(Service $service): ?Carbon
    {
        $requested = self::cancellation($service)['requested_at'] ?? null;
        $at = is_string($requested) && $requested !== '' ? Carbon::parse($requested) : null;
        if ($service->suspended_at !== null && ($at === null || $service->suspended_at->lt($at))) {
            $at = $service->suspended_at->copy();
        }

        return $at;
    }

    /**
     * The record of the cancellation: the one running, or — right after it was taken back — the one just undone.
     *
     * @return array<string,mixed>
     */
    private static function cancellation(Service $service): array
    {
        $record = data_get($service->tags, 'deletion');
        if (! is_array($record) && $service->terminate_at === null) {
            $record = data_get($service->tags, 'deletion_cancelled');
        }

        return is_array($record) ? $record : [];
    }

    /** Staff and the platform itself decide on their own authority; a customer, their API tokens and assistants do not. */
    private static function actsForPlatform(CommandContext $context): bool
    {
        if ($context->actorType === 'system') {
            return true;
        }

        return $context->actorType === 'user' && $context->actorId !== null && (bool) User::query()->whereKey($context->actorId)->value('is_staff');
    }

    private static function label(Service $service): string
    {
        return (string) ($service->label ?: ($service->hostname ?: $service->name));
    }
}
