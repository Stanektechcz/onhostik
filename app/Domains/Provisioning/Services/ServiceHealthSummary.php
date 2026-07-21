<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Customer\Models\Customer;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Provisioning\Enums\ServiceStatus;

/**
 * Aggregated health of a customer's services (audit O189).
 *
 * The dashboard listed services one by one but never answered the first
 * question a customer actually has: "is everything OK right now?" This rolls
 * the whole portfolio into a single verdict — ok / attention / critical — plus
 * the counts behind it, so a problem is visible at a glance instead of only
 * after scrolling a list.
 */
final class ServiceHealthSummary
{
    /**
     * @return array{
     *   verdict: 'ok'|'attention'|'critical'|'none',
     *   total: int,
     *   active: int,
     *   suspended: int,
     *   failed: int,
     *   pending: int,
     *   open_incidents: int,
     * }
     */
    public function forCustomer(Customer $customer): array
    {
        $counts = $customer->services()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $active    = (int) ($counts[ServiceStatus::Active->value] ?? 0);
        $suspended = (int) ($counts[ServiceStatus::Suspended->value] ?? 0);
        $failed    = (int) ($counts[ServiceStatus::Failed->value] ?? 0);
        $pending   = (int) ($counts[ServiceStatus::Pending->value] ?? 0);

        // Terminated services are gone; they don't count toward "how many do I
        // have" on a health widget.
        $total = $active + $suspended + $failed + $pending;

        $openIncidents = MonitorIncident::query()
            ->whereHas('monitor.service', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereNull('resolved_at')
            ->count();

        return [
            'verdict'        => $this->verdict($total, $failed, $suspended, $openIncidents),
            'total'          => $total,
            'active'         => $active,
            'suspended'      => $suspended,
            'failed'         => $failed,
            'pending'        => $pending,
            'open_incidents' => $openIncidents,
        ];
    }

    /** @return 'ok'|'attention'|'critical'|'none' */
    private function verdict(int $total, int $failed, int $suspended, int $incidents): string
    {
        if ($total === 0) {
            return 'none';
        }

        // Failed provisioning or a live outage is critical — money is at stake
        // or a paid service is down.
        if ($failed > 0 || $incidents > 0) {
            return 'critical';
        }

        // Suspended usually means an unpaid invoice — worth attention, not alarm.
        if ($suspended > 0) {
            return 'attention';
        }

        return 'ok';
    }
}
