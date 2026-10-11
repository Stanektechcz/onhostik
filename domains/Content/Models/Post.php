<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Models;

use Onhost\Platform\Eloquent\Model;

/**
 * Blog post / static page with per-locale title, excerpt, category and body (`[[heading, paragraph], …]`). Written by staff through
 * the ContentCommand (I-R17): `state` draft | published | archived, `revision` counts the writes (the idempotency key of an edit).
 */
final class Post extends Model
{
    public const STATES = ['draft', 'published', 'archived'];

    protected static string $idPrefix = 'post';

    protected $table = 'posts';

    protected function casts(): array
    {
        return ['category' => 'array', 'title' => 'array', 'excerpt' => 'array', 'body' => 'array', 'featured' => 'boolean', 'read_minutes' => 'integer', 'published_on' => 'date', 'revision' => 'integer'];
    }

    public function text(string $field, string $locale): string
    {
        $value = $this->{$field};

        return (string) ($value[$locale] ?? $value['cs'] ?? (is_string($value) ? $value : ''));
    }

    /** @return array<string, mixed> the editor's view: every locale, every state */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id, 'slug' => $this->slug, 'kind' => $this->kind, 'state' => $this->state, 'category' => $this->category ?? [], 'title' => $this->title ?? [],
            'excerpt' => $this->excerpt ?? [], 'body' => $this->body ?? [], 'author' => $this->author, 'read_minutes' => $this->read_minutes, 'featured' => $this->featured,
            'published_on' => $this->published_on?->toDateString(), 'revision' => (int) $this->revision, 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
