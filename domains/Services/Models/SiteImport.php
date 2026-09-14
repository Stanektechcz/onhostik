<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class SiteImport extends Model
{
    protected static string $idPrefix = 'imp';

    protected $table = 'site_imports';

    protected function casts(): array
    {
        return ['stats' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
