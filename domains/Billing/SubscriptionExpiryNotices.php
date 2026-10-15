<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * N18 (variant C): a transactional notice that a service ends with its paid period because it is not to renew
 * (auto-renew off, or a cancellation at period end). It performs the contract, it is not marketing, so it needs no
 * consent; no abandoned-checkout or other lifecycle mails exist. Domains have their own notices (`domain.renewal_notice`).
 * Windows 14/7/1 days; one notice per subscription, period end and window — the most urgent window reached implies the
 * coarser ones (a late first run sends one notice, not three).
 */
final class SubscriptionExpiryNotices
{
    /** @var list<int> */
    public const WINDOWS = [1, 7, 14];

    public function __construct(private readonly OutboxPublisher $outbox) {}

    /** @return array{checked:int, notified:int} */
    public function run(): array
    {
        $stats = ['checked' => 0, 'notified' => 0];
        $candidates = Subscription::query()->whereNotNull('service_id')->whereNull('domain_id')
            ->whereIn('state', [Subscription::ACTIVE, Subscription::PAST_DUE])
            ->where(fn ($q) => $q->where('auto_renew', false)->orWhere('cancel_at_period_end', true))
            ->where('current_period_end', '>', now())->where('current_period_end', '<=', now()->addDays(max(self::WINDOWS)))
            ->orderBy('current_period_end')->limit(1000)->get();
        foreach ($candidates as $subscription) {
            $stats['checked']++;
            // the service of the subscription's own organization only — never another tenant's row
            $service = Service::query()->where('organization_id', $subscription->organization_id)->find($subscription->service_id);
            if ($service === null || in_array($service->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING, ServiceStateMachine::FAILED], true)) {
                continue;
            }
            $days = max(1, (int) ceil(now()->diffInSeconds($subscription->current_period_end) / 86400));
            $window = self::window($days);
            $periodEnd = CarbonImmutable::parse($subscription->current_period_end)->toIso8601String();
            $sent = DB::transaction(function () use ($subscription, $service, $days, $window, $periodEnd): bool {
                $organization = Organization::query()->lockForUpdate()->find($subscription->organization_id);
                if ($organization === null) {
                    return false;
                }
                $settings = (array) ($organization->settings ?? []);
                $notices = (array) ($settings['subscription_expiry_notices'] ?? []);
                [$lastEnd, $lastWindow] = array_pad(explode('|', (string) ($notices[$subscription->id] ?? ''), 2), 2, '');
                if ($lastEnd === $periodEnd && $lastWindow !== '' && (int) $lastWindow <= $window) {
                    return false;
                }
                $notices[$subscription->id] = $periodEnd.'|'.$window;
                $organization->forceFill(['settings' => array_merge($settings, ['subscription_expiry_notices' => $notices])])->save();
                $this->outbox->publish(GenericEvent::of('subscription.expiring', 'subscription', $subscription->id, [
                    'service_id' => $service->id, 'service' => (string) ($service->label ?: ($service->hostname ?: $service->name)), 'days' => $days, 'window' => $window,
                    'period_end' => $periodEnd, 'reason' => $subscription->cancel_at_period_end ? 'cancelled' : 'auto_renew_off',
                ], $subscription->organization_id));

                return true;
            });
            if ($sent) {
                $stats['notified']++;
            }
        }

        return $stats;
    }

    private static function window(int $days): int
    {
        foreach (self::WINDOWS as $window) {
            if ($days <= $window) {
                return $window;
            }
        }

        return max(self::WINDOWS);
    }
}
