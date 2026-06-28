<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'body',
        'image', 'is_published', 'published_at', 'author_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** Route model binding uses slug. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Alias: views reference featured_image, DB stores it as image. */
    public function getFeaturedImageAttribute(): ?string
    {
        return isset($this->attributes['image']) ? $this->attributes['image'] : null;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
