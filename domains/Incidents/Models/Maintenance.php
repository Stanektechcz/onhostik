<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * Planned maintenance window (blueprint §73.3): announced, approved, executed; SLA-excluded only by `excludesFromSla()`.
 *
 * @property string $id
 * @property string $number
 * @property string $title
 * @property list<string> $components
 * @property ?list<string> $affected_services
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property ?string $impact
 * @property ?string $rollback
 * @property ?string $owner_id
 * @property ?string $approved_by
 * @property ?string $change_ticket
 * @property string $sla_treatment
 * @property string $state
 * @property bool $emergency
 * @property ?Carbon $announced_at
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 */
final class Maintenance extends Model
{
    protected static string $idPrefix = 'mnt';

    protected $table = 'maintenances';

    public const STATES = ['planned', 'approved', 'in_progress', 'completed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'components' => 'array', 'affected_services' => 'array',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'announced_at' => 'datetime', 'emergency' => 'boolean',
        ];
    }

    /**
     * The one definition of "planned maintenance" for the SLA (Brain card H15): approved by a second person — which is
     * what announces it to the customers — at least the lead time before it starts, not an emergency, not cancelled.
     * Anything else is downtime like any other, whatever `sla_treatment` says. A window drawn over an outage afterwards,
     * or pushed through at short notice, therefore never takes minutes out of an SLA report.
     */
    public function excludesFromSla(): bool
    {
        if ($this->sla_treatment !== 'excluded' || $this->emergency || $this->announced_at === null || ! in_array($this->state, ['approved', 'in_progress', 'completed'], true)) {
            return false;
        }

        return $this->announced_at->lessThanOrEqualTo($this->starts_at->copy()->subHours(self::leadHours()));
    }

    public static function leadHours(): int
    {
        return max(0, (int) config('onhost.status.maintenance_lead_hours', 48));
    }

    public function isActiveAt(\DateTimeInterface $at): bool
    {
        return $this->state !== 'cancelled' && $this->starts_at <= $at && $this->ends_at >= $at;
    }
}
