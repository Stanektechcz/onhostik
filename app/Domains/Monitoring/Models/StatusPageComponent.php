<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $group_name
 * @property string $name
 * @property string|null $description
 * @property int|null $monitor_id
 * @property int $sort_order
 * @property bool $is_visible
 */
class StatusPageComponent extends Model
{
    protected $fillable = [
        'group_name',
        'name',
        'description',
        'monitor_id',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_visible'  => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function currentStatus(): string
    {
        if ($this->monitor === null) {
            return 'unknown';
        }

        return $this->monitor->status->value;
    }

    public function statusColor(): string
    {
        return match ($this->currentStatus()) {
            'up'      => 'success',
            'down'    => 'danger',
            'paused'  => 'secondary',
            default   => 'warning',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->currentStatus()) {
            'up'      => 'Funkční',
            'down'    => 'Výpadek',
            'paused'  => 'Pozastaveno',
            default   => 'Neznámý',
        };
    }
}
