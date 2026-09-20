<?php

declare(strict_types=1);

namespace Onhost\Platform\Outbox;

use Onhost\Platform\Eloquent\Model;

/**
 * Transactional outbox row. Written inside the same DB transaction as the business
 * change and relayed to listeners/webhooks afterwards (blueprint §35 P0 "Outbox +
 * idempotent jobs"). Consumers dedupe on `id`.
 *
 * @property array<string,mixed>|null $payload
 */
final class OutboxMessage extends Model
{
    protected static string $idPrefix = 'evt';

    protected $table = 'outbox_messages';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
