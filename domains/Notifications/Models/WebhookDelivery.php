<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Models;

use Onhost\Platform\Eloquent\Model;

final class WebhookDelivery extends Model
{
    protected static string $idPrefix = 'whd';

    protected $table = 'webhook_deliveries';

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'response_status' => 'integer', 'next_attempt_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
