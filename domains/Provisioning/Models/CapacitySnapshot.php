<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

final class CapacitySnapshot extends Model
{
    protected static string $idPrefix = 'cap';

    protected $table = 'capacity_snapshots';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'cpu_pct' => 'float', 'load' => 'float'];
    }
}
