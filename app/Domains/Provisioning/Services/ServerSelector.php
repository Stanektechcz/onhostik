<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Picks the server a new service should land on (audit E69).
 *
 * The old rule was orderByDesc('is_default') — every service went to the
 * default server until it fell over, while other active servers sat idle.
 * This spreads load by free capacity instead.
 *
 * Load is counted LIVE from the services table rather than from
 * Server::current_services: that counter is incremented on provision but
 * never decremented on termination, so it drifts upward forever and would
 * eventually make every server look full.
 */
class ServerSelector
{
    /**
     * Least-loaded active server for a driver, or null when none exists.
     */
    public function pick(ProvisioningDriver $driver): ?Server
    {
        /** @var \Illuminate\Support\Collection<int, Server> $servers */
        $servers = Server::query()
            ->where('driver', $driver->value)
            ->where('status', 'active')
            ->withCount(['services as live_services_count' => function ($query): void {
                $query->whereNotIn('status', [
                    ServiceStatus::Terminated->value,
                    ServiceStatus::Failed->value,
                ]);
            }])
            ->get();

        if ($servers->isEmpty()) {
            return null;
        }

        $withRoom = $servers->filter(fn (Server $s): bool => $this->freeSlots($s) > 0);

        if ($withRoom->isEmpty()) {
            // Refusing to provision a paid order would be worse than a tight
            // server, so fall back to the least loaded — but make it loud.
            $fallback = $this->leastLoaded($servers);

            Log::warning('provisioning.no_server_capacity', [
                'driver'         => $driver->value,
                'chosen_server'  => $fallback?->id,
                'servers_checked' => $servers->count(),
            ]);

            return $fallback;
        }

        return $this->leastLoaded($withRoom);
    }

    /**
     * Select a server AND reserve it atomically. Without this, two concurrent
     * provisions can both read the same server as having one free slot and both
     * land on it, over-filling it. A per-driver distributed lock (Redis/DB/array
     * cache) serialises select+create so the second sees the first's service in
     * the live count.
     *
     * @template T
     * @param  Closure(?Server): T  $reserve  receives the chosen server, creates the service
     * @return T
     */
    public function pickAndReserve(ProvisioningDriver $driver, Closure $reserve): mixed
    {
        $lock = Cache::lock('provisioning:server-select:' . $driver->value, 10);

        return $lock->block(5, fn () => $reserve($this->pick($driver)));
    }

    /**
     * Free slots, or PHP_INT_MAX when the server declares no limit.
     */
    private function freeSlots(Server $server): int
    {
        if ($server->max_services === null) {
            return PHP_INT_MAX;
        }

        /** @var int $live */
        $live = $server->live_services_count ?? 0;

        return max(0, $server->max_services - $live);
    }

    /**
     * Most free slots wins; ties go to the default server, then lowest id so
     * the choice is deterministic.
     *
     * @param  \Illuminate\Support\Collection<int, Server>  $servers
     */
    private function leastLoaded(\Illuminate\Support\Collection $servers): ?Server
    {
        return $servers
            ->sortBy([
                fn (Server $a, Server $b): int => $this->freeSlots($b) <=> $this->freeSlots($a),
                fn (Server $a, Server $b): int => ($b->is_default ? 1 : 0) <=> ($a->is_default ? 1 : 0),
                fn (Server $a, Server $b): int => $a->id <=> $b->id,
            ])
            ->first();
    }
}
