<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;

/** poll-req notification stored before it is processed and acked (at-least-once, idempotent by remote id). */
final class RegistrarNotification extends Model
{
    protected static string $idPrefix = 'rnt';

    protected $table = 'registrar_notifications';

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'received_at' => 'datetime', 'processed_at' => 'datetime', 'acked_at' => 'datetime'];
    }
}
