<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Onhost\Platform\Eloquent\Model;

final class TicketMessage extends Model
{
    protected static string $idPrefix = 'tm';

    protected $table = 'support_messages';

    protected function casts(): array
    {
        return ['attachments' => 'array'];
    }

    /** Prototype author keys: zakaznik | podpora | ai | system. */
    public function uiFrom(): string
    {
        return match ($this->author_type) {
            'customer' => 'zakaznik', 'staff' => 'podpora', default => $this->author_type
        };
    }
}
