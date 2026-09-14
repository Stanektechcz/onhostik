<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class UptimeMonitor extends Model
{
    protected static string $idPrefix = 'upm';

    protected $table = 'uptime_monitors';

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'notify' => 'boolean', 'last_checked_at' => 'datetime', 'next_check_at' => 'datetime'];
    }
}
