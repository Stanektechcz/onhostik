<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Illuminate\Database\Eloquent\Model;

final class Region extends Model
{
    protected $table = 'regions';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
