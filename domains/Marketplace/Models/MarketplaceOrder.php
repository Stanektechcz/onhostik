<?php

declare(strict_types=1);

namespace Onhost\Domain\Marketplace\Models;

use Onhost\Platform\Eloquent\Model;

/** One purchase of a marketplace listing: paid from credit at the order, earned by the partner when the customer accepts. */
final class MarketplaceOrder extends Model
{
    public const ORDERED = 'ordered';

    public const IN_PROGRESS = 'in_progress';

    public const DELIVERED = 'delivered';

    public const ACCEPTED = 'accepted';

    public const DISPUTED = 'disputed';

    public const CANCELLED = 'cancelled';

    public const ENDED = 'ended'; // a monthly listing that stopped (customer ended it or the credit ran out)

    public const OPEN = [self::ORDERED, self::IN_PROGRESS, self::DELIVERED, self::DISPUTED];

    protected static string $idPrefix = 'mko';

    protected $table = 'marketplace_orders';

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'commission_minor' => 'integer', 'partner_minor' => 'integer', 'late_credit_minor' => 'integer', 'missed_periods' => 'integer', 'due_at' => 'datetime', 'delivered_at' => 'datetime', 'accepted_at' => 'datetime', 'period_delivered_at' => 'datetime', 'period_warned_at' => 'datetime', 'period_evidence' => 'array', 'period_uploads' => 'array', 'overdue_notified_at' => 'datetime', 'refund_offered_at' => 'datetime'];
    }
}
