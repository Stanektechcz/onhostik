<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations\Models;

use Onhost\Platform\Eloquent\Model;

final class DiscordLink extends Model
{
    protected static string $idPrefix = 'dsl';

    protected $table = 'discord_links';

    protected function casts(): array
    {
        return ['code_expires_at' => 'datetime', 'linked_at' => 'datetime', 'last_used_at' => 'datetime', 'commands' => 'integer'];
    }
}
