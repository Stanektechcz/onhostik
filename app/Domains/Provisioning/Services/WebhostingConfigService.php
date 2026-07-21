<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Service;
use Throwable;

/**
 * Reads the COMPLETE aaPanel configuration of a webhosting service —
 * databases, FTP accounts, PHP version, SSL and cron — so the service detail
 * shows the same picture the panel does.
 *
 * Read-only. Every call is a pure GET that does not require the
 * AAPANEL_ALLOW_REAL_WRITES gate; a customer viewing their own configuration
 * must never need write access.
 *
 * Each section fails independently and reports WHY (audit E82: the old live
 * status silently rendered an empty box when the integration errored, which
 * looked identical to "you have nothing configured").
 */
class WebhostingConfigService
{
    /**
     * @return array{
     *   supported: bool,
     *   dry_run: bool,
     *   site: string,
     *   php: array{current: string|null, available: array<string, string>, error: string|null},
     *   ssl: array{active: bool, detail: array<string, mixed>, error: string|null},
     *   databases: array{items: list<array<string, mixed>>, error: string|null},
     *   ftp: array{items: list<array<string, mixed>>, error: string|null},
     *   cron: array{items: list<array<string, mixed>>, error: string|null},
     *   mailboxes: array{items: list<array<string, mixed>>, error: string|null}
     * }
     */
    public function forService(Service $service): array
    {
        $siteName = (string) ($service->label ?? '');

        $empty = [
            'supported' => false,
            'dry_run'   => false,
            'site'      => $siteName,
            'php'       => ['current' => null, 'available' => [], 'error' => null],
            'ssl'       => ['active' => false, 'detail' => [], 'error' => null],
            'databases' => ['items' => [], 'error' => null],
            'ftp'       => ['items' => [], 'error' => null],
            'cron'      => ['items' => [], 'error' => null],
            'mailboxes' => ['items' => [], 'error' => null],
        ];

        if ($service->provisioning_driver !== ProvisioningDriver::AAPanel || $siteName === '') {
            return $empty;
        }

        $setting = IntegrationSetting::query()->firstOrCreate(
            ['provider' => ProvisioningDriver::AAPanel->value],
            ['label' => ProvisioningDriver::AAPanel->label(), 'mock_mode' => true, 'dry_run' => true],
        );

        $client = new AapanelClient($setting);
        $dryRun = ! $setting->is_active || $setting->mock_mode || $setting->dry_run;

        /** @var array<string, mixed> $resources */
        $resources = $service->resources ?? [];
        $currentPhp = is_string($resources['php_version'] ?? null) ? $resources['php_version'] : null;

        return [
            'supported' => true,
            'dry_run'   => $dryRun,
            'site'      => $siteName,
            'php'       => $this->php($client, $currentPhp),
            'ssl'       => $this->ssl($client, $siteName),
            'databases' => $this->rows(fn (): array => $client->listDatabases($siteName)),
            'ftp'       => $this->rows(fn (): array => $client->listFtpAccounts($siteName)),
            'cron'      => $this->rows(fn (): array => $client->listCronJobs()),
            // Mail needs aaPanel's mail_sys plugin; when it is absent the
            // panel errors and the section says so rather than showing empty.
            'mailboxes' => $this->rows(fn (): array => $client->listMailboxes($siteName)),
        ];
    }

    /** @return array{current: string|null, available: array<string, string>, error: string|null} */
    private function php(AapanelClient $client, ?string $current): array
    {
        try {
            $response = $client->getPhpVersions();
            $rows     = $this->extractRows($response);

            $available = [];
            foreach ($rows as $row) {
                $version = (string) ($row['version'] ?? '');

                // '00' is aaPanel's "Static" pseudo-build — not a PHP runtime.
                if ($version === '' || $version === '00') {
                    continue;
                }

                $available[$version] = (string) ($row['name'] ?? ('PHP ' . $version));
            }

            return ['current' => $current, 'available' => $available, 'error' => null];
        } catch (Throwable $e) {
            return ['current' => $current, 'available' => [], 'error' => $e->getMessage()];
        }
    }

    /** @return array{active: bool, detail: array<string, mixed>, error: string|null} */
    private function ssl(AapanelClient $client, string $siteName): array
    {
        try {
            $response = $client->getSslInfo($siteName);
            $active   = ($response['status'] ?? false) === true;

            return ['active' => $active, 'detail' => $this->scalars($response), 'error' => null];
        } catch (Throwable $e) {
            return ['active' => false, 'detail' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $fetch
     * @return array{items: list<array<string, mixed>>, error: string|null}
     */
    private function rows(callable $fetch): array
    {
        try {
            return ['items' => $this->extractRows($fetch()), 'error' => null];
        } catch (Throwable $e) {
            return ['items' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * aaPanel returns either a bare list or {data: [...]} depending on endpoint.
     *
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function extractRows(array $response): array
    {
        $rows = is_array($response['data'] ?? null) ? $response['data'] : $response;

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->scalars($row);
            }
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $row
     * @return array<string, mixed>
     */
    private function scalars(array $row): array
    {
        $flat = [];

        foreach ($row as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $flat[(string) $key] = $value;
            }
        }

        return $flat;
    }
}
