<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

final class OperationAttempt extends Model
{
    protected static string $idPrefix = 'opa';

    protected $table = 'operation_attempts';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'attempt' => 'integer', 'step' => 'integer'];
    }
}
