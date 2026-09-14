<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization\Models;

use Illuminate\Database\Eloquent\Model;

final class PermissionDefinition extends Model
{
    protected $table = 'permission_definitions';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['step_up' => 'boolean', 'four_eyes' => 'boolean'];
    }
}
