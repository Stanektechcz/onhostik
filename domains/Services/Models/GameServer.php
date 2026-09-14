<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

/** Game server projection (Pterodactyl executor). */
final class GameServer extends Model
{
    protected static string $idPrefix = 'gs';

    protected $table = 'game_servers';

    protected function casts(): array
    {
        return ['nest_id' => 'integer', 'egg_id' => 'integer', 'ptero_id' => 'integer', 'ptero_user_id' => 'integer', 'ptero_node_id' => 'integer', 'allocation' => 'array', 'memory_mb' => 'integer', 'cpu_pct' => 'integer', 'disk_mb' => 'integer', 'startup' => 'array', 'last_status' => 'array'];
    }
}
