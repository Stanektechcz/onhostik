<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Onhost\Platform\Eloquent\Model;

final class SupportQueue extends Model
{
    protected static string $idPrefix = 'sq';

    protected $table = 'support_queues';

    protected function casts(): array
    {
        return ['skills' => 'array'];
    }
}
