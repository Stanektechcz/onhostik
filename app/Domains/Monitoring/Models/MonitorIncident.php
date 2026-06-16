<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorIncident extends Model
{
    protected $fillable = [
        'monitor_id',
        'severity',
        'reason',
        'started_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'resolved_at' => 'datetime',
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
}
