<?php

declare(strict_types=1);

namespace App\Domains\Backups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupFile extends Model
{
    protected $fillable = [
        'backup_job_id',
        'disk',
        'path',
        'size_mb',
        'checksum',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
        ];
    }

    /** @return BelongsTo<BackupJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(BackupJob::class, 'backup_job_id');
    }
}
