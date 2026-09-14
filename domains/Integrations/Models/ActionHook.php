<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations\Models;

use Onhost\Platform\Eloquent\Model;

final class ActionHook extends Model
{
    protected static string $idPrefix = 'ahk';

    protected $table = 'action_hooks';

    protected function casts(): array
    {
        return ['params' => 'array', 'enabled' => 'boolean', 'uses' => 'integer', 'last_used_at' => 'datetime'];
    }
}
