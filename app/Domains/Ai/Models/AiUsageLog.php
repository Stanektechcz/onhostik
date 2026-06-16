<?php

declare(strict_types=1);

namespace App\Domains\Ai\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'feature',
        'user_id',
        'tokens_in',
        'tokens_out',
        'cost_minor',
        'currency',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
