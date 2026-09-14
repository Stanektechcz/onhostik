<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;

/** One scheduled renewal per domain per expiry (blueprint §46.3). */
final class DomainRenewalJob extends Model
{
    protected static string $idPrefix = 'drj';

    protected $table = 'domain_renewal_jobs';

    public const SCHEDULED = 'SCHEDULED';

    public const HOLD_PLACED = 'HOLD_PLACED';

    public const SENT = 'SENT';

    public const PENDING_REGISTRY = 'PENDING_REGISTRY';

    public const SUCCEEDED = 'SUCCEEDED';

    public const FAILED = 'FAILED';

    public const SKIPPED = 'SKIPPED';

    protected function casts(): array
    {
        return ['period_years' => 'integer', 'attempts' => 'integer', 'notices_sent' => 'array', 'due_at' => 'datetime', 'scheduled_for' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return in_array($this->state, [self::SCHEDULED, self::HOLD_PLACED, self::SENT, self::PENDING_REGISTRY], true);
    }
}
