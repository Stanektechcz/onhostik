<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;

final class Budget extends Model
{
    protected static string $idPrefix = 'bud';

    protected $table = 'budgets';

    protected function casts(): array
    {
        return [
            'limit_minor' => 'integer', 'hard' => 'boolean', 'alert_thresholds' => 'array', 'max_single_service_minor' => 'integer',
            'approval_above_minor' => 'integer', 'spent_minor' => 'integer', 'period_start' => 'date', 'notified' => 'array',
        ];
    }
}
