<?php

declare(strict_types=1);

namespace App\Domains\Backups\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupPolicy extends Model
{
    protected $fillable = [
        'service_id',
        'frequency',
        'retention_days',
        'provider',
        'is_active',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'last_run_at' => 'datetime',
        ];
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
