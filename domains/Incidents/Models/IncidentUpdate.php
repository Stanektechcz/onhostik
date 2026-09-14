<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

final class IncidentUpdate extends Model
{
    protected static string $idPrefix = 'incu';

    protected $table = 'incident_updates';

    protected function casts(): array
    {
        return ['public' => 'boolean'];
    }
}
