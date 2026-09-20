<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * A customer's request to leave a service early with part of the unused paid period returned as credit.
 *
 * @property ?Carbon $decided_at
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $refunded_at
 * @property array<int|string,mixed>|null $basis the document lines the return was computed from, fixed when the cancellation starts; after the settlement also the credit notes
 */
final class ChargebackRequest extends Model
{
    protected static string $idPrefix = 'cbk';

    protected $table = 'chargeback_requests';

    public const REQUESTED = 'requested';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLING = 'cancelling';

    public const REFUNDED = 'refunded';

    public const WITHDRAWN = 'withdrawn';

    public const OPEN = [self::REQUESTED, self::APPROVED, self::CANCELLING];

    protected function casts(): array
    {
        return ['percent' => 'integer', 'unused_minor' => 'integer', 'refund_minor' => 'integer', 'decided_at' => 'datetime', 'cancelled_at' => 'datetime', 'refunded_at' => 'datetime', 'basis' => 'array'];
    }
}
