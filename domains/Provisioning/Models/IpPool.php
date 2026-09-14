<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

final class IpPool extends Model
{
    protected static string $idPrefix = 'ipp';

    protected $table = 'ip_pools';

    protected function casts(): array
    {
        return ['dns' => 'array', 'family' => 'integer', 'vlan' => 'integer', 'reserve_count' => 'integer'];
    }
}
