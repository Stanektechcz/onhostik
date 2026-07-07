<?php

declare(strict_types=1);

namespace App\Domains\Bi\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;

/**
 * Computes a 0-100 health score for a customer.
 *
 * High score = healthy relationship; low score = at-risk.
 * Scoring is independent of ChurnRiskScorer — it emphasises
 * positive engagement signals rather than problem indicators.
 */
final class CustomerHealthScorer
{
    public function score(Customer $customer): int
    {
        $score = 60;

        // +20 if customer has at least one active service
        $hasActive = $customer->services()
            ->where('status', ServiceStatus::Active->value)
            ->exists();
        if ($hasActive) {
            $score += 20;
        }

        // +10 if user logged in within the last 30 days
        $lastLogin = $customer->user?->last_login_at;
        if ($lastLogin !== null && $lastLogin->gte(now()->subDays(30))) {
            $score += 10;
        }

        // +10 if no overdue invoices at all
        $overdueCount = $customer->invoices()
            ->where('status', InvoiceStatus::Overdue->value)
            ->count();
        if ($overdueCount === 0) {
            $score += 10;
        }

        // -15 per overdue invoice (capped at -45)
        $score -= min($overdueCount * 15, 45);

        // -10 if any suspended/failed service
        $hasProblemService = $customer->services()
            ->whereIn('status', [ServiceStatus::Suspended->value, ServiceStatus::Failed->value])
            ->exists();
        if ($hasProblemService) {
            $score -= 10;
        }

        // -5 per open support ticket in last 30 days (capped at -15)
        $recentTickets = $customer->supportTickets()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $score -= min($recentTickets * 5, 15);

        return max(0, min(100, $score));
    }

    /**
     * Return a Bootstrap colour name for the score badge.
     * @return 'success'|'warning'|'danger'
     */
    public function color(int $score): string
    {
        return match (true) {
            $score >= 80 => 'success',
            $score >= 50 => 'warning',
            default      => 'danger',
        };
    }

    /**
     * Return a short Czech label for the health tier.
     */
    public function label(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Zdravý',
            $score >= 50 => 'Pozor',
            default      => 'Kritický',
        };
    }
}
