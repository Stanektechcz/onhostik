<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Carbon\CarbonInterface;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/** Calendar month of metered usage per organization and currency; carries the monthly caps applied per service. */
final class BillingPeriod extends Model
{
    protected static string $idPrefix = 'bp';

    protected $table = 'billing_periods';

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'total_minor' => 'integer', 'cap_applied' => 'array'];
    }

    public function total(): Money
    {
        return Money::minor($this->total_minor, $this->currency);
    }

    /** The calendar-month period containing `$day` (created open when missing). */
    public static function forMonth(string $organizationId, string $currency, CarbonInterface $day): self
    {
        $start = $day->copy()->startOfMonth();
        $existing = self::query()->where('organization_id', $organizationId)->where('currency', $currency)->whereDate('period_start', $start->toDateString())->first();

        return $existing ?? self::query()->create(['organization_id' => $organizationId, 'currency' => $currency, 'period_start' => $start, 'period_end' => $start->copy()->endOfMonth(), 'state' => 'open', 'total_minor' => 0, 'cap_applied' => []]);
    }
}
