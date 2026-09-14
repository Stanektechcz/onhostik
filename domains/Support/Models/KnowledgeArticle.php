<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Onhost\Platform\Eloquent\Model;

/** Knowledge base article (public KB + assistant retrieval). Body is `[[heading, paragraph], …]` per locale, as the surfaces render it. */
final class KnowledgeArticle extends Model
{
    protected static string $idPrefix = 'kb';

    protected $table = 'knowledge_articles';

    protected function casts(): array
    {
        return ['title' => 'array', 'excerpt' => 'array', 'body' => 'array', 'tags' => 'array', 'read_minutes' => 'integer', 'published_at' => 'datetime'];
    }

    public function text(string $locale = 'cs'): string
    {
        $parts = [(string) ($this->title[$locale] ?? $this->title['cs'] ?? '')];
        foreach ((array) ($this->body[$locale] ?? $this->body['cs'] ?? []) as $block) {
            $parts[] = implode(' ', array_map('strval', (array) $block));
        }

        return implode("\n", $parts);
    }
}
