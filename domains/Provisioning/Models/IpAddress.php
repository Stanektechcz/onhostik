<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

final class IpAddress extends Model
{
    protected static string $idPrefix = 'ip';

    protected $table = 'ip_addresses';

    protected function casts(): array
    {
        return ['family' => 'integer', 'prefix_length' => 'integer', 'reserved_until' => 'datetime', 'allocated_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
