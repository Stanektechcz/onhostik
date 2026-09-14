<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Models;

use Onhost\Platform\Eloquent\Model;

/** Point of presence shown on the public site (`locations(cs)` → `[code, city, country, ping, live]`). */
final class Location extends Model
{
    protected $table = 'locations';

    protected $primaryKey = 'code';

    protected function casts(): array
    {
        return ['country' => 'array', 'live' => 'boolean', 'ping_ms' => 'integer', 'sort' => 'integer'];
    }
}
