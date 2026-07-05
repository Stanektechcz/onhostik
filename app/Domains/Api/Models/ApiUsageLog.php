<?php

declare(strict_types=1);

namespace App\Domains\Api\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsageLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'token_id',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'ip_address',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isError(): bool
    {
        return $this->status_code >= 400;
    }
}
