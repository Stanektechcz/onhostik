<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

final class BackupPolicy extends Model
{
    protected static string $idPrefix = 'bpol';

    protected $table = 'backup_policies';

    protected function casts(): array
    {
        return ['schedule' => 'array', 'retention' => 'array', 'offsite' => 'boolean', 'restore_test' => 'array'];
    }
}
