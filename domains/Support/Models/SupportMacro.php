<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Onhost\Platform\Eloquent\Model;

final class SupportMacro extends Model
{
    protected static string $idPrefix = 'mac';

    protected $table = 'support_macros';

    protected function casts(): array
    {
        return ['body' => 'array', 'actions' => 'array'];
    }
}
