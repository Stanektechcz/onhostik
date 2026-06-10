<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracked provisioning operation (create/suspend/terminate/...).
 * One DB row per logical task; queue job updates it as it progresses.
 */
class ProvisioningTask extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'service_id',
        'operation',         // create | suspend | unsuspend | terminate | change_package
        'status',
        'attempts',
        'max_attempts',
        'payload',           // sanitized input config
        'result',            // sanitized output
        'error_message',
        'external_request_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status'      => TaskStatus::class,
            'payload'     => 'array',
            'result'      => 'array',
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function canRetry(): bool
    {
        return $this->status->canRetry() && $this->attempts < $this->max_attempts;
    }
}
