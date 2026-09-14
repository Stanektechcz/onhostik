<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

final class ResourceDrift extends Model
{
    protected static string $idPrefix = 'drf';

    protected $table = 'resource_drifts';

    protected function casts(): array
    {
        return ['expected' => 'array', 'actual' => 'array', 'detected_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
