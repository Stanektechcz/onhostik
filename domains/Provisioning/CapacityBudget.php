<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Settings\SettingsStore;

/**
 * The monthly cap on vendor node orders (audit §5q-5): `ONHOST_CAPACITY_BUDGET_MONTHLY_MINOR` (or the console's
 * setting) against the vendor's monthly price of every node ordered this calendar month. An automatic order that
 * would cross the cap waits for a person; a person crossing it must say so (`override_budget` with a note), which is
 * the finance approval the audit row keeps. Zero means no cap.
 */
final class CapacityBudget
{
    public const SETTING = 'capacity.budget.monthly_minor';

    public function __construct(private readonly SettingsStore $settings) {}

    /** @return array{monthly_minor:int, currency:string, spent_minor:int, remaining_minor:?int, orders:int, month:string, source:string} */
    public function status(): array
    {
        $currency = strtoupper((string) config('onhost.provisioning.capacity_budget.currency', 'EUR'));
        $set = $this->settings->get(self::SETTING);
        $monthly = max(0, (int) ($set !== null ? $set : config('onhost.provisioning.capacity_budget.monthly_minor', 0)));
        $rows = CapacityRequest::query()->whereIn('state', [CapacityRequest::ORDERED, CapacityRequest::DELIVERED])->whereNotNull('ordered_at')->where('ordered_at', '>=', now()->startOfMonth())->where('cost_currency', $currency)->get(['cost_minor']);
        $spent = (int) $rows->sum('cost_minor');

        return ['monthly_minor' => $monthly, 'currency' => $currency, 'spent_minor' => $spent, 'remaining_minor' => $monthly > 0 ? max(0, $monthly - $spent) : null, 'orders' => $rows->count(), 'month' => now()->format('Y-m'), 'source' => $set !== null ? 'setting' : 'config'];
    }

    /** Whether one more order at `$costMinor` fits; anything unpriced fits (the cap counts what the vendor prices). */
    public function allows(?int $costMinor, ?string $currency): bool
    {
        $status = $this->status();
        if ($status['monthly_minor'] <= 0 || $costMinor === null || $costMinor <= 0 || strtoupper((string) $currency) !== $status['currency']) {
            return true;
        }

        return $status['monthly_minor'] >= $status['spent_minor'] + $costMinor;
    }

    public function set(?int $monthlyMinor, ?string $by = null): array
    {
        if ($monthlyMinor === null) {
            $this->settings->forget(self::SETTING);
        } else {
            $this->settings->set(self::SETTING, max(0, $monthlyMinor), $by);
        }

        return $this->status();
    }

    /** @return array{cost:string, budget:string, spent:string} formatted for the finance notice */
    public function describe(?int $costMinor): array
    {
        $status = $this->status();
        $fmt = fn (int $minor) => Money::minor($minor, $status['currency'])->format('cs');

        return ['cost' => $fmt((int) $costMinor), 'budget' => $fmt($status['monthly_minor']), 'spent' => $fmt($status['spent_minor'])];
    }
}
