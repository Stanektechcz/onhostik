<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Models;

use Onhost\Platform\Eloquent\Model;

/** A badge an organization earned (levels reached, milestones); unique per organization + badge. */
final class LoyaltyBadge extends Model
{
    protected static string $idPrefix = 'lbg';

    protected $table = 'loyalty_badges';

    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }
}
