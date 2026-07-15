<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $message
 * @property string $color
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class MaintenanceWindow extends Model
{
    /** @var list<string> */
    public const COLORS = ['warning', 'danger', 'info', 'primary'];

    protected $table = 'maintenance_windows';

    protected $fillable = [
        'title',
        'message',
        'starts_at',
        'ends_at',
        'show_on_frontend',
        'show_on_admin',
        'color',
        'is_active',
        'customers_notified_at',
        // Phase 265 fields
        'description',
        'server_id',
        'notify_customers',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'             => 'datetime',
            'ends_at'               => 'datetime',
            'show_on_frontend'      => 'boolean',
            'show_on_admin'         => 'boolean',
            'is_active'             => 'boolean',
            'notify_customers'      => 'boolean',
            'customers_notified_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<MaintenanceWindow>  $query
     * @return Builder<MaintenanceWindow>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>', now())->where('status', 'scheduled');
    }

    /**
     * Currently running banner windows (Phase 81).
     *
     * @param  Builder<MaintenanceWindow>  $query
     * @return Builder<MaintenanceWindow>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /**
     * @param  Builder<MaintenanceWindow>  $query
     * @return Builder<MaintenanceWindow>
     */
    public function scopeForAdmin(Builder $query): Builder
    {
        return $query->where('show_on_admin', true);
    }

    /**
     * @param  Builder<MaintenanceWindow>  $query
     * @return Builder<MaintenanceWindow>
     */
    public function scopeForFrontend(Builder $query): Builder
    {
        return $query->where('show_on_frontend', true);
    }

    public function isCurrentlyActive(): bool
    {
        return $this->is_active
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    public function isUpcoming(): bool
    {
        return $this->is_active && $this->starts_at->isFuture();
    }

    /**
     * @return array{active: self|null, upcoming: self|null}
     */
    public static function currentBanners(bool $isAdmin = false): array
    {
        $column = $isAdmin ? 'show_on_admin' : 'show_on_frontend';

        $active = static::where('is_active', true)
            ->where($column, true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();

        $upcoming = static::where('is_active', true)
            ->where($column, true)
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addHours(24))
            ->first();

        return ['active' => $active, 'upcoming' => $upcoming];
    }
}
