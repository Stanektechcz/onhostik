<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Actions;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Services\ServerSelector;
use Throwable;

/**
 * Drains a server — moves its live services off to other nodes.
 *
 * For maintenance or decommissioning: nobody wants to migrate services one by
 * one from the admin UI when a box is failing. Draining first flips the server
 * to `maintenance` so `ServerSelector` stops placing NEW services on it, then
 * rebinds each live service to the least-loaded active node of the same driver
 * via `MigrateServiceToServerAction` (which itself is a rebind, not a data
 * copy — the same honest limitation as the manual migrate).
 */
final class DrainServerAction
{
    public function __construct(
        private readonly ServerSelector $selector,
        private readonly MigrateServiceToServerAction $migrate,
    ) {}

    /**
     * @return array{moved: int, skipped: int, no_target: int}
     */
    public function execute(Server $server, ?string $reason = null): array
    {
        // Stop new placements immediately — even if a migration below fails, the
        // box must not keep receiving services while we drain it.
        if ($server->status === 'active') {
            $server->update(['status' => 'maintenance']);
        }

        $live = $server->services()
            ->whereNotIn('status', [ServiceStatus::Terminated->value, ServiceStatus::Failed->value])
            ->get();

        $moved = $skipped = $noTarget = 0;

        foreach ($live as $service) {
            $target = $this->selector->pick($server->driver);

            // No other active node of this driver → leave it put, count it.
            if ($target === null || $target->id === $server->id) {
                $noTarget++;

                continue;
            }

            try {
                $this->migrate->execute($service, $target, $reason ?? 'server drain');
                $moved++;
            } catch (Throwable) {
                // e.g. a terminated/failed race or full target — skip, don't abort
                // the whole drain for one service.
                $skipped++;
            }
        }

        activity('provisioning')
            ->performedOn($server)
            ->withProperties(['moved' => $moved, 'skipped' => $skipped, 'no_target' => $noTarget, 'reason' => $reason])
            ->log('server.drained');

        return ['moved' => $moved, 'skipped' => $skipped, 'no_target' => $noTarget];
    }
}
