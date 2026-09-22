<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\VmidReservation;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ComputeProvider;

/**
 * The number of a new VM on a Proxmox cluster: never one this platform gave before (Brain cards H488, H505).
 *
 * Proxmox hands out the lowest free number, so the number of a VPS that was just cancelled and purged went straight to the
 * next order. The cancelled service's binding still held it — bindings are history, and instance/type/number is unique —
 * so the new clone was made, its binding failed, the order failed and the new VM stayed on the node bound to nothing. Had
 * it been bound, its backups would have joined the predecessor's group `vm/<number>` on the backup server, next to the
 * protected final archive of somebody else's server. Two orders running at once could also pick the same number: Proxmox
 * refused the second clone and that order failed too.
 *
 * A number is held here from the moment an operation picks it. The floor for the next one is above every number this
 * platform ever held or bound on the cluster; the unique key keeps two orders apart; a number somebody else took at the
 * panel before the clone arrived is burned and never tried again.
 */
final class VmidReservations
{
    /** How many numbers in a row one call may find taken by another order before it gives up for the moment. */
    private const ATTEMPTS = 5;

    /** The number this operation clones into: the one it already holds, else a new one. */
    public function hold(ProviderInstance $instance, ComputeProvider $compute, string $serviceId, string $operationId): int
    {
        $held = VmidReservation::query()->where('provider_instance_id', $instance->id)->where('operation_id', $operationId)->whereNull('burned_at')->max('vmid');
        if ($held !== null) {
            return (int) $held;
        }
        $floor = $this->highWater($instance) + 1;
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $vmid = $compute->reserveVmid($floor);
            try {
                // in a savepoint: on PostgreSQL a refused statement would abort a surrounding transaction
                DB::transaction(fn () => VmidReservation::query()->create(['provider_instance_id' => $instance->id, 'vmid' => $vmid, 'service_id' => $serviceId, 'operation_id' => $operationId]));

                return $vmid;
            } catch (UniqueConstraintViolationException) {
                $floor = $vmid + 1; // another order holds it since a moment ago; the panel cannot know yet
            }
        }

        throw new ProviderException('proxmox', ProviderErrorCode::TRANSIENT, 'No VMID could be held on '.$instance->key.': '.self::ATTEMPTS.' numbers in a row were taken by other orders', retryAfterSeconds: 10);
    }

    /** Somebody else took the number at the panel before the clone arrived: it is never tried again. */
    public function burn(ProviderInstance $instance, int $vmid, string $why): void
    {
        VmidReservation::query()->where('provider_instance_id', $instance->id)->where('vmid', $vmid)->update(['burned_at' => now(), 'note' => mb_substr($why, 0, 300)]);
    }

    /** The highest number this platform ever held or bound on the cluster — bindings count the VMs made before numbers were held here. */
    public function highWater(ProviderInstance $instance): int
    {
        $held = (int) VmidReservation::query()->where('provider_instance_id', $instance->id)->max('vmid');
        $bound = ProviderBinding::query()->where('provider_instance_id', $instance->id)->whereIn('remote_type', ['qemu', 'lxc'])->pluck('remote_id')
            ->reduce(fn (int $carry, mixed $id) => ctype_digit((string) $id) ? max($carry, (int) $id) : $carry, 0);

        return max($held, $bound);
    }
}
