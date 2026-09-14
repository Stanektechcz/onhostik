<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

final class Settlement extends Model
{
    protected static string $idPrefix = 'stl';

    protected $table = 'settlements';

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'items_count' => 'integer', 'total_minor' => 'integer', 'fee_minor' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class, 'settlement_id');
    }
}
