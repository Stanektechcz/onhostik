<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Onhost\Platform\Eloquent\Model;

/** A customer's request to leave a service early with part of the unused paid period returned as credit. */
final class ChargebackRequest extends Model
{
    protected static string $idPrefix = 'cbk';

    protected $table = 'chargeback_requests';

    public const REQUESTED = 'requested';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLING = 'cancelling';

    public const REFUNDED = 'refunded';

    public const WITHDRAWN = 'withdrawn';

    public const OPEN = [self::REQUESTED, self::APPROVED, self::CANCELLING];

    protected function casts(): array
    {
        return ['percent' => 'integer', 'unused_minor' => 'integer', 'refund_minor' => 'integer', 'decided_at' => 'datetime', 'cancelled_at' => 'datetime', 'refunded_at' => 'datetime'];
    }
}
