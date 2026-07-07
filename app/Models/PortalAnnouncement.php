<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class PortalAnnouncement extends Model
{
    protected $table = 'portal_announcements';

    protected $fillable = [
        'title',
        'body',
        'type',
        'target_audience',
        'is_published',
        'published_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    /**
     * @param  Builder<PortalAnnouncement>  $query
     * @return Builder<PortalAnnouncement>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isCurrentlyVisible(): bool
    {
        return $this->is_published && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
