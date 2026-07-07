<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 * @property Carbon|null $downloaded_at
 */
class GdprExportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'download_token',
        'expires_at',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'      => 'datetime',
            'downloaded_at'   => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready'
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
