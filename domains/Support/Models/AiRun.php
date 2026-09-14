<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Onhost\Platform\Eloquent\Model;

/** One assistant session turn: provider, tools used, outcome and the transcript handed to humans on handoff (blueprint §69). */
final class AiRun extends Model
{
    protected static string $idPrefix = 'air';

    protected $table = 'support_ai_runs';

    protected function casts(): array
    {
        return ['confident' => 'boolean', 'tools_called' => 'array', 'transcript' => 'array', 'input_tokens' => 'integer', 'output_tokens' => 'integer'];
    }
}
