<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger\Models;

use Onhost\Platform\Eloquent\Model;

final class AutoTopupSetting extends Model
{
    protected static string $idPrefix = 'atu';

    protected $table = 'auto_topup_settings';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean', 'threshold_minor' => 'integer', 'amount_minor' => 'integer',
            'max_per_day' => 'integer', 'monthly_limit_minor' => 'integer', 'last_triggered_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }
}
