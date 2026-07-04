<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property MonitorStatus $status
 * @property Carbon|null $last_check_at
 * @property Carbon|null $ssl_expires_at
 * @property int|null $response_time_threshold_ms
 * @property float|null $uptime_threshold_percent
 * @property int $ssl_warn_days
 */
class Monitor extends Model
{
    /** @use HasFactory<\Database\Factories\MonitorFactory> */
    use HasFactory;

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
        'response_time_threshold_ms',
        'uptime_threshold_percent',
        'ssl_warn_days',
    ];

    protected function casts(): array
    {
        return [
            'status'                     => MonitorStatus::class,
            'is_active'                  => 'boolean',
            'last_check_at'              => 'datetime',
            'uptime_percent'             => 'decimal:2',
            'ssl_expires_at'             => 'date',
            'response_time_threshold_ms' => 'integer',
            'uptime_threshold_percent'   => 'decimal:2',
            'ssl_warn_days'              => 'integer',
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

    /** @return HasMany<MonitorAlert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(MonitorAlert::class);
    }

    public function hasOpenAlert(string $type): bool
    {
        return $this->alerts()
            ->where('type', $type)
            ->whereNull('resolved_at')
            ->exists();
    }
}
