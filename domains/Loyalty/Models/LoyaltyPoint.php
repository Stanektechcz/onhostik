<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Models;

use Onhost\Platform\Eloquent\Model;

/** One award of loyalty points; unique per organization + rule + reference, so nothing is counted twice. */
final class LoyaltyPoint extends Model
{
    protected static string $idPrefix = 'lpt';

    protected $table = 'loyalty_points';

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }
}
