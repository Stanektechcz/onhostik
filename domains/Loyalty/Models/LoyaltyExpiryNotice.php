<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Models;

use Onhost\Platform\Eloquent\Model;

/** The one warning an organization gets for the points that expire in one month (G-R2); unique per organization + month. */
final class LoyaltyExpiryNotice extends Model
{
    protected static string $idPrefix = 'lxn';

    protected $table = 'loyalty_expiry_notices';

    protected function casts(): array
    {
        return ['points' => 'integer', 'notified_at' => 'datetime'];
    }
}
