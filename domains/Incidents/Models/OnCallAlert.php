<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

/**
 * One page to the on-call (audit §5q-1): opened by an operational event, acknowledged by a person (console or the
 * pager's own webhook), escalated while nobody acknowledges, resolved by a person or the recovery event.
 */
final class OnCallAlert extends Model
{
    public const OPEN = 'open';

    public const ACKED = 'acked';

    public const ESCALATED = 'escalated';

    public const RESOLVED = 'resolved';

    public const ACTIVE = [self::OPEN, self::ESCALATED];

    protected static string $idPrefix = 'onc';

    protected $table = 'oncall_alerts';

    protected function casts(): array
    {
        return ['meta' => 'array', 'escalations' => 'integer', 'escalated_at' => 'datetime', 'escalate_after' => 'datetime', 'acked_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
