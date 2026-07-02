<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $status pending|inprogress|done
 * @property string $priority low|medium|high
 * @property Carbon|null $due_date
 * @property int|null $assigned_to
 * @property int $created_by
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AdminTask extends Model
{
    protected $fillable = [
        'title', 'description', 'status', 'priority',
        'due_date', 'assigned_to', 'created_by', 'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeNotDone(Builder $query): Builder
    {
        return $query->where('status', '!=', 'done');
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== 'done'
            && $this->due_date->isPast();
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            'high'   => 'danger',
            'medium' => 'warning',
            default  => 'secondary',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'done'       => 'success',
            'inprogress' => 'primary',
            default      => 'warning',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'done'       => 'Dokončeno',
            'inprogress' => 'Probíhá',
            default      => 'Čeká',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'high'   => 'Vysoká',
            'medium' => 'Střední',
            default  => 'Nízká',
        };
    }
}
