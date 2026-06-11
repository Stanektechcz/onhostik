<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Contracts;

use App\Domains\Provisioning\DTOs\DomainCheckResult;
use App\Domains\Provisioning\DTOs\ProvisioningResult;

/**
 * Domain registrar contract (WEDOS WAPI / VEDOS).
 *
 * Same rules as ProvisioningDriverInterface:
 *  - NEVER call a registrar from controllers (except cheap availability
 *    checks, which are rate-limited); registrations always run via Jobs.
 *  - Every operation must be idempotent and logged via ProvisioningTask.
 *  - WEDOS quota: 1000 req/h total, 100 req/h for domain-check — the real
 *    implementation must respect config('provisioning.wedos.*') limits.
 */
interface DomainRegistrarInterface
{
    /** Check whether a fully-qualified domain can be registered. */
    public function checkDomain(string $fqdn): DomainCheckResult;

    /**
     * Register the domain. Must be idempotent (re-running for an already
     * registered domain returns the existing registration, never a duplicate).
     *
     * @param  array<string, mixed>  $options  contact/nameserver overrides, mock flags
     */
    public function registerDomain(string $fqdn, array $options = []): ProvisioningResult;

    /** Renew the registration. Placeholder until the real WAPI arrives. */
    public function renewDomain(string $fqdn, int $years = 1): ProvisioningResult;

    /**
     * Replace the nameserver set. Placeholder until the real WAPI arrives.
     *
     * @param  list<string>  $nameservers
     */
    public function updateNameservers(string $fqdn, array $nameservers): ProvisioningResult;

    /**
     * Fetch registry info for the domain. Placeholder until the real WAPI arrives.
     *
     * @return array<string, mixed>
     */
    public function getDomainInfo(string $fqdn): array;
}
