<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

final class ReconciliationRun extends Model
{
    protected static string $idPrefix = 'rec';

    protected $table = 'reconciliation_runs';

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime', 'summary' => 'array', 'mismatches' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class, 'run_id');
    }
}
