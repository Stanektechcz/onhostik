<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * A consumer's withdrawal from a distance contract within fourteen days (TASK-0025): the evidence of the notice and the
 * way the contract was unwound — the service stopped first, then the refund to the credit, then the cancellation.
 *
 * @property Carbon $sent_at
 * @property Carbon $contract_start_at
 * @property Carbon $deadline_at
 * @property ?Carbon $refunded_at
 * @property ?Carbon $completed_at
 * @property array<string,mixed>|null $basis
 */
final class Withdrawal extends Model
{
    protected static string $idPrefix = 'wdr';

    protected $table = 'withdrawals';

    /** accepted; the service is being switched off (or waits to be) */
    public const SUSPENDING = 'suspending';

    /** switched off and the paid, unused part is back on the credit; the cancellation is next */
    public const REFUNDED = 'refunded';

    public const TERMINATING = 'terminating';

    public const COMPLETED = 'completed';

    public const OPEN = [self::SUSPENDING, self::REFUNDED, self::TERMINATING];

    public const PANEL = 'panel';

    public const STAFF = 'staff';

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime', 'contract_start_at' => 'datetime', 'deadline_at' => 'datetime', 'refunded_at' => 'datetime', 'completed_at' => 'datetime',
            'refund_minor' => 'integer', 'to_credit_minor' => 'integer', 'off_document_minor' => 'integer', 'basis' => 'array',
        ];
    }
}
