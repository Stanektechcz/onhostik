<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * What staff decide about one advisory that hits something of ours.
 *
 *     new → awaiting_approval (somebody looked at it) → approved (plan runs in waves) → resolved | partially_resolved
 *      └──────────────┴→ dismissed (not relevant, with a note)       └→ halted (a wave failed; the rest is told, not fixed)
 *
 * `announced_at` is set once customers were told; from then on a draft blog post exists (`post_id`).
 *
 * @property ?Carbon $decided_at
 * @property ?Carbon $announced_at
 * @property ?Carbon $resolved_at
 */
final class AdvisoryCase extends Model
{
    public const NEW = 'new';

    public const AWAITING = 'awaiting_approval';

    public const APPROVED = 'approved';

    public const HALTED = 'halted';

    public const RESOLVED = 'resolved';

    public const PARTIAL = 'partially_resolved';

    public const DISMISSED = 'dismissed';

    public const OPEN_STATES = [self::NEW, self::AWAITING, self::APPROVED];

    public const STATES = [self::NEW, self::AWAITING, self::APPROVED, self::HALTED, self::RESOLVED, self::PARTIAL, self::DISMISSED];

    protected static string $idPrefix = 'avc';

    protected $table = 'security_advisory_cases';

    protected function casts(): array
    {
        return ['kev' => 'boolean', 'include_risky' => 'boolean', 'matches_count' => 'integer', 'plan_revision' => 'integer', 'wave' => 'integer', 'decided_at' => 'datetime', 'announced_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Advisory, $this> */
    public function advisory(): BelongsTo
    {
        return $this->belongsTo(Advisory::class, 'advisory_id');
    }

    public function open(): bool
    {
        return in_array($this->state, self::OPEN_STATES, true);
    }
}
