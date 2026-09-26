<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger;

use Carbon\CarbonImmutable;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Domain\WalletLedger\Models\AutoTopupSetting;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Credit runway: the renewals of the organization's subscriptions are walked in date order (gross, with the customer's
 * VAT) against the available credit; the first renewal the credit cannot cover is the day the credit runs out. A daily
 * pass warns organizations two weeks ahead (`wallet.runway.low`, once per day) unless automatic top-ups are on.
 */
final class WalletForecast
{
    public const HORIZON_DAYS = 120;

    public const WARN_DAYS = 14;

    public const GUARD_DAYS = 7;

    public function __construct(private readonly WalletService $wallets, private readonly TaxEngine $tax, private readonly OutboxPublisher $outbox, private readonly AutoTopup $autoTopup) {}

    /**
     * @return array{currency:string, available:Money, monthly_burn:Money, renewals_30d:Money, shortfall_30d:Money, depletes_at:?string, days:?int, next_renewal:?array{at:string,amount:Money,service_id:?string,domain_id:?string}, auto_topup:bool, subscriptions:int}
     */
    public function forecast(Organization $organization, string $currency): array
    {
        $currency = strtoupper($currency);
        $available = $this->wallets->balances($organization, $currency)['available'];
        $subscriptions = Subscription::query()->where('organization_id', $organization->id)->where('currency', $currency)
            ->whereIn('state', ['active', 'past_due'])->where('auto_renew', true)->where('cancel_at_period_end', false)->orderBy('next_renewal_at')->get();
        $now = CarbonImmutable::now();
        $horizon = $now->addDays(self::HORIZON_DAYS);
        $monthly = 0;
        $events = [];
        foreach ($subscriptions as $subscription) {
            $gross = $this->gross($organization, $subscription)->minor;
            $monthly += $subscription->period === 'year' ? intdiv($gross, 12) : $gross;
            $at = CarbonImmutable::instance($subscription->next_renewal_at);
            while ($at <= $horizon) {
                $events[] = ['at' => $at, 'amount' => $gross, 'subscription' => $subscription];
                $at = $subscription->period === 'year' ? $at->addYear() : $at->addMonth();
            }
        }
        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);
        $balance = $available->minor;
        $renewals30 = 0;
        $depletes = null;
        foreach ($events as $event) {
            if ($event['at'] <= $now->addDays(30)) {
                $renewals30 += $event['amount'];
            }
            $balance -= $event['amount'];
            if ($balance < 0 && $depletes === null) {
                $depletes = $event['at'];
            }
        }
        $next = $events[0] ?? null;

