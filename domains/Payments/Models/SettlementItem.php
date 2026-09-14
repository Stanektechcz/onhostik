<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Onhost\Platform\Eloquent\Model;

final class SettlementItem extends Model
{
    protected static string $idPrefix = 'sti';

    protected $table = 'settlement_items';

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'fee_minor' => 'integer', 'settled_at' => 'datetime'];
    }
}
