<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class StagingLink extends Model
{
    protected static string $idPrefix = 'stg';

    protected $table = 'staging_links';

    protected function casts(): array
    {
        return ['databases' => 'array', 'meta' => 'array', 'last_synced_at' => 'datetime', 'last_pushed_at' => 'datetime'];
    }
}
