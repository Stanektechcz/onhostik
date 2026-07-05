<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaTier extends Model
{
    protected $fillable = [
        'name', 'slug', 'uptime_percent_x100', 'response_time_minutes',
        'resolution_time_hours', 'credit_percent_per_hour', 'max_credit_percent', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return HasMany<SlaIncident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(SlaIncident::class);
    }

    public function uptimeLabel(): string
    {
        $val = $this->uptime_percent_x100;
        $whole = intdiv($val, 100);
        $frac  = $val % 100;

        return $frac > 0
            ? "{$whole}." . str_pad((string) $frac, 2, '0', STR_PAD_LEFT) . ' %'
            : "{$whole} %";
    }

    /** Allowed monthly downtime in minutes for a 30-day month */
    public function allowedDowntimeMinutes(): int
    {
        $uptimeFraction = $this->uptime_percent_x100 / 10000;
        return (int) round((1 - $uptimeFraction) * 30 * 24 * 60);
    }
}
