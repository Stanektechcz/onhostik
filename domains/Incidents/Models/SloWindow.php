<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

/** Computed SLI/SLO snapshot for a component and window (1h/6h/3d/30d). */
final class SloWindow extends Model
{
    protected static string $idPrefix = 'slo';

    protected $table = 'slo_windows';

    protected function casts(): array
    {
        return [
            'good' => 'integer', 'total' => 'integer', 'objective' => 'float', 'availability_pct' => 'float',
            'budget_consumed_pct' => 'float', 'burn_rate' => 'float', 'window_start' => 'datetime', 'window_end' => 'datetime', 'computed_at' => 'datetime',
        ];
    }
}
