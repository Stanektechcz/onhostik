<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class ServiceBackupLog extends Model
{
    protected $table = 'service_backup_logs';

    protected $fillable = [
        'service_id',
        'status',
        'size_bytes',
        'duration_seconds',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'       => 'datetime',
            'completed_at'     => 'datetime',
            'size_bytes'       => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