        return [
            'currency' => $currency,
            'available' => $available,
            'monthly_burn' => Money::minor($monthly, $currency),
            'renewals_30d' => Money::minor($renewals30, $currency),
            'shortfall_30d' => Money::minor(max(0, $renewals30 - $available->minor), $currency),
            'depletes_at' => $depletes?->toIso8601String(),
            'days' => $depletes === null ? null : max(0, (int) $now->startOfDay()->diffInDays($depletes->startOfDay(), false)),
            'next_renewal' => $next === null ? null : ['at' => $next['at']->toIso8601String(), 'amount' => Money::minor($next['amount'], $currency), 'service_id' => $next['subscription']->service_id, 'domain_id' => $next['subscription']->domain_id],
            'auto_topup' => AutoTopupSetting::query()->where('organization_id', $organization->id)->where('enabled', true)->exists(),
            'subscriptions' => $subscriptions->count(),
        ];
    }

    /**
     * Renewal guard (audit §5e-2), daily: every organization whose renewals of the next GUARD_DAYS days outrun the
     * available credit either gets its credit topped up automatically (opt-in, a provider that charges stored methods)
     * or hears exactly how much is missing and by when — `billing.renewal.underfunded`, once per day.
     *
     * @return array{checked:int, underfunded:int, topped_up:int, notified:int}
     */
    public function renewalGuard(int $days = self::GUARD_DAYS): array
    {
        $stats = ['checked' => 0, 'underfunded' => 0, 'topped_up' => 0, 'notified' => 0];
        $now = CarbonImmutable::now();
        $until = $now->addDays(max(1, $days));
        $organizationIds = Subscription::query()->whereIn('state', ['active', 'past_due'])->where('auto_renew', true)->where('cancel_at_period_end', false)
            ->whereBetween('next_renewal_at', [$now->subDay(), $until])->distinct()->pluck('organization_id');
        foreach (Organization::query()->whereIn('id', $organizationIds)->get() as $organization) {
            $stats['checked']++;
            $currency = (string) $organization->currency;
            $due = Subscription::query()->where('organization_id', $organization->id)->where('currency', $currency)->whereIn('state', ['active', 'past_due'])
                ->where('auto_renew', true)->where('cancel_at_period_end', false)->whereBetween('next_renewal_at', [$now->subDay(), $until])->orderBy('next_renewal_at')->get();
            if ($due->isEmpty()) {
                continue;
            }
            $sum = 0;
            $services = [];
            foreach ($due as $subscription) {
                $sum += $this->gross($organization, $subscription)->minor;
                $services[] = ['service_id' => $subscription->service_id, 'at' => $subscription->next_renewal_at?->toIso8601String(), 'amount' => $this->gross($organization, $subscription)];
            }
            $available = $this->wallets->balances($organization, $currency)['available'];
            if ($available->minor >= $sum) {
                continue;
            }
            $stats['underfunded']++;
            $shortfall = Money::minor($sum - $available->minor, $currency);
            $topup = $this->autoTopup->attempt($organization, $shortfall, CommandContext::system('renewal-guard'));
            if ($topup['status'] === 'charged') {
                $stats['topped_up']++;
            }
            $settings = (array) ($organization->settings ?? []);
            if (($settings['renewal_guard_warned_on'] ?? null) === $now->toDateString()) {
                continue;
            }
            $organization->forceFill(['settings' => array_merge($settings, ['renewal_guard_warned_on' => $now->toDateString()])])->save();
            $this->outbox->publish(GenericEvent::of('billing.renewal.underfunded', 'wallet', $organization->id, [
                'days' => $days, 'due' => Money::minor($sum, $currency), 'available' => $available, 'shortfall' => $shortfall, 'first_renewal_at' => $due->first()?->next_renewal_at?->toIso8601String(),
                'services' => $services, 'auto_topup' => $topup,
            ], $organization->id));
            $stats['notified']++;
        }

        return $stats;
    }

    /** The renewal as the wallet will be charged: net renewal amount plus the customer's VAT. */
    public function gross(Organization $organization, Subscription $subscription): Money
    {
        $net = Money::minor((int) $subscription->amount_minor, $subscription->currency);
        $calc = $this->tax->calculate(VatStanding::taxCustomer($organization), [['key' => 'renewal', 'net' => $net, 'product_class' => 'esd']], $subscription->currency, $organization->id);

        return $calc['lines'][0]['total'];
    }

    /**
     * Daily pass: every organization with renewals ahead whose credit runs out within WARN_DAYS gets `wallet.runway.low`
     * (notification + mail through the router), once per calendar day; organizations with automatic top-ups are left alone.
     *
     * @return array{checked:int, warned:int}
     */
    public function warnLow(): array
    {
        $stats = ['checked' => 0, 'warned' => 0];
        $organizationIds = Subscription::query()->whereIn('state', ['active', 'past_due'])->where('auto_renew', true)->distinct()->pluck('organization_id');
        foreach (Organization::query()->whereIn('id', $organizationIds)->get() as $organization) {
            $stats['checked']++;
            $forecast = $this->forecast($organization, (string) $organization->currency);
            if ($forecast['days'] === null || $forecast['days'] > self::WARN_DAYS || $forecast['auto_topup']) {
                continue;
            }
            $settings = (array) ($organization->settings ?? []);
            if (($settings['runway_warned_on'] ?? null) === now()->toDateString()) {
                continue;
            }
            $organization->forceFill(['settings' => array_merge($settings, ['runway_warned_on' => now()->toDateString()])])->save();
            $this->outbox->publish(GenericEvent::of('wallet.runway.low', 'wallet', $organization->id, [
                'days' => $forecast['days'], 'depletes_at' => $forecast['depletes_at'], 'shortfall' => $forecast['shortfall_30d'], 'available' => $forecast['available'],
                'renewals_30d' => $forecast['renewals_30d'], 'next_renewal' => $forecast['next_renewal'],
            ], $organization->id));
            $stats['warned']++;
        }

        return $stats;
    }
}
