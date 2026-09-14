<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

final class BulkJob extends Model
{
    protected static string $idPrefix = 'blk';

    protected $table = 'bulk_jobs';

    protected function casts(): array
    {
        return ['params' => 'array', 'filter' => 'array', 'items' => 'array', 'total' => 'integer', 'refused' => 'integer', 'finished_at' => 'datetime'];
    }
}
