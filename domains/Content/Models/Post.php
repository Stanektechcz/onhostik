<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Models;

use Onhost\Platform\Eloquent\Model;

/** Blog post / static page with per-locale title, excerpt, category and body (`[[heading, paragraph], …]`). */
final class Post extends Model
{
    protected static string $idPrefix = 'post';

    protected $table = 'posts';

    protected function casts(): array
    {
        return ['category' => 'array', 'title' => 'array', 'excerpt' => 'array', 'body' => 'array', 'featured' => 'boolean', 'read_minutes' => 'integer', 'published_on' => 'date'];
    }

    public function text(string $field, string $locale): string
    {
        $value = $this->{$field};

        return (string) ($value[$locale] ?? $value['cs'] ?? (is_string($value) ? $value : ''));
    }
}
