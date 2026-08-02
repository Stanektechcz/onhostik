<?php

declare(strict_types=1);

namespace App\Domains\Backups\Models;

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property BackupJobStatus $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 */
class BackupJob extends Model
{
    protected $fillable = [
        'backup_policy_id',
        'service_id',
        'type',
        'status',
        'started_at',
        'finished_at',
        'size_mb',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status'      => BackupJobStatus::class,
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BackupPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(BackupPolicy::class, 'backup_policy_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<BackupFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(BackupFile::class);
    }
}
