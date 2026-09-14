<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class UptimeIncident extends Model
{
    protected static string $idPrefix = 'upi';

    protected $table = 'uptime_incidents';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'resolved_at' => 'datetime', 'notified' => 'boolean'];
    }
}
