<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Models;

use Onhost\Platform\Eloquent\Model;

/**
 * A customer invited by another customer (audit §5j-2); rewarded once the invited organization pays its first invoice —
 * unless the fraud score holds it for finance (§5l-4) or refuses it outright; a chargeback after the reward claws it back.
 */
final class Referral extends Model
{
    public const PENDING = 'pending';

    public const HELD = 'held';

    public const REWARDED = 'rewarded';

    public const REFUSED = 'refused';

    public const CLAWBACK = 'clawback';

    protected static string $idPrefix = 'ref';

    protected $table = 'referrals';

    protected function casts(): array
    {
        return ['rewarded_at' => 'datetime', 'decided_at' => 'datetime', 'score' => 'integer', 'signals' => 'array'];
    }
}
