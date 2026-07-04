<?php

declare(strict_types=1);

namespace App\Domains\Bi\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Bi\Enums\CustomerSegment;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use Brick\Money\Money;

final class ChurnRiskScorer
{
    public function score(Customer $customer): int
    {
        $score = 0;

        // +20 per open overdue invoice (strong predictor)
        $overdueCount = $customer->invoices()
            ->where('status', InvoiceStatus::Overdue->value)
            ->count();
        $score += min($overdueCount * 20, 60);

        // +20 per suspended/failed service
        $problemServices = $customer->services()
            ->whereIn('status', [ServiceStatus::Suspended->value, ServiceStatus::Failed->value])
            ->count();
        $score += min($problemServices * 20, 40);

        // +15 if no paid invoice in last 90 days and account older than 30 days
        $lastPaid = $customer->invoices()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->max('paid_at');

        $accountAgedays = $customer->created_at?->diffInDays(now()) ?? 0;
        if ($accountAgedays > 30 && ($lastPaid === null || now()->subDays(90)->gt($lastPaid))) {
            $score += 15;
        }

        // +5 per support ticket in last 30 days (dissatisfaction signal; cap at 15)
        $recentTickets = $customer->supportTickets()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $score += min($recentTickets * 5, 15);

        return min(100, $score);
    }

    public function segment(Customer $customer, int $riskScore): CustomerSegment
    {
        if ($riskScore >= 70) {
            return CustomerSegment::Churned;
        }

        if ($riskScore >= 30) {
            return CustomerSegment::AtRisk;
        }

        $totalSpendMinor = $customer->invoices()
            ->where('status', InvoiceStatus::Paid->value)
            ->sum('total');

        // VIP: low risk + meaningful spend (>= 10 000 CZK equivalent in minor units)
        if ($totalSpendMinor >= 1_000_000 && $riskScore < 20) {
            return CustomerSegment::VIP;
        }

        return CustomerSegment::Healthy;
    }
}
