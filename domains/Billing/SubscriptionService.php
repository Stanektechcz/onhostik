<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Subscription lifecycle for services (blueprint §49.1): prepaid renewals charged
 * from the wallet with a credit statement, postpaid renewals invoiced with a
 * receivable, metered products only roll their monthly cap period. Failed
 * renewals open a dunning case instead of touching the resource directly.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly TaxEngine $tax,
        private readonly InvoiceService $invoices,
        private readonly DunningService $dunning,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** Called when a service becomes ACTIVE. Idempotent per service. */
    public function ensureForService(Service $service, ?OrderItem $item, CommandContext $context): Subscription
    {
        $existing = Subscription::query()->where('service_id', $service->id)->first();
        if ($existing !== null) {
            return $existing;
        }
        $organization = Organization::query()->findOrFail($service->organization_id);
        $product = Product::query()->where('key', $service->product_key)->first();
        $config = (array) ($item?->config ?? []);
        $currency = (string) ($config['currency'] ?? $organization->currency);
        $metered = self::isMetered($product);
        $periodsBilled = max(1, (int) ($config['periods_billed'] ?? 1));
        $start = ($service->activated_at ?? now())->copy();
        if ($metered) {
            $price = $item?->price_id ? Price::query()->find($item->price_id) : null;
            $amount = $price?->monthlyCap()?->minor ?? (int) ($config['renewal_net_minor'] ?? 0);
            $period = 'month';
            $end = $start->copy()->endOfMonth();
            $next = $end->copy();
        } else {
            $period = in_array($item?->period, ['month', 'year'], true) ? $item->period : 'month';
            $amount = (int) round(((int) ($config['renewal_net_minor'] ?? 0)) / $periodsBilled);
            $end = BillingPeriod::end($start, $period, $periodsBilled);
            $next = $end->copy()->subDays((int) config('onhost.billing.renew_lead_days', 7));
        }
        $subscription = Subscription::query()->create([
            'organization_id' => $organization->id, 'service_id' => $service->id, 'plan_version_id' => $service->plan_version_id, 'price_id' => $item?->price_id, 'currency' => $currency, 'period' => $period, 'amount_minor' => $amount,
            'state' => Subscription::ACTIVE, 'current_period_start' => $start, 'current_period_end' => $end, 'next_renewal_at' => $next, 'auto_renew' => (bool) ($organization->auto_renew_default ?? true), 'renewal_priority' => 'normal',
        ]);
        $service->forceFill(['subscription_id' => $subscription->id])->save();
        $this->outbox->publish(GenericEvent::of('subscription.created', 'subscription', $subscription->id, ['service_id' => $service->id, 'period' => $period, 'amount' => Money::minor($amount, $currency), 'metered' => $metered, 'current_period_end' => $end->toIso8601String()], $organization->id));

        return $subscription;
    }

    public static function isMetered(?Product $product): bool
    {
        return $product !== null && in_array($product->billing_model, ['hourly', 'daily', 'metered'], true);
    }

    /** @return array{renewed:int, invoiced:int, failed:int, rolled:int, cancelled:int} */
    public function tick(?CommandContext $context = null): array
    {
        $context ??= CommandContext::system('subscription renewals');
        $stats = ['renewed' => 0, 'invoiced' => 0, 'failed' => 0, 'rolled' => 0, 'cancelled' => 0];
        $due = Subscription::query()->whereNotNull('service_id')->whereIn('state', [Subscription::ACTIVE, Subscription::PAST_DUE])->where('next_renewal_at', '<=', now())->orderBy('next_renewal_at')->limit(500)->get();
        foreach ($due as $subscription) {
            $service = Service::query()->find($subscription->service_id);
            // an add-on whose service is gone or ending renews no more than that service does (TASK-0022: a raise delivered after
            // the service's add-ons were cancelled billed on for a service that was gone)
            if ($service === null || in_array($service->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING, ServiceStateMachine::FAILED], true) || Addons::parentEnded($service)) {
                $subscription->forceFill(['state' => Subscription::CANCELLED])->save();
                $stats['cancelled']++;

                continue;
            }
            if ($this->addonWaitsForParent($service)) { // tried again tomorrow, after the parent's own retry (review round 2)
                $subscription->forceFill(['next_renewal_at' => now()->addDay()])->save();

                continue;
            }
            if ($subscription->cancel_at_period_end && $subscription->current_period_end <= now()) {
                $this->expire($subscription, $service, $context);
                $stats['cancelled']++;

                continue;
            }
            $product = Product::query()->where('key', $service->product_key)->first();
            if (self::isMetered($product)) {
                $this->rollMetered($subscription);
                $stats['rolled']++;

                continue;
            }
            if (! $subscription->auto_renew) {
                if ($subscription->current_period_end <= now()) {
                    $this->expire($subscription, $service, $context);
                    $stats['cancelled']++;
                } else {
                    $subscription->forceFill(['next_renewal_at' => $subscription->current_period_end])->save();
                }

                continue;
            }
            $result = $this->renew($subscription, $service, $context->withScope($service->organization_id));
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * An add-on's renewal waits while its parent is failed (an operator brings it back; ending the add-on for good left it
     * delivered and unbilled) or unpaid (its renewal past due, or stopped by dunning): money that comes in goes to the service
     * the customer is about to lose, not to an add-on of it that fell due a little earlier (review round 2).
     */
    private function addonWaitsForParent(Service $addon): bool
    {
        $parent = Addons::parentOf($addon);
        if ($parent === null) {
            return false;
        }

        return $parent->state === ServiceStateMachine::FAILED
            || in_array(SuspensionHold::PAYMENT, SuspensionHold::holds($parent), true)
            || Subscription::query()->where('service_id', $parent->id)->where('state', Subscription::PAST_DUE)->exists();
    }

    /** @return 'renewed'|'invoiced'|'failed' */
    public function renew(Subscription $subscription, Service $service, CommandContext $context): string
    {
        $organization = Organization::query()->findOrFail($subscription->organization_id);
        $periodKey = $subscription->current_period_end->format('Ymd');
        $newStart = $subscription->current_period_end->copy();
        $newEnd = BillingPeriod::end($newStart, (string) $subscription->period, 1, self::anchorDay($service)); // anchored on the day the service started: 31 Jan → 28 Feb → 31 Mar
        ['line' => $line, 'invoice_line' => $invoiceLine] = $this->periodLine($subscription, $service, $organization, $newStart, $newEnd);
        if ($organization->billing_mode === 'postpaid' && $this->wallets->approvedCreditLine($organization->id, $subscription->currency)->isPositive()) {
            $draft = $this->invoices->draft($organization, 'invoice', $subscription->currency, [$invoiceLine], $context, null, ['payment_method' => 'invoice', 'postpaid' => true, 'subscription_id' => $subscription->id, 'renewal_period' => $periodKey]);
            $invoice = $this->invoices->issue($draft, $context, dueDays: (int) config('onhost.billing.invoice_due_days', 14));
            $this->advance($subscription, $newStart, $newEnd);
            $this->dunning->open($organization->id, $invoice->id, $service->id, $invoice->due_at ?? now()->addDays(14));
            $this->outbox->publish(GenericEvent::of('subscription.renewed', 'subscription', $subscription->id, ['service_id' => $service->id, 'invoice_id' => $invoice->id, 'mode' => 'postpaid', 'period_end' => $newEnd->toIso8601String()], $organization->id));

            return 'invoiced';
        }
        try {
            $this->wallets->charge($organization, $line['total'], $service->family, "sub_renew:{$subscription->id}:{$periodKey}", $context, 'subscription', $subscription->id, $line['tax']);
        } catch (DomainError $e) {
            if (! in_array($e->error, ['insufficient_funds', 'budget_exceeded', 'budget_single_service_exceeded'], true)) {
                throw $e;
            }
            $graceEnd = $subscription->current_period_end->copy()->addDays((int) config('onhost.billing.dunning.grace_days', 14));
            $subscription->forceFill(['state' => Subscription::PAST_DUE, 'renewal_failures' => $subscription->renewal_failures + 1, 'next_renewal_at' => now()->addDay()->min($graceEnd->copy()->addDay())])->save();
            $this->dunning->open($organization->id, null, $service->id, $subscription->current_period_end);
            $this->audit->record($context, 'subscription.renewal_failed', 'failed', ['service_id' => $service->id, 'required' => $line['total'], 'attempt' => $subscription->renewal_failures], 'subscription', $subscription->id);
            $this->outbox->publish(GenericEvent::of('subscription.renewal_failed', 'subscription', $subscription->id, ['service_id' => $service->id, 'required' => $line['total'], 'cause' => $e->error === 'insufficient_funds' ? 'credit' : 'budget', 'period_end' => $subscription->current_period_end->toIso8601String(), 'attempt' => $subscription->renewal_failures], $organization->id));

            return 'failed';
        }
        $draft = $this->invoices->draft($organization, 'statement', $subscription->currency, [$invoiceLine], $context, null, ['payment_method' => 'wallet', 'subscription_id' => $subscription->id, 'renewal_period' => $periodKey]);
        $invoice = $this->invoices->issue($draft, $context, dueDays: 0);
        $this->invoices->markPaid($invoice, $invoice->total(), 'wallet', $context, postLedger: false);
        $this->advance($subscription, $newStart, $newEnd);
        $this->dunning->resolve($organization->id, null, $service->id, $context);
        $this->audit->record($context, 'subscription.renewed', 'succeeded', ['service_id' => $service->id, 'amount' => $line['total'], 'invoice' => $invoice->number], 'subscription', $subscription->id);
        $this->outbox->publish(GenericEvent::of('subscription.renewed', 'subscription', $subscription->id, ['service_id' => $service->id, 'invoice_id' => $invoice->id, 'mode' => 'wallet', 'amount' => $line['total'], 'period_end' => $newEnd->toIso8601String()], $organization->id));

        return 'renewed';
    }

    /** A paid plan change: the next renewal charges the new plan's price; the current period was settled pro rata by the order. */
    public function changePlan(Service $service, OrderItem $item, CommandContext $context): Subscription
    {
        $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
        $config = (array) $item->config;
        $amount = (int) ($config['renewal_net_minor'] ?? $subscription->amount_minor);
        $before = ['plan_version_id' => $subscription->plan_version_id, 'amount_minor' => $subscription->amount_minor, 'period' => $subscription->period, 'current_period_end' => $subscription->current_period_end?->toIso8601String()];
        $period = in_array($item->period, ['month', 'year'], true) ? $item->period : $subscription->period;
        $dates = [];
        if (! empty($config['plan_change']['period_change']) && $period !== $subscription->period) {
            // a billing-period change was paid for a whole new period: it starts now, the unused rest of the old one was credited in the quote
            $start = now();
            $end = BillingPeriod::end($start, $period);
            $dates = ['current_period_start' => $start, 'current_period_end' => $end, 'next_renewal_at' => $end->copy()->subDays((int) config('onhost.billing.renew_lead_days', 7)), 'last_renewed_at' => $start, 'renewal_failures' => 0];
        }
        $subscription->forceFill(['plan_version_id' => $item->plan_version_id, 'price_id' => $item->price_id, 'amount_minor' => $amount, 'period' => $period] + $dates)->save();
        $this->outbox->publish(GenericEvent::of('subscription.plan_changed', 'subscription', $subscription->id, ['service_id' => $service->id, 'period' => $subscription->period, 'amount' => Money::minor($amount, $subscription->currency), 'from_plan' => data_get($config, 'plan_change.from_plan'), 'to_plan' => data_get($config, 'plan_change.to_plan'), 'period_change' => $dates !== [], 'current_period_end' => $subscription->current_period_end?->toIso8601String(), 'before' => $before], $subscription->organization_id));

        return $subscription;
    }

    public function cancelAtPeriodEnd(Subscription $subscription, bool $cancel, CommandContext $context): Subscription
    {
        $subscription->forceFill(['cancel_at_period_end' => $cancel, 'auto_renew' => $cancel ? false : $subscription->auto_renew, 'next_renewal_at' => $cancel ? $subscription->current_period_end : $subscription->next_renewal_at])->save();
        $this->audit->record($context->withScope($subscription->organization_id), $cancel ? 'subscription.cancel_scheduled' : 'subscription.cancel_revoked', 'succeeded', ['period_end' => $subscription->current_period_end->toIso8601String()], 'subscription', $subscription->id);
        $this->outbox->publish(GenericEvent::of($cancel ? 'subscription.cancel_scheduled' : 'subscription.cancel_revoked', 'subscription', $subscription->id, ['service_id' => $subscription->service_id, 'period_end' => $subscription->current_period_end->toIso8601String()], $subscription->organization_id));

        return $subscription;
    }

    public function setAutoRenew(Subscription $subscription, bool $enabled, CommandContext $context): Subscription
    {
        if ($enabled && ! $subscription->auto_renew) { // owner decision 20 (TASK-0021): a standing renewal from credit — the owner or the billing admin switches it on
            app(CreditOrderPolicy::class)->assertMaySpend($subscription->organization_id, $context, 'Požádejte vlastníka o zapnutí automatického prodloužení.');
        }
        $subscription->forceFill(['auto_renew' => $enabled, 'cancel_at_period_end' => $enabled ? false : $subscription->cancel_at_period_end])->save();
        $this->audit->record($context->withScope($subscription->organization_id), 'subscription.auto_renew', 'succeeded', ['enabled' => $enabled], 'subscription', $subscription->id);

        return $subscription;
    }

    /** Retry past-due renewals of one organization (after a top-up). @return int renewed */
    public function retryPastDue(string $organizationId, CommandContext $context): int
    {
        $count = 0;
        foreach (Subscription::query()->where('organization_id', $organizationId)->where('state', Subscription::PAST_DUE)->whereNotNull('service_id')->get() as $subscription) {
            $service = Service::query()->find($subscription->service_id);
            if ($service !== null && $this->renew($subscription, $service, $context->withScope($organizationId)) === 'renewed') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The day of the month a service's periods end on. It is the day the service started, until a period was started again
     * on another day (a restore after the paid time had run out): from then on that day — the renewal would otherwise drift
     * back to the old one, a restore on the 15th billed until the 31st.
     */
    public static function anchorDay(Service $service): ?int
    {
        $day = data_get($service->tags, 'billing_anchor_day');

        return is_numeric($day) && (int) $day >= 1 && (int) $day <= 31 ? (int) $day : $service->activated_at?->day;
    }

    /**
     * What one new period of a cancelled subscription costs if it started now (pay and restore, TASK-0025): the subscription's
     * own price — the one the customer had, no discount added or taken — taxed like a renewal.
     *
     * @return array{start:Carbon, end:Carbon, net:Money, tax:Money, gross:Money}
     */
    public function restartPrice(Subscription $subscription, Service $service): array
    {
        $organization = Organization::query()->findOrFail($subscription->organization_id);
        $start = now();
        $end = BillingPeriod::end($start, (string) $subscription->period, 1, $start->day);
        ['line' => $line] = $this->periodLine($subscription, $service, $organization, $start, $end);

        return ['start' => $start, 'end' => $end, 'net' => Money::minor((int) $subscription->amount_minor, $subscription->currency), 'tax' => $line['tax'], 'gross' => $line['total']];
    }

    /**
     * Bring the CANCELLED subscription of a cancelled service back (pay and restore, TASK-0025). A period that is still paid
     * for simply runs on; otherwise one new period starts now and is paid like a renewal — from the credit with a statement,
     * or with a postpaid invoice and its receivable. Unlike a renewal nothing turns PAST_DUE and no dunning case opens when
     * the credit is short: the caller asked for a restore, the refusal goes back to it and nothing is written.
     *
     * @return array{result:'covered'|'renewed'|'invoiced', document_id:?string, amount:?Money, period_end:string}
     *
     * @throws DomainError `insufficient_funds` / `budget_*` from the wallet
     */
    public function reinstate(Subscription $subscription, Service $service, CommandContext $context, string $walletKey, bool $paidPeriodCounts = true): array
    {
        $organization = Organization::query()->findOrFail($subscription->organization_id);
        $restore = ['cancel_at_period_end' => false, 'auto_renew' => self::previousAutoRenew($service, $subscription)];
        if (self::isMetered(Product::query()->where('key', $service->product_key)->first())) {
            $this->rollMetered($subscription);
            $subscription->forceFill($restore)->save();

            return ['result' => 'covered', 'document_id' => null, 'amount' => null, 'period_end' => (string) self::periodEnd($subscription)?->toIso8601String()];
        }
        if ($paidPeriodCounts && self::periodEnd($subscription)?->isFuture()) {
            $this->runOn($subscription, $restore);

            return ['result' => 'covered', 'document_id' => null, 'amount' => null, 'period_end' => (string) self::periodEnd($subscription)?->toIso8601String()];
        }
        $start = now();
        $end = BillingPeriod::end($start, (string) $subscription->period, 1, $start->day);
        ['line' => $line, 'invoice_line' => $invoiceLine] = $this->periodLine($subscription, $service, $organization, $start, $end, 'Obnovení služby');
        $meta = ['subscription_id' => $subscription->id, 'renewal_period' => 'reinstate-'.$start->format('Ymd'), 'reinstatement' => true];
        if ($organization->billing_mode === 'postpaid' && $this->wallets->approvedCreditLine($organization->id, $subscription->currency)->isPositive()) {
            $draft = $this->invoices->draft($organization, 'invoice', $subscription->currency, [$invoiceLine], $context, null, $meta + ['payment_method' => 'invoice', 'postpaid' => true]);
            $document = $this->invoices->issue($draft, $context, dueDays: (int) config('onhost.billing.invoice_due_days', 14));
            $this->dunning->open($organization->id, $document->id, $service->id, $document->due_at ?? now()->addDays(14));
            $result = 'invoiced';
        } else {
            $this->wallets->charge($organization, $line['total'], $service->family, $walletKey, $context, 'subscription', $subscription->id, $line['tax']);
            $draft = $this->invoices->draft($organization, 'statement', $subscription->currency, [$invoiceLine], $context, null, $meta + ['payment_method' => 'wallet']);
            $document = $this->invoices->issue($draft, $context, dueDays: 0);
            $this->invoices->markPaid($document, $document->total(), 'wallet', $context, postLedger: false);
            $result = 'renewed';
        }
        $this->advance($subscription, $start, $end);
        $subscription->forceFill($restore)->save();
        $service->forceFill(['tags' => array_replace((array) $service->tags, ['billing_anchor_day' => $start->day])])->save(); // the next periods end on this day
        $this->audit->record($context->withScope($organization->id), 'subscription.reinstated', 'succeeded', ['service_id' => $service->id, 'amount' => $line['total'], 'document' => $document->number, 'mode' => $result], 'subscription', $subscription->id);
        $this->outbox->publish(GenericEvent::of('subscription.renewed', 'subscription', $subscription->id, ['service_id' => $service->id, 'invoice_id' => $document->id, 'mode' => $result === 'invoiced' ? 'postpaid' : 'wallet', 'amount' => $line['total'], 'period_end' => $end->toIso8601String(), 'reinstated' => true], $organization->id));

        return ['result' => $result, 'document_id' => $document->id, 'amount' => $line['total'], 'period_end' => $end->toIso8601String()];
    }

    /**
     * A cancellation was taken back (the customer's own resume, staff, a paid dunning case): the service runs again and so
     * does its bill. The terminate saga had set the subscription CANCELLED and nothing ever set it back — an undone
     * cancellation ran unbilled for good (TASK-0025). A period still paid for runs on; one that has run out restarts today,
     * so the next renewal bills a full period from now: the dead time is not billed and nothing is free.
     *
     * @return ?string 'covered' | 'restarted', null when there was nothing to restart
     */
    public function restartAfterRestore(Service $service, CommandContext $context, bool $paidPeriodCounts = true): ?string
    {
        $subscription = Subscription::query()->where('service_id', $service->id)->where('state', Subscription::CANCELLED)->orderByDesc('created_at')->first();
        if ($subscription === null) {
            return null;
        }
        $organization = Organization::query()->findOrFail($subscription->organization_id);
        $restore = ['cancel_at_period_end' => false, 'auto_renew' => self::previousAutoRenew($service, $subscription)];
        if (self::isMetered(Product::query()->where('key', $service->product_key)->first())) {
            $this->rollMetered($subscription);
            $subscription->forceFill($restore)->save();
            $outcome = 'covered';
        } elseif ($paidPeriodCounts && self::periodEnd($subscription)?->isFuture()) { // a period a refund gave back does not count
            $this->runOn($subscription, $restore);
            $outcome = 'covered';
        } else {
            // the period restarts today and renews at once — from the credit only where auto-renew was on before the cancellation.
            // It is never switched on here (TASK-0027): whoever brought it back (staff, an operator, a payment) is not the
            // holder who agreed to renewals from the credit, so a service whose customer had it off ends again at the renewal
            // pass unless it is paid for (`reinstate`) or a holder switches auto-renew on.
            $now = now();
            $subscription->forceFill(['state' => Subscription::ACTIVE, 'current_period_start' => $now, 'current_period_end' => $now, 'next_renewal_at' => $now, 'renewal_failures' => 0] + $restore)->save();
            $service->forceFill(['tags' => array_replace((array) $service->tags, ['billing_anchor_day' => $now->day])])->save();
            $outcome = 'restarted';
        }
        $this->audit->record($context->withScope($organization->id), 'subscription.restarted', 'succeeded', ['service_id' => $service->id, 'outcome' => $outcome, 'period_end' => self::periodEnd($subscription)?->toIso8601String()], 'subscription', $subscription->id);

        return $outcome;
    }

    /**
     * Whether the customer had auto-renew on before this cancellation — as the terminate saga recorded it for the cancellation
     * being taken back (`deletion` while it runs, `deletion_cancelled` once it was undone; never an earlier cancellation's
     * record, never another subscription's). Without such a record, the cancelled row's own value as the cancellation left it
     * (the saga and `expire()` write false); nothing at all means off, never the organization's default: a subscription that
     * expired because the customer switched auto-renew off or asked to end it with the period has no record, and a restore
     * must not start charging the next periods against that choice (TASK-0025 review). The same for every restore, whoever
     * triggers it — a payment, staff, an operator, the customer (TASK-0027).
     */
    private static function previousAutoRenew(Service $service, Subscription $subscription): bool
    {
        $tags = (array) $service->tags;
        $record = is_array($tags['deletion'] ?? null) ? $tags['deletion'] : ($tags['deletion_cancelled'] ?? null);
        $before = data_get($record, 'subscription');
        $forThis = is_array($before) && array_key_exists('auto_renew', $before) && (! isset($before['id']) || (string) $before['id'] === (string) $subscription->id);
        if (! $forThis) {
            $before = ['auto_renew' => (bool) ($subscription->auto_renew ?? false), 'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? false)];
        }

        return (bool) $before['auto_renew'] && ! (bool) ($before['cancel_at_period_end'] ?? false);
    }

    /** @param array<string,mixed> $restore */
    private function runOn(Subscription $subscription, array $restore): void
    {
        $lead = (self::periodEnd($subscription) ?? now())->subDays((int) config('onhost.billing.renew_lead_days', 7));
        $subscription->forceFill(['state' => Subscription::ACTIVE, 'next_renewal_at' => $lead->max(now()), 'renewal_failures' => 0] + $restore)->save();
    }

    /** The end of the paid period as a date (the model reads it through its cast; this says so to the type checker). */
    private static function periodEnd(Subscription $subscription): ?Carbon
    {
        $end = $subscription->getAttribute('current_period_end');

        return $end instanceof \DateTimeInterface ? Carbon::instance($end) : null;
    }

    /**
     * One period of the subscription as a document line, taxed for the organization.
     *
     * @return array{line:array{rate:mixed, category:mixed, tax:Money, total:Money}, invoice_line:array<string,mixed>}
     */
    private function periodLine(Subscription $subscription, Service $service, Organization $organization, Carbon $start, Carbon $end, string $what = 'Prodloužení služby'): array
    {
        $net = Money::minor((int) $subscription->amount_minor, $subscription->currency);
        $calc = $this->tax->calculate(['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], [['key' => 'renewal', 'net' => $net, 'product_class' => 'esd']], $subscription->currency, $organization->id);
        $line = $calc['lines'][0];
        $invoiceLine = [
            'sku' => $service->product_key.'-renewal', 'description' => "{$what} {$service->name}".($service->hostname ? " ({$service->hostname})" : ''), 'qty' => 1, 'unit' => 'ks',
            'unit_net' => $net->minor, 'discount' => 0, 'net' => $net->minor, 'tax_rate' => (string) $line['rate'], 'tax_category' => (string) $line['category'], 'tax' => $line['tax']->minor, 'total' => $line['total']->minor,
            'period_from' => AccountingClock::date($start), 'period_to' => AccountingClock::date($end->copy()->subDay()), 'service_id' => $service->id, // accounting days; the last day of the period, as on an order's line
        ];

        return ['line' => $line, 'invoice_line' => $invoiceLine];
    }

    private function advance(Subscription $subscription, Carbon $start, Carbon $end): void
    {
        $subscription->forceFill(['state' => Subscription::ACTIVE, 'current_period_start' => $start, 'current_period_end' => $end, 'next_renewal_at' => $end->copy()->subDays((int) config('onhost.billing.renew_lead_days', 7)), 'last_renewed_at' => now(), 'renewal_failures' => 0])->save();
    }

    private function rollMetered(Subscription $subscription): void
    {
        $start = now()->startOfMonth();
        $subscription->forceFill(['current_period_start' => $start, 'current_period_end' => $start->copy()->endOfMonth(), 'next_renewal_at' => $start->copy()->endOfMonth(), 'state' => Subscription::ACTIVE])->save();
    }

    private function expire(Subscription $subscription, Service $service, CommandContext $context): void
    {
        DB::transaction(function () use ($subscription, $service) {
            $subscription->forceFill(['state' => Subscription::CANCELLED, 'auto_renew' => false])->save(); // it ended because it was not to renew: the row says so for a later restore
            $this->outbox->publish(GenericEvent::of('subscription.expired', 'subscription', $subscription->id, ['service_id' => $service->id], $subscription->organization_id));
        });
        if (in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED], true)) {
            app(ServiceService::class)->requestAction($service, 'terminate', CommandContext::system('subscription expired')->withScope($service->organization_id), "sub_expire:{$subscription->id}", ['reason' => 'subscription ended', 'final_backup' => true]);
        }
    }
}
