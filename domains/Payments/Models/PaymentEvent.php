<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Onhost\Platform\Eloquent\Model;

/** Every provider callback, unique per (provider, event_id): the dedupe barrier for S51. */
final class PaymentEvent extends Model
{
    protected static string $idPrefix = 'pev';

    protected $table = 'payment_events';

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_ok' => 'boolean', 'processed_at' => 'datetime'];
    }
}
