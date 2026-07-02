<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracked provisioning operation (create/suspend/terminate/...).
 * One DB row per logical task; queue job updates it as it progresses.
 *
 * @property TaskStatus $status
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $result
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class ProvisioningTask extends Model
{
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

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function canRetry(): bool
    {
        return $this->status->canRetry() && $this->attempts < $this->max_attempts;
    }
}
