<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Models;

use Onhost\Platform\Eloquent\Model;

final class ChangelogEntry extends Model
{
    protected static string $idPrefix = 'chg';

    protected $table = 'changelog_entries';

    public const TAGS = ['panel', 'fix', 'infra', 'api', 'game'];

    /** Prototype tag labels (`tagLabel`). */
    public const LABELS = ['cs' => ['panel' => 'PANEL', 'fix' => 'OPRAVA', 'infra' => 'INFRA', 'api' => 'API', 'game' => 'GAME'], 'en' => ['panel' => 'PANEL', 'fix' => 'FIX', 'infra' => 'INFRA', 'api' => 'API', 'game' => 'GAME']];

    protected function casts(): array
    {
        return ['title' => 'array', 'body' => 'array', 'entry_date' => 'date'];
    }
}
