<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Models;

use Onhost\Platform\Eloquent\Model;

/**
 * A customer reference on the public pages (I-R18): a quote per locale, the person's name and their role/company, an order and a
 * publish flag. Only real references approved by the person quoted are published (I-R14). `demo` marks the sample rows seeded
 * outside production; in production such a row is shown only when a member of staff published it there (`published_by`).
 */
final class ContentReference extends Model
{
    protected static string $idPrefix = 'ref';

    protected $table = 'content_references';

    protected function casts(): array
    {
        return ['quote' => 'array', 'role' => 'array', 'published' => 'boolean', 'demo' => 'boolean', 'sort' => 'integer', 'revision' => 'integer', 'published_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id, 'quote' => $this->quote ?? [], 'who' => $this->who, 'role' => $this->role ?? [], 'sort' => $this->sort, 'published' => $this->published,
            'demo' => $this->demo, 'published_at' => $this->published_at?->toIso8601String(), 'revision' => $this->revision, 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
