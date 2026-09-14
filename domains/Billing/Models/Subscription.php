<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/** Recurring charge attached to a service or a domain (blueprint §21). Domain subscriptions renew with `domain` priority. */
final class Subscription extends Model
{
    protected static string $idPrefix = 'sub';

    protected $table = 'subscriptions';

    public const ACTIVE = 'active';

    public const PAST_DUE = 'past_due';

    public const PAUSED = 'paused';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'auto_renew' => 'boolean', 'cancel_at_period_end' => 'boolean', 'renewal_failures' => 'integer',
            'current_period_start' => 'datetime', 'current_period_end' => 'datetime', 'next_renewal_at' => 'datetime', 'last_renewed_at' => 'datetime',
        ];
    }

    public function amount(): Money
    {
        return Money::minor($this->amount_minor, $this->currency);
    }

    public function isDomain(): bool
    {
        return $this->domain_id !== null;
    }
}
