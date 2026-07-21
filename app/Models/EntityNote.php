<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One internal note attached to any entity (audit 75).
 *
 * @property string $body
 * @property bool   $is_pinned
 */
class EntityNote extends Model
{
    protected $fillable = ['notable_type', 'notable_id', 'author_id', 'body', 'is_pinned'];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
