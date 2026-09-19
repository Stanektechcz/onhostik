<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
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
            $end = $period === 'year' ? $start->copy()->addYears($periodsBilled) : $start->copy()->addMonths($periodsBilled);
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
            if ($service === null || in_array($service->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING, ServiceStateMachine::FAILED], true)) {
                $subscription->forceFill(['state' => Subscription::CANCELLED])->save();
                $stats['cancelled']++;

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

    /** @return 'renewed'|'invoiced'|'failed' */
    public function renew(Subscription $subscription, Service $service, CommandContext $context): string
    {
        $organization = Organization::query()->findOrFail($subscription->organization_id);
        $net = Money::minor((int) $subscription->amount_minor, $subscription->currency);
        $calc = $this->tax->calculate(['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], [['key' => 'renewal', 'net' => $net, 'product_class' => 'esd']], $subscription->currency, $organization->id);
        $line = $calc['lines'][0];
        $periodKey = $subscription->current_period_end->format('Ymd');
        $newStart = $subscription->current_period_end->copy();
        $newEnd = $subscription->period === 'year' ? $newStart->copy()->addYear() : $newStart->copy()->addMonth();
        $invoiceLine = [
            'sku' => $service->product_key.'-renewal', 'description' => "Prodloužení služby {$service->name}".($service->hostname ? " ({$service->hostname})" : ''), 'qty' => 1, 'unit' => 'ks',
            'unit_net' => $net->minor, 'discount' => 0, 'net' => $net->minor, 'tax_rate' => (string) $line['rate'], 'tax_category' => (string) $line['category'], 'tax' => $line['tax']->minor, 'total' => $line['total']->minor,
            'period_from' => $newStart->toDateString(), 'period_to' => $newEnd->toDateString(), 'service_id' => $service->id,
        ];
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
            $end = $period === 'year' ? $start->copy()->addYear() : $start->copy()->addMonth();
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
            $subscription->forceFill(['state' => Subscription::CANCELLED])->save();
            $this->outbox->publish(GenericEvent::of('subscription.expired', 'subscription', $subscription->id, ['service_id' => $service->id], $subscription->organization_id));
        });
        if (in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED], true)) {
            app(ServiceService::class)->requestAction($service, 'terminate', CommandContext::system('subscription expired')->withScope($service->organization_id), "sub_expire:{$subscription->id}", ['reason' => 'subscription ended', 'final_backup' => true]);
        }
    }
}
