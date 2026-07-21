<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A long-running export the customer/admin asked for (audit L120).
 *
 * @property array<string, mixed>|null $parameters
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class ExportJob extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_READY   = 'ready';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'user_id', 'type', 'status', 'format', 'parameters',
        'file_path', 'file_size', 'row_count',
        'error_message', 'started_at', 'finished_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters'  => 'array',
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
            'expires_at'  => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Downloadable only while it is both ready AND unexpired — an expired row
     * may still have a file on disk that the cleanup job has not reached yet.
     */
    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @param  Builder<ExportJob>  $query
     * @return Builder<ExportJob>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }
}
