<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticleReview extends Model
{
    protected $fillable = ['kb_article_id', 'user_id', 'author_name', 'content', 'is_visible'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    /** @return BelongsTo<KbArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
