<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\Eloquent\Model;

/** VPS/VDS projection of a service (Proxmox executor). */
final class VirtualMachine extends Model
{
    protected static string $idPrefix = 'vm';

    protected $table = 'virtual_machines';

    protected function casts(): array
    {
        return ['vmid' => 'integer', 'cores' => 'integer', 'memory_mb' => 'integer', 'disk_gb' => 'integer', 'ssh_keys' => 'array', 'cloud_init' => 'array', 'firewall' => 'array', 'agent' => 'boolean', 'last_status' => 'array'];
    }
}
