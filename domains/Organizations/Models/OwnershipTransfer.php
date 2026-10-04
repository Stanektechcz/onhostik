<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * An offer of the organization's ownership to one of its members, moved only when that member accepts (I4, TASK-0042).
 *
 * @property string $organization_id
 * @property string $from_user_id
 * @property string $to_user_id
 * @property string $state
 * @property Carbon $expires_at
 * @property ?Carbon $decided_at
 * @property ?string $decided_by
 * @property ?string $recovery_id the owner recovery (transfer mode) that made this offer; only support withdraws it (TASK-0044)
 * @property ?Carbon $created_at
 */
final class OwnershipTransfer extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected static string $idPrefix = 'otr';

    protected $table = 'ownership_transfers';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
