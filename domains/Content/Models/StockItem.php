<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Models;

use Onhost\Platform\Eloquent\Model;

/** Hardware rental stock shown on `#/technika` (name, spec, price, unit, availability, state). */
final class StockItem extends Model
{
    protected static string $idPrefix = 'stk';

    protected $table = 'stock_items';

    protected function casts(): array
    {
        return ['spec' => 'array', 'price_minor' => 'integer', 'available' => 'integer', 'lead_days' => 'integer', 'sort' => 'integer'];
    }
}
