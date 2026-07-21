<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Service;
use Throwable;

/**
 * Shows an admin what a provision WOULD do before they run it (audit 56).
 *
 * Provisioning is a queued write against a live panel, gated behind
 * ALLOW_REAL_WRITES. The gap this fills is the moment before you press the
 * button: which driver answers, which server it lands on, whether this will
 * actually write or be simulated, and whether the backend is even reachable.
 *
 * Everything here is READ-ONLY. It resolves the same driver the job would use
 * and calls only testConnection() — never create/suspend/terminate. A preview
 * that could change state would defeat its own purpose.
 */
final class ProvisioningPreviewService
{
    public function __construct(
        private readonly DriverResolver $drivers,
        private readonly ServerSelector $servers,
    ) {}

    /**
     * @return array{
     *     driver: string|null,
     *     driver_class: string|null,
     *     target_server: array{id:int,name:string,free_slots:string}|null,
     *     writes_enabled: bool,
     *     mode: string,
     *     connection_ok: bool|null,
     *     notes: list<string>,
     * }
     */
    public function forService(Service $service): array
    {
        $driver = $service->provisioning_driver;
        $notes  = [];

        if ($driver === null) {
            return [
                'driver'         => null,
                'driver_class'   => null,
                'target_server'  => null,
                'writes_enabled' => false,
                'mode'           => 'unavailable',
                'connection_ok'  => null,
                'notes'          => ['Služba nemá přiřazený provisioning driver — zřízení nelze naplánovat.'],
            ];
        }

        // Domains go through the registrar contract, not the service driver
        // interface — the create-service preview does not apply to them.
        if ($driver === ProvisioningDriver::Wedos) {
            return [
                'driver'         => $driver->value,
                'driver_class'   => null,
                'target_server'  => null,
                'writes_enabled' => (bool) config('provisioning.wedos.allow_real_writes', false),
                'mode'           => $this->mode('provisioning.wedos.allow_real_writes'),
                'connection_ok'  => null,
                'notes'          => ['Doména se zřizuje přes registrátora, ne přes serverový driver.'],
            ];
        }

        $writesFlag = match ($driver) {
            ProvisioningDriver::AAPanel => 'provisioning.aapanel.allow_real_writes',
            default                     => null,
        };

        $concrete = $this->drivers->forService($service);

        return [
            'driver'         => $driver->value,
            'driver_class'   => class_basename($concrete),
            'target_server'  => $this->targetServer($service, $driver, $notes),
            'writes_enabled' => $writesFlag !== null && (bool) config($writesFlag, false),
            'mode'           => $writesFlag === null ? 'mock' : $this->mode($writesFlag),
            'connection_ok'  => $this->probe($concrete, $notes),
            'notes'          => $notes,
        ];
    }

    /**
     * @param  list<string>  $notes
     * @return array{id:int,name:string,free_slots:string}|null
     */
    private function targetServer(Service $service, ProvisioningDriver $driver, array &$notes): ?array
    {
        // Already pinned to a server → that is where it will go.
        $server = $service->server;

        if ($server === null) {
            // Unassigned → the same selection the job would make.
            $server = $this->servers->pick($driver);

            if ($server === null) {
                $notes[] = 'Pro tento driver není k dispozici žádný aktivní server.';

                return null;
            }

            $notes[] = 'Služba zatím nemá přiřazený server — byl by vybrán nejméně vytížený.';
        }

        return [
            'id'         => (int) $server->id,
            'name'       => (string) $server->name,
            'free_slots' => $server->max_services === null
                ? '∞'
                : (string) max(0, (int) $server->max_services - $this->liveServiceCount($server)),
        ];
    }

    /**
     * Live services on a server. Uses the withCount attribute when the server
     * came from ServerSelector, otherwise counts directly — the drift-prone
     * Server::current_services column is deliberately not trusted here.
     */
    private function liveServiceCount(\App\Domains\Provisioning\Models\Server $server): int
    {
        if (isset($server->live_services_count)) {
            return (int) $server->live_services_count;
        }

        return $server->services()
            ->whereNotIn('status', [
                \App\Domains\Provisioning\Enums\ServiceStatus::Terminated->value,
                \App\Domains\Provisioning\Enums\ServiceStatus::Failed->value,
            ])
            ->count();
    }

    /**
     * @param  list<string>  $notes
     */
    private function probe(object $driver, array &$notes): ?bool
    {
        if (! method_exists($driver, 'testConnection')) {
            return null;
        }

        try {
            return (bool) $driver->testConnection();
        } catch (Throwable $e) {
            // A failing probe is information, not an error to bubble up — the
            // whole point is to surface it before a real run.
            $notes[] = 'Test spojení selhal: ' . $e->getMessage();

            return false;
        }
    }

    private function mode(string $flag): string
    {
        return (bool) config($flag, false) ? 'live' : 'dry-run';
    }
}
