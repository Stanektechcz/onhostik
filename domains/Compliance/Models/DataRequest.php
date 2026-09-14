<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance\Models;

use Onhost\Platform\Eloquent\Model;

/** GDPR export/deletion and Data Act switching requests. */
final class DataRequest extends Model
{
    protected static string $idPrefix = 'dreq';

    protected $table = 'data_requests';

    public const KINDS = ['export', 'deletion', 'switching'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'ready_at' => 'datetime', 'expires_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
