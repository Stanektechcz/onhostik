<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One event for one endpoint; `payload` is the public envelope `data` (WebhookPayload), exactly what goes on the wire.
 *
 * @property ?Carbon $next_attempt_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $created_at
 */
final class WebhookDelivery extends Model
{
    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public const DEAD = 'dead';

    protected static string $idPrefix = 'whd';

    protected $table = 'webhook_deliveries';

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'response_status' => 'integer', 'next_attempt_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
