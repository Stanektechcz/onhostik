<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Clients\ProxmoxClient;
use App\Domains\Integrations\Clients\PterodactylClient;
use App\Domains\Integrations\Clients\WedosWapiClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\ServiceSyncState;
use App\Domains\Provisioning\Models\Service;

/**
 * Reconciles a local Service against its backend panel — READ ONLY.
 *
 * This answers the question the billing side cannot: "the customer paid and
 * we flipped the service to Active, but does the site actually exist in
 * aaPanel?". A service that was billed but never created shows up here as
 * ServiceSyncState::MissingRemote instead of looking healthy.
 *
 * Only pure-GET endpoints are used. aaPanel reads go through
 * getSiteOverview(), which is explicitly NOT behind the
 * AAPANEL_ALLOW_REAL_WRITES gate — verification never needs write access and
 * this service must never be able to create, modify or delete anything.
 *
 * In mock/dry-run mode the clients return simulated payloads, so a positive
 * result would be meaningless. Those cases return Unsupported rather than
 * InSync — we refuse to report false assurance.
 */
class ServiceRemoteSyncService
{
    /**
     * @return array{state: ServiceSyncState, message: string, remote: array<string, mixed>, dry_run: bool}
     */
    public function sync(Service $service, bool $persist = true): array
    {
        $result = $this->probe($service);

        if ($persist) {
            $this->persist($service, $result);
        }

        return $result;
    }

    /**
     * @return array{state: ServiceSyncState, message: string, remote: array<string, mixed>, dry_run: bool}
     */
    private function probe(Service $service): array
    {
        $driver = $service->provisioning_driver;

        if ($driver === null) {
            return $this->result(ServiceSyncState::Unsupported, 'Služba nemá přiřazený provisioning driver.');
        }

        $setting = IntegrationSetting::query()->firstOrCreate(
            ['provider' => $driver->value],
            ['label' => $driver->label(), 'mock_mode' => true, 'dry_run' => true],
        );

        if (! $setting->is_active || $setting->mock_mode || $setting->dry_run) {
            return $this->result(
                ServiceSyncState::Unsupported,
                'Integrace je v mock/dry-run režimu — skutečný stav v panelu nelze ověřit.',
                dryRun: true,
            );
        }

        try {
            [$exists, $remote, $remoteId, $remoteRunning] = $this->lookup($service, $driver, $setting);
        } catch (\Throwable $e) {
            return $this->result(ServiceSyncState::Error, 'Chyba při dotazu do panelu: ' . $e->getMessage());
        }

        return $this->verdict($service, $exists, $remote, $remoteId, $remoteRunning);
    }

    /**
     * Read-only existence probe.
     *
     * @return array{0: bool, 1: array<string, mixed>, 2: string|null, 3: bool|null}
     */
    private function lookup(Service $service, ProvisioningDriver $driver, IntegrationSetting $setting): array
    {
        return match ($driver) {
            ProvisioningDriver::AAPanel     => $this->lookupAapanel($service, $setting),
            ProvisioningDriver::Proxmox     => $this->lookupProxmox($service, $setting),
            ProvisioningDriver::Pterodactyl => $this->lookupPterodactyl($service, $setting),
            ProvisioningDriver::Wedos       => $this->lookupWedos($service, $setting),
        };
    }

