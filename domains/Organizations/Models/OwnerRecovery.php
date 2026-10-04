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
 * @property ?string $phase while pending (TASK-0044): null = waiting for its date, contested = the person recovered objected to a transfer (staff review), offered = the heir was offered the ownership
 * @property ?Carbon $contested_at
 * @property ?string $contested_by
 * @property ?string $review_evidence what staff checked before a second person let a contested recovery continue
 * @property ?string $reviewed_by
 * @property ?Carbon $reviewed_at
 * @property ?string $cancel_reason
 * @property ?Carbon $created_at
 */
final class OwnerRecovery extends Model
{
    public const PENDING = 'pending';

    public const CANCELLED = 'cancelled';

    public const COMPLETED = 'completed';

    public const MODES = ['mfa_reset', 'transfer'];

    /** Phases of a pending recovery (TASK-0044). */
    public const CONTESTED = 'contested';

    public const OFFERED = 'offered';

    protected static string $idPrefix = 'orc';

    protected $table = 'owner_recoveries';

    protected function casts(): array
    {
        return ['approval_ids' => 'array', 'not_before' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime', 'contested_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
