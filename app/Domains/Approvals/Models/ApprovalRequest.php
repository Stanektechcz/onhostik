<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A staged, awaiting-second-admin operation (audit 74).
 *
 * @property array<string, mixed>|null $payload
 */
class ApprovalRequest extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_FAILED   = 'failed';

    protected $fillable = [
        'action', 'payload', 'subject_type', 'subject_id',
        'requested_by', 'reviewed_by', 'status', 'review_note',
        'reviewed_at', 'executed_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'reviewed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
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
