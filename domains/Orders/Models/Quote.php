<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/**
 * Immutable price snapshot (product/price/tax versions locked in, §78).
 *
 * @property array<int, array<string, mixed>> $lines the priced cart lines (cast from JSON)
 */
final class Quote extends Model
{
    protected static string $idPrefix = 'qt';

    protected $table = 'quotes';

    protected function casts(): array
    {
        return [
            'lines' => 'array', 'versions' => 'array', 'valid_until' => 'datetime',
            'subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'renewal_total_minor' => 'integer',
        ];
    }

    public function total(): Money
    {
        return Money::minor($this->total_minor, $this->currency);
    }

    public function tax(): Money
    {
        return Money::minor($this->tax_minor, $this->currency);
    }

    public function isValid(): bool
    {
        return $this->state === 'open' && $this->valid_until->isFuture();
    }
}
