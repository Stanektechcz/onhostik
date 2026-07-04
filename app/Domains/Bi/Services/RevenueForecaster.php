<?php

declare(strict_types=1);

namespace App\Domains\Bi\Services;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use Illuminate\Support\Collection;

final class RevenueForecaster
{
    /**
     * Returns a forecast for the next calendar month's revenue.
     *
     * @return array{forecast: float, trend: string, months: array<string, float>, confidence: string}
     */
    public function forecast(): array
    {
        $months = collect();

        for ($i = 3; $i >= 1; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end   = (clone $start)->endOfMonth();
            $label = $start->format('Y-m');

            $total = Invoice::query()
                ->where('status', InvoiceStatus::Paid->value)
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$start, $end])
                ->sum('total');

            $months->put($label, (float) $total / 100);
        }

        $values = $months->values()->toArray();
        $count  = count(array_filter($values, fn (float $v) => $v > 0));

        $forecast   = $count > 0 ? array_sum($values) / count($values) : 0.0;
        $confidence = match (true) {
            $count >= 3 => 'high',
            $count >= 2 => 'medium',
            default     => 'low',
        };

        $trend = 'stable';
        if ($count >= 2) {
            $last    = end($values);
            $first   = reset($values);
            $diffPct = $first > 0 ? (($last - $first) / $first) * 100 : 0;
            $trend = match (true) {
                $diffPct >= 5  => 'growing',
                $diffPct <= -5 => 'declining',
                default        => 'stable',
            };
        }

        return [
            'forecast'   => round($forecast, 2),
            'trend'      => $trend,
            'months'     => $months->toArray(),
            'confidence' => $confidence,
        ];
    }
}
