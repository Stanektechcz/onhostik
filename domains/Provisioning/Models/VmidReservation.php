<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;

/** A guest number held on one Proxmox cluster: given to one operation, or burned because somebody else took it (VmidReservations). */
final class VmidReservation extends Model
{
    protected static string $idPrefix = 'vmr';

    protected $table = 'vmid_reservations';

    protected function casts(): array
    {
        return ['vmid' => 'integer', 'burned_at' => 'datetime'];
    }
}
