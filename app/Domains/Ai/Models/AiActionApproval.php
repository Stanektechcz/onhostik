<?php

declare(strict_types=1);

namespace App\Domains\Ai\Models;

use App\Domains\Ai\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * High-risk AI tool actions NEVER execute directly — they are parked here
 * and require an explicit admin decision. Approval does not auto-execute
 * anything in this phase; execution wiring arrives with the real providers.
 *
 * @property ApprovalStatus $status
 * @property Carbon|null $reviewed_at
 */
class AiActionApproval extends Model
{
    protected $fillable = [
        'ai_run_id',
        'requested_by',
        'action_type',
        'payload',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'status'      => ApprovalStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AiRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
