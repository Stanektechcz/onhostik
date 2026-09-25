<?php

declare(strict_types=1);

namespace Onhost\Providers\IspConfig;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ResourceRef;

/**
 * Database sizes of one ISPConfig site (TASK-0023 web-disk-total, `DatabaseSizeCapable`).
 *
 * ISPConfig answers sizes per client (`databasequota_get_by_user`, fed by the server monitor's `database_size` data),
 * and one client can hold sites the platform did not create — historical sites are untouchable and must not be
 * counted as this customer's usage. So the answer is narrowed to the databases ISPConfig itself lists under this
 * site's web domain (`sites_database_get` by `parent_domain_id`). The raw byte figure is preferred; a database the
 * monitor has not measured yet is null.
 */
trait IspConfigDatabaseSizes
{
    public function databaseSizes(ResourceRef $site): array
    {
        if ($site->remoteType !== 'web_domain' && $site->remoteType !== 'site') {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, "Database sizes are read for a web site, not for a {$site->remoteType} resource.");
        }
        $clientId = (int) ($site->meta['client_id'] ?? 0);
        if ($clientId <= 0) {
            // without the client the panel would be asked about every client's databases
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'The site has no ISPConfig client on record; refusing to read database sizes.');
        }
        $domainId = ctype_digit($site->remoteId) ? (int) $site->remoteId : 0;
        if ($domainId <= 0) {
            // parent_domain_id 0 would match the client's databases attached to no site — possibly historical ones
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'The site has no usable ISPConfig domain id; refusing to read database sizes.');
        }
        $names = [];
        foreach ((array) $this->api->call('sites_database_get', ['primary_id' => ['parent_domain_id' => $domainId]]) as $row) {
            // a row counts only when the panel itself says it belongs to this domain; a missing parent is not a match
            if (is_array($row) && (string) ($row['database_name'] ?? '') !== '' && is_numeric($row['parent_domain_id'] ?? null) && (int) $row['parent_domain_id'] === $domainId) {
                $names[] = (string) $row['database_name'];
            }
        }
        if ($names === []) {
            return [];
        }
        $sizes = [];
        foreach ((array) $this->api->call('databasequota_get_by_user', ['client_id' => $clientId]) as $row) {
            if (! is_array($row) || ! in_array((string) ($row['database_name'] ?? ''), $names, true)) {
                continue;
            }
            $used = $row['used_raw'] ?? $row['used'] ?? null;
            $sizes[(string) $row['database_name']] = is_numeric($used) ? max(0, (int) $used) : null;
        }

        return array_map(fn (string $name) => ['name' => $name, 'used_bytes' => $sizes[$name] ?? null], array_values(array_unique($names)));
    }
}
