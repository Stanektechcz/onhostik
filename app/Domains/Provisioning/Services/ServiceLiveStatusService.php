<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Clients\ProxmoxClient;
use App\Domains\Integrations\Clients\PterodactylClient;
use App\Domains\Integrations\Clients\WedosWapiClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Service;

/**
 * Fetches a READ-ONLY live snapshot of a service from its backend panel
 * (aaPanel / Proxmox / Pterodactyl / WEDOS) for the admin Service 360° page.
 *
 * All calls respect the integration's mock/dry-run state — in mock mode the
 * clients return simulated payloads and no HTTP is ever sent. No write
 * operation is reachable from here.
 */
class ServiceLiveStatusService
{
    /**
     * @return array{provider: string|null, label: string, dry_run: bool, ok: bool, data: array<string, mixed>, error: string|null}
     */
    public function fetch(Service $service): array
    {
        $driver = $service->provisioning_driver;

        if ($driver === null) {
            return $this->result(null, 'Bez driveru', dryRun: false, ok: false, error: 'Služba nemá přiřazený provisioning driver.');
        }

        $setting = IntegrationSetting::query()->firstOrCreate(
            ['provider' => $driver->value],
            ['label' => $driver->label(), 'mock_mode' => true, 'dry_run' => true],
        );

        try {
            return match ($driver) {
                ProvisioningDriver::AAPanel     => $this->fromAapanel($service, $setting),
                ProvisioningDriver::Proxmox     => $this->fromProxmox($service, $setting),
                ProvisioningDriver::Pterodactyl => $this->fromPterodactyl($service, $setting),
                ProvisioningDriver::Wedos       => $this->fromWedos($service, $setting),
            };
        } catch (\Throwable $e) {
            return $this->result($driver->value, $driver->label(), dryRun: false, ok: false, error: $e->getMessage());
        }
    }

    /** @return array{provider: string|null, label: string, dry_run: bool, ok: bool, data: array<string, mixed>, error: string|null} */
    private function fromAapanel(Service $service, IntegrationSetting $setting): array
    {
        $siteName = $service->label ?? (string) $service->external_id;

        if ($siteName === '') {
            return $this->result('aapanel', 'AAPanel (webhosting)', dryRun: false, ok: false, error: 'Služba nemá label ani external_id — web nelze dohledat.');
        }

        $response = (new AapanelClient($setting))->getSiteOverview($siteName);
        $dryRun   = (bool) ($response['dry_run'] ?? false);
        $site     = is_array($response['site'] ?? null) ? $response['site'] : [];

        return $this->result('aapanel', 'AAPanel (webhosting)', $dryRun, ok: true, data: $this->flatten($site));
    }

    /** @return array{provider: string|null, label: string, dry_run: bool, ok: bool, data: array<string, mixed>, error: string|null} */
    private function fromProxmox(Service $service, IntegrationSetting $setting): array
    {
        $vmid = (int) $service->external_id;

        if ($vmid <= 0) {
            return $this->result('proxmox', 'Proxmox VE (VPS)', dryRun: false, ok: false, error: 'Služba nemá platné VMID v external_id.');
        }

        $client = new ProxmoxClient($setting);
        $status = $client->getVMStatus($vmid);
        $dryRun = $setting->mock_mode || !$setting->is_active;

        return $this->result('proxmox', 'Proxmox VE (VPS)', $dryRun, ok: true, data: $this->flatten($status));
    }

    /** @return array{provider: string|null, label: string, dry_run: bool, ok: bool, data: array<string, mixed>, error: string|null} */
    private function fromPterodactyl(Service $service, IntegrationSetting $setting): array
    {
        $serverId = (int) $service->external_id;

        if ($serverId <= 0) {
            return $this->result('pterodactyl', 'Pterodactyl (gamehosting)', dryRun: false, ok: false, error: 'Služba nemá platné ID serveru v external_id.');
        }

        $client = new PterodactylClient($setting);
        $server = $client->getServer($serverId);
        $dryRun = $setting->mock_mode || !$setting->is_active;

        return $this->result('pterodactyl', 'Pterodactyl (gamehosting)', $dryRun, ok: true, data: $this->flatten($server));
    }

    /** @return array{provider: string|null, label: string, dry_run: bool, ok: bool, data: array<string, mixed>, error: string|null} */
    private function fromWedos(Service $service, IntegrationSetting $setting): array
    {
        $fqdn = $service->domainRegistration?->fqdn() ?? (string) $service->label;

        if ($fqdn === '') {
            return $this->result('wedos', 'WEDOS WAPI (domény)', dryRun: false, ok: false, error: 'Služba nemá doménu k dohledání.');
        }

        $response = (new WedosWapiClient($setting))->getDomainInfo($fqdn);
        $dryRun   = (bool) ($response['dry_run'] ?? false);

        return $this->result('wedos', 'WEDOS WAPI (domény)', $dryRun, ok: true, data: $this->flatten($response));
    }

    /**
     * Flatten nested arrays into displayable key => scalar pairs (max 20 rows).
     *
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private function flatten(array $data): array
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

    /**
     * @param  array<string, mixed>  $data
     * @return array{provider: string|null, label: string, dry_run: bool, ok: bool, data: array<string, mixed>, error: string|null}
     */
    private function result(?string $provider, string $label, bool $dryRun, bool $ok, array $data = [], ?string $error = null): array
    {
        return [
            'provider' => $provider,
            'label'    => $label,
            'dry_run'  => $dryRun,
            'ok'       => $ok,
            'data'     => $data,
            'error'    => $error,
        ];
    }
}