    /** @return array{0: bool, 1: array<string, mixed>, 2: string|null, 3: bool|null} */
    private function lookupAapanel(Service $service, IntegrationSetting $setting): array
    {
        $siteName = (string) ($service->label ?? '');

        if ($siteName === '') {
            throw new \RuntimeException('Služba nemá doménu (label) k dohledání.');
        }

        $response = (new AapanelClient($setting))->getSiteOverview($siteName);
        $site     = is_array($response['site'] ?? null) ? $response['site'] : [];

        // aaPanel's getBySearch returns either a list of rows or {data: [rows]}.
        $rows = is_array($site['data'] ?? null) ? $site['data'] : $site;
        $rows = array_values(array_filter($rows, 'is_array'));

        $match = null;
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '' && strcasecmp($name, $siteName) === 0) {
                $match = $row;
                break;
            }
        }

        if ($match === null) {
            return [false, [], null, null];
        }

        $status  = (string) ($match['status'] ?? '');
        $running = $status === '' ? null : in_array($status, ['1', 'run', 'running'], true);

        return [true, $this->scalarize($match), isset($match['id']) ? (string) $match['id'] : null, $running];
    }

    /** @return array{0: bool, 1: array<string, mixed>, 2: string|null, 3: bool|null} */
    private function lookupProxmox(Service $service, IntegrationSetting $setting): array
    {
        $vmid = (int) $service->external_id;

        if ($vmid <= 0) {
            return [false, [], null, null];
        }

        $status  = (new ProxmoxClient($setting))->getVMStatus($vmid);
        $state   = (string) ($status['status'] ?? '');
        $running = $state === '' ? null : $state === 'running';

        return [$status !== [], $this->scalarize($status), (string) $vmid, $running];
    }

    /** @return array{0: bool, 1: array<string, mixed>, 2: string|null, 3: bool|null} */
    private function lookupPterodactyl(Service $service, IntegrationSetting $setting): array
    {
        $serverId = (int) $service->external_id;

        if ($serverId <= 0) {
            return [false, [], null, null];
        }

        $server = (new PterodactylClient($setting))->getServer($serverId);

        $suspended = $server['suspended'] ?? null;
        $running   = is_bool($suspended) ? ! $suspended : null;

        return [$server !== [], $this->scalarize($server), (string) $serverId, $running];
    }

    /** @return array{0: bool, 1: array<string, mixed>, 2: string|null, 3: bool|null} */
    private function lookupWedos(Service $service, IntegrationSetting $setting): array
    {
        $fqdn = $service->domainRegistration?->fqdn() ?? (string) $service->label;

        if ($fqdn === '') {
            throw new \RuntimeException('Služba nemá doménu k dohledání.');
        }

        $response = (new WedosWapiClient($setting))->getDomainInfo($fqdn);

        return [$response !== [], $this->scalarize($response), $fqdn, null];
    }

    /**
     * Turn the probe into a verdict, weighed against the LOCAL status.
     *
     * @param  array<string, mixed>  $remote
     * @return array{state: ServiceSyncState, message: string, remote: array<string, mixed>, dry_run: bool}
     */
    private function verdict(Service $service, bool $exists, array $remote, ?string $remoteId, ?bool $remoteRunning): array
    {
        $status = $service->status;
        $live   = in_array($status, [ServiceStatus::Active, ServiceStatus::Suspended], true);

        if (! $exists) {
            // The important case: billed and marked live, but nothing there.
            if ($live) {
                return $this->result(
                    ServiceSyncState::MissingRemote,
                    "Služba je lokálně ve stavu {$status->value}, ale v panelu neexistuje — je potřeba ji zřídit.",
                    $remote,
                );
            }

            return $this->result(
                ServiceSyncState::InSync,
                'Zatím nezřízeno — služba čeká na provisioning.',
                $remote,
            );
        }

        // Found remotely but we never recorded the id → adopt it.
        if (($service->external_id === null || $service->external_id === '') && $remoteId !== null) {
            return $this->result(
                ServiceSyncState::Adopted,
                "V panelu dohledáno (ID {$remoteId}) a spárováno s touto službou.",
                $remote,
                remoteId: $remoteId,
            );
        }

        if ($status === ServiceStatus::Pending) {
            return $this->result(
                ServiceSyncState::Adopted,
                'V panelu už existuje, ale lokálně je stav Pending — zkontrolujte dokončení provisioningu.',
                $remote,
                remoteId: $remoteId,
            );
        }

        if ($remoteRunning !== null) {
            if ($status === ServiceStatus::Active && $remoteRunning === false) {
                return $this->result(
                    ServiceSyncState::StatusMismatch,
                    'Lokálně Active, ale v panelu je zastavená/pozastavená.',
                    $remote,
                    remoteId: $remoteId,
                );
            }

            if ($status === ServiceStatus::Suspended && $remoteRunning === true) {
                return $this->result(
                    ServiceSyncState::StatusMismatch,
                    'Lokálně Suspended, ale v panelu běží.',
                    $remote,
                    remoteId: $remoteId,
                );
            }
        }

        return $this->result(ServiceSyncState::InSync, 'Stav v panelu odpovídá lokálnímu záznamu.', $remote, remoteId: $remoteId);
    }

    /** @param array{state: ServiceSyncState, message: string, remote: array<string, mixed>, dry_run: bool, remote_id?: string|null} $result */
    private function persist(Service $service, array $result): void
    {
        $attributes = [
            'last_synced_at' => now(),
            'sync_state'     => $result['state']->value,
            'sync_message'   => mb_substr($result['message'], 0, 500),
        ];

        // Adopting a discovered id is the only write this class performs, and
        // it only ever fills an id that was empty — it never overwrites one.
        $remoteId = $result['remote_id'] ?? null;
        if (is_string($remoteId) && $remoteId !== '' && ($service->external_id === null || $service->external_id === '')) {
            $attributes['external_id'] = $remoteId;
        }

        $service->forceFill($attributes)->save();

        if ($result['state']->needsAttention()) {
            activity('service')
                ->performedOn($service)
                ->withProperties(['sync_state' => $result['state']->value, 'message' => $result['message']])
                ->log('service.sync_drift');
        }
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array{state: ServiceSyncState, message: string, remote: array<string, mixed>, dry_run: bool, remote_id: string|null}
     */
    private function result(
        ServiceSyncState $state,
        string $message,
        array $remote = [],
        bool $dryRun = false,
        ?string $remoteId = null,
    ): array {
        return [
            'state'     => $state,
            'message'   => $message,
            'remote'    => $remote,
            'dry_run'   => $dryRun,
            'remote_id' => $remoteId,
        ];
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private function scalarize(array $data): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if (count($flat) >= 20) {
                break;
            }
            if (is_scalar($value) || $value === null) {
                $flat[(string) $key] = $value;
            } elseif (is_array($value)) {
                $flat[(string) $key] = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            }
        }

        return $flat;
    }
}
