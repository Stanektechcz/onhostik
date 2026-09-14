<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class Build extends Model
{
    protected static string $idPrefix = 'bld';

    protected $table = 'builds';

    protected function casts(): array
    {
        return ['scan_result' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
