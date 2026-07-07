<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class PromotionalBanner extends Model
{
    protected $fillable = [
        'title',
        'body',
        'cta_text',
        'cta_url',
        'type',
        'placement',
        'is_active',
        'is_dismissible',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'is_dismissible' => 'boolean',
            'starts_at'      => 'date',
            'ends_at'        => 'date',
        ];
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $today = now()->startOfDay();
        if ($this->starts_at !== null && $this->starts_at->gt($today)) {
            return false;
        }
        if ($this->ends_at !== null && $this->ends_at->lt($today)) {
            return false;
        }

        return true;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<PromotionalBanner>  $query
     * @return \Illuminate\Database\Eloquent\Builder<PromotionalBanner>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', now());
            });
    }
}
