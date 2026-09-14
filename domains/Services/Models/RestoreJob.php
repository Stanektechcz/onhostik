<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class RestoreJob extends Model
{
    protected static string $idPrefix = 'rst';

    protected $table = 'restore_jobs';

    protected function casts(): array
    {
        return ['result' => 'array', 'duration_seconds' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
