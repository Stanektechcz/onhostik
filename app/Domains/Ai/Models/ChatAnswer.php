<?php

declare(strict_types=1);

namespace App\Domains\Ai\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin-curated chat answer, merged into the chatbot's knowledge base.
 *
 * @property int $id
 * @property string $category
 * @property string $question
 * @property string $keywords
 * @property string $answer
 * @property list<array{label: string, url: string}>|null $links
 * @property list<array{label: string, message: string}>|null $follow_ups
 * @property int $priority
 * @property bool $is_active
 * @property int $hits
 */
class ChatAnswer extends Model
{
    protected $fillable = [
        'category',
        'question',
        'keywords',
        'answer',
        'links',
        'follow_ups',
        'priority',
        'is_active',
        'hits',
    ];

    protected function casts(): array
    {
        return [
            'links'      => 'array',
            'follow_ups' => 'array',
            'priority'   => 'integer',
            'is_active'  => 'boolean',
            'hits'       => 'integer',
        ];
    }

    /** @return list<string> */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $k): string => trim($k),
            explode(',', $this->keywords),
        )));
    }
}
