<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Actions;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Support\LogContext;
use InvalidArgumentException;

/**
 * Moves a service to a different server (audit E53).
 *
 * Until now a service was pinned to whatever server it landed on at
 * provisioning time — a full or failing box could not be drained, and a
 * customer on it simply stayed there.
 *
 * This records the INTENT and rebinds the service; it deliberately does not
 * copy files or databases. Real data movement is a driver-level operation
 * against two live panels and is gated by the same real-writes switch as every
 * other write, so it stays an operator-run step. Pretending to migrate data
 * here would be far worse than being explicit that this is a rebind.
 */
final class MigrateServiceToServerAction
{
    /**
     * @throws InvalidArgumentException when the move makes no sense
     */
    public function execute(Service $service, Server $target, ?string $reason = null): Service
    {
        $this->assertMovable($service, $target);

        $previousServerId = $service->server_id;

        return LogContext::with([
            'service_id' => $service->id,
            'from_server' => $previousServerId,
            'to_server'   => $target->id,
        ], function () use ($service, $target, $previousServerId, $reason): Service {
            $service->update([
                'server_id' => $target->id,
                // The external id belongs to the OLD panel. Clearing it forces
                // a re-sync/adopt against the new one rather than leaving a
                // dangling reference that would silently address the wrong box.
                'external_id' => null,
                'sync_state'  => null,
                'sync_message' => null,
            ]);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties([
                    'from_server_id' => $previousServerId,
                    'to_server_id'   => $target->id,
                    'reason'         => $reason,
                    // Loud about what this did and did not do.
                    'data_migrated'  => false,
                ])
                ->log('service.migrated_server');

            return $service->fresh();
        });
    }

    private function assertMovable(Service $service, Server $target): void
    {
        if ($service->server_id === $target->id) {
            throw new InvalidArgumentException('Služba už na tomto serveru je.');
        }

        if (in_array($service->status, [ServiceStatus::Terminated, ServiceStatus::Failed], true)) {
            throw new InvalidArgumentException('Ukončenou ani selhanou službu nelze migrovat.');
        }

        if ($target->status !== 'active') {
            throw new InvalidArgumentException('Cílový server není aktivní.');
        }

        /*
         | Moving between drivers would mean a different product entirely — an
         | aaPanel site cannot become a Proxmox VM by changing a foreign key.
         |
         | Both sides are cast to the ProvisioningDriver enum, so compare the
         | enums directly; comparing one against ->value silently mismatches
         | every time and would block every legitimate migration.
         */
        if ($service->provisioning_driver !== null
            && $target->driver !== $service->provisioning_driver) {
            throw new InvalidArgumentException('Cílový server má jiný driver než služba.');
        }

        if ($target->max_services !== null) {
            $live = Service::query()
                ->where('server_id', $target->id)
                ->whereNotIn('status', [ServiceStatus::Terminated->value, ServiceStatus::Failed->value])
                ->count();

            if ($live >= $target->max_services) {
                throw new InvalidArgumentException('Cílový server je plný.');
            }
        }
    }
}
