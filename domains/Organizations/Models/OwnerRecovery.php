<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * A customer owner who lost access, recovered in the open (permission program D21, TASK-0042).
 *
 * @property string $organization_id
 * @property ?string $group_id the id of the row in the organization support named; the rows of one recovery share it (review round 1)
 * @property string $owner_user_id
 * @property string $mode
 * @property ?string $new_owner_user_id
 * @property string $state
 * @property string $reason
 * @property string $ticket_ref
 * @property ?string $requested_by
 * @property ?list<string> $approval_ids
 * @property Carbon $not_before
 * @property ?Carbon $cancelled_at
 * @property ?string $cancelled_by
 * @property ?Carbon $completed_at
 * @property ?string $completed_by
 * @property ?Carbon $created_at
 */
final class OwnerRecovery extends Model
{
    public const PENDING = 'pending';

    public const CANCELLED = 'cancelled';

    public const COMPLETED = 'completed';

    public const MODES = ['mfa_reset', 'transfer'];

    protected static string $idPrefix = 'orc';

    protected $table = 'owner_recoveries';

    protected function casts(): array
    {
        return ['approval_ids' => 'array', 'not_before' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
