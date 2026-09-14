<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

final class SlaMeasurement extends Model
{
    protected static string $idPrefix = 'msr';

    protected $table = 'sla_measurements';

    protected function casts(): array
    {
        return ['ok' => 'boolean', 'latency_ms' => 'integer', 'measured_at' => 'datetime'];
    }
}
