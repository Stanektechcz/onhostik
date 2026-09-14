<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

/**
 * A node the forecast wants for a pool (audit §5n-7): proposed by the capacity forecast, approved by operations (or
 * ordered automatically when the rule is on and the pool's instance can order nodes), delivered when the node is active.
 */
final class CapacityRequest extends Model
{
    public const PROPOSED = 'proposed';

    public const APPROVED = 'approved';

    public const ORDERED = 'ordered';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    public const OPEN = [self::PROPOSED, self::APPROVED, self::ORDERED];

    protected static string $idPrefix = 'capr';

    protected function casts(): array
    {
        return ['meta' => 'array', 'decided_at' => 'datetime', 'ordered_at' => 'datetime', 'delivered_at' => 'datetime', 'ready_at' => 'datetime', 'activated_at' => 'datetime'];
    }
}
