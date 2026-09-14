<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

/** Planned maintenance window (blueprint §73.3): announced, approved, executed, SLA-excluded by default. */
final class Maintenance extends Model
{
    protected static string $idPrefix = 'mnt';

    protected $table = 'maintenances';

    public const STATES = ['planned', 'approved', 'in_progress', 'completed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'components' => 'array', 'affected_services' => 'array',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function isActiveAt(\DateTimeInterface $at): bool
    {
        return $this->state !== 'cancelled' && $this->starts_at <= $at && $this->ends_at >= $at;
    }
}
