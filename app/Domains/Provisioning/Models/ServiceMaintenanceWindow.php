<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property 'scheduled'|'in_progress'|'completed'|'cancelled' $status
 * @property Carbon      $scheduled_start
 * @property Carbon      $scheduled_end
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_end
 * @property Carbon|null $notified_at
 * @property bool        $notify_customers
 */
class ServiceMaintenanceWindow extends Model
{
    protected $fillable = [
        'service_id',
        'title',
        'description',
        'status',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'notify_customers',
        'notified_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start'  => 'datetime',
            'scheduled_end'    => 'datetime',
            'actual_start'     => 'datetime',
            'actual_end'       => 'datetime',
            'notified_at'      => 'datetime',
            'notify_customers' => 'boolean',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<self> $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereIn('status', ['scheduled', 'in_progress'])
              ->where('scheduled_end', '>', now())
              ->orderBy('scheduled_start');
    }

    /** @param Builder<self> $query */
    public function scopeForService(Builder $query, int $serviceId): void
    {
        $query->where('service_id', $serviceId);
    }

    public function isActive(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isPast(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'scheduled'   => 'Plánováno',
            'in_progress' => 'Probíhá',
            'completed'   => 'Dokončeno',
            'cancelled'   => 'Zrušeno',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'scheduled'   => 'bg-warning',
            'in_progress' => 'bg-danger',
            'completed'   => 'bg-success',
            'cancelled'   => 'bg-secondary',
        };
    }

    public function durationMinutes(): int
    {
        return (int) $this->scheduled_start->diffInMinutes($this->scheduled_end);
    }
}
