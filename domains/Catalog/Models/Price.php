<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/**
 * What one version of a plan costs in one currency for one billing period (net amounts in minor units).
 *
 * @property string $id
 * @property string $plan_version_id
 * @property string $currency
 * @property string $period
 * @property int $amount_minor
 * @property ?int $renewal_amount_minor
 * @property int $setup_minor
 * @property ?int $promo_amount_minor
 * @property ?int $promo_periods
 * @property ?int $monthly_cap_minor
 * @property ?array<string,mixed> $included
 * @property string $state
 */
final class Price extends Model
{
    protected static string $idPrefix = 'prc';

    protected $table = 'prices';

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'renewal_amount_minor' => 'integer', 'setup_minor' => 'integer', 'promo_amount_minor' => 'integer',
            'promo_periods' => 'integer', 'monthly_cap_minor' => 'integer', 'included' => 'array',
            'effective_from' => 'datetime', 'effective_to' => 'datetime',
        ];
    }

    public function amount(): Money
    {
        return Money::minor($this->amount_minor, $this->currency);
    }

    public function renewalAmount(): Money
    {
        return Money::minor($this->renewal_amount_minor ?? $this->amount_minor, $this->currency);
    }

    public function firstPeriodAmount(): Money
    {
        return Money::minor($this->promo_amount_minor ?? $this->amount_minor, $this->currency);
    }

    public function setup(): Money
    {
        return Money::minor($this->setup_minor, $this->currency);
    }

    public function monthlyCap(): ?Money
    {
        return $this->monthly_cap_minor === null ? null : Money::minor($this->monthly_cap_minor, $this->currency);
    }

    public function isCurrent(): bool
    {
        return $this->state === 'active' && $this->effective_from->isPast() && ($this->effective_to === null || $this->effective_to->isFuture());
    }
}
