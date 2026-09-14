<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Onhost\Platform\Eloquent\Model;

/** Raw metered quantity for one service and one interval (unique by dedupe_key, so collectors can be replayed). */
final class UsageEvent extends Model
{
    protected static string $idPrefix = 'use';

    protected $table = 'usage_events';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'rated' => 'boolean', 'period_start' => 'datetime', 'period_end' => 'datetime'];
    }
}
