<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Onhost\Platform\Eloquent\Model;

final class SlaEvent extends Model
{
    protected static string $idPrefix = 'sle';

    protected $table = 'support_sla_events';

    protected function casts(): array
    {
        return ['met' => 'boolean', 'due_at' => 'datetime', 'measured_at' => 'datetime', 'delta_minutes' => 'integer'];
    }
}
