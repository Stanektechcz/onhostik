<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbArticle extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'body',
        'is_published', 'sort_order', 'locale', 'views_count',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    /** Route model binding uses slug for URL-friendly routes. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<KbArticle>  $query
     * @return Builder<KbArticle>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** @return HasMany<KbArticleVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(KbArticleVote::class);
    }

    /** @return HasMany<KbArticleReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(KbArticleReview::class);
    }

    /** @return HasMany<KbArticleComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(KbArticleComment::class, 'kb_article_id');
    }
}
