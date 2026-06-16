<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property MonitorStatus $status
 * @property Carbon|null $last_check_at
 */
class Monitor extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'type',
        'target',
        'provider',
        'status',
        'is_active',
        'last_check_at',
        'uptime_percent',
        'ssl_expires_at',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'status'         => MonitorStatus::class,
            'is_active'      => 'boolean',
            'last_check_at'  => 'datetime',
            'uptime_percent' => 'decimal:2',
            'ssl_expires_at' => 'date',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<MonitorCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    /** @return HasMany<MonitorIncident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(MonitorIncident::class);
    }
}
