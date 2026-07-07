<?php

declare(strict_types=1);

namespace App\Domains\Backups\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int         $scheduled_hour     Hour (0–23, UTC) when the backup should run
 * @property int|null    $scheduled_weekday  Day of week (0=Mon…6=Sun) for weekly policies
 * @property bool        $notify_on_failure  Whether to email the customer on backup failure
 * @property Carbon|null $last_run_at
 */
class BackupPolicy extends Model
{
    protected $fillable = [
        'service_id',
        'frequency',
        'scheduled_hour',
        'scheduled_weekday',
        'retention_days',
        'provider',
        'is_active',
        'notify_on_failure',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'notify_on_failure' => 'boolean',
            'last_run_at'       => 'datetime',
        ];
    }

    /** Human-readable label for the backup frequency. */
    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            'weekly'  => 'Týdně',
            'monthly' => 'Měsíčně',
            default   => 'Denně',
        };
    }

    /** Czech day-of-week label for weekly policies. */
    public function weekdayLabel(): string
    {
        return match ((int) $this->scheduled_weekday) {
            0 => 'Pondělí',
            1 => 'Úterý',
            2 => 'Středa',
            3 => 'Čtvrtek',
            4 => 'Pátek',
            5 => 'Sobota',
            6 => 'Neděle',
            default => '—',
        };
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<BackupJob, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(BackupJob::class);
    }
}
