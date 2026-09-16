<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * Backup catalogue entry (blueprint §11): where it lives, whether it was verified, when it becomes deletable.
 *
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 * @property ?Carbon $verified_at
 * @property ?Carbon $immutable_until
 * @property ?Carbon $retention_until
 * @property array<string,mixed>|null $meta
 */
final class Backup extends Model
{
    protected static string $idPrefix = 'bkp';

    protected $table = 'backups';

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'protected' => 'boolean', 'offsite' => 'boolean', 'meta' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'verified_at' => 'datetime', 'immutable_until' => 'datetime', 'retention_until' => 'datetime'];
    }
}
