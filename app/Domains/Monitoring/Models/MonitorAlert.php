<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $monitor_id
 * @property string $type                    response_time | uptime | ssl_expiry
 * @property float $threshold_value
 * @property float $current_value
 * @property Carbon $triggered_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $resolved_at
 */
class MonitorAlert extends Model
{
    protected $fillable = [
        'monitor_id',
        'type',
        'threshold_value',
        'current_value',
        'triggered_at',
        'notified_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at'   => 'datetime',
            'notified_at'    => 'datetime',
            'resolved_at'    => 'datetime',
            'threshold_value' => 'decimal:2',
            'current_value'   => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'response_time' => 'Pomalá odezva',
            'uptime'        => 'Nízká dostupnost',
            'ssl_expiry'    => 'Expirující SSL',
            default         => $this->type,
        };
    }
}
