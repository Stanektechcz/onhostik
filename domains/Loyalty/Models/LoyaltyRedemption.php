<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Models;

use Onhost\Platform\Eloquent\Model;

/**
 * Points one order redeems (owner decision G-R2): reserved at placement, consumed at payment, released when the order is
 * cancelled unpaid. `returned_points` is what credit notes gave back in proportion to what they credited.
 *
 * @property string $organization_id
 * @property string $order_id
 * @property string $state
 * @property int $points
 * @property int $value_minor
 * @property string $currency
 * @property int $returned_points
 */
final class LoyaltyRedemption extends Model
{
    public const RESERVED = 'reserved';

    public const CONSUMED = 'consumed';

    public const RELEASED = 'released';

    protected static string $idPrefix = 'lrd';

    protected $table = 'loyalty_redemptions';

    protected function casts(): array
    {
        return [
            'points' => 'integer', 'value_minor' => 'integer', 'rate_micro' => 'integer', 'rate_amount' => 'integer', 'returned_points' => 'integer',
            'rate_valid_on' => 'date', 'reserved_at' => 'datetime', 'consumed_at' => 'datetime', 'released_at' => 'datetime',
        ];
    }
}
