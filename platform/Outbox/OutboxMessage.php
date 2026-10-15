<?php

declare(strict_types=1);

namespace Onhost\Platform\Outbox;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Onhost\Platform\Eloquent\Model;

/**
 * Transactional outbox row. Written inside the same DB transaction as the business
 * change and relayed to listeners/webhooks afterwards (blueprint §35 P0 "Outbox +
 * idempotent jobs"). Consumers dedupe on `id`. Relayed rows older than
 * `onhost.outbox.retention_days` are removed by `model:prune`; an unrelayed row is never pruned.
 *
 * @property array<string,mixed>|null $payload
 */
final class OutboxMessage extends Model
{
    use MassPrunable;

    protected static string $idPrefix = 'evt';

    protected $table = 'outbox_messages';

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return self::query()->whereNotNull('published_at')
            ->where('published_at', '<', now()->subDays(max(1, (int) config('onhost.outbox.retention_days', 30))));
    }

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
