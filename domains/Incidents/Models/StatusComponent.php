<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

/** Public status component (blueprint §73.1). The key is the primary key; no ULID is generated. */
final class StatusComponent extends Model
{
    protected $table = 'status_components';

    protected $primaryKey = 'key';

    public const STATES = ['operational', 'degraded', 'partial_outage', 'major_outage', 'maintenance'];

    /** Severity → component state while an incident is open. */
    public const SEVERITY_STATE = ['p1' => 'major_outage', 'p2' => 'partial_outage', 'p3' => 'degraded', 'p4' => 'degraded'];

    protected function casts(): array
    {
        return ['public' => 'boolean', 'sort' => 'integer'];
    }

    /** Ordering for "worst state wins" when several incidents touch one component. */
    public static function rank(string $state): int
    {
        return array_search($state, ['operational', 'maintenance', 'degraded', 'partial_outage', 'major_outage'], true) ?: 0;
    }
}
