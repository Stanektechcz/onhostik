<?php

declare(strict_types=1);

namespace App\Domains\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ai_run_id',
        'role',
        'content',
    ];

    /** @return BelongsTo<AiRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }
}
