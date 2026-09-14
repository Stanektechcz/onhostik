<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Models;

use Onhost\Platform\Eloquent\Model;

/** Transactional mail queue (`queued → sent | failed`), the "Transakční maily" surface reads it. */
final class MailOutbox extends Model
{
    protected static string $idPrefix = 'mail';

    protected $table = 'mail_outbox';

    protected function casts(): array
    {
        return ['vars' => 'array', 'attempts' => 'integer', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
