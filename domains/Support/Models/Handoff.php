<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Onhost\Platform\Eloquent\Model;

final class Handoff extends Model
{
    protected static string $idPrefix = 'hnd';

    protected $table = 'support_handoffs';

    protected function casts(): array
    {
        return ['diagnostics' => 'array'];
    }
}
