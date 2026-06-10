<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Contracts;

use App\Domains\Provisioning\DTOs\ProvisioningResult;
use App\Domains\Provisioning\DTOs\UsageStats;
use App\Domains\Provisioning\Models\Service;

/**
 * Common contract for all provisioning backends:
 * AAPanel (webhosting), WEDOS (domains), Proxmox (VPS), Pterodactyl (game servers).
 *
 * Implementation rules (enforced in code review):
 *  - NEVER call drivers from controllers; always via queued Jobs.
 *  - Every call must be idempotent: check Service::$external_id before create.
 *  - Every call must be logged via ProvisioningLogService (sanitized).
 *  - Throw domain exceptions (ProvisioningException subclasses), never raw Guzzle exceptions.
 */
interface ProvisioningDriverInterface
{
    /** Create the service on the remote backend. Must be idempotent. */
    public function create(Service $service, array $config = []): ProvisioningResult;

    /** Suspend the service (non-destructive, reversible). */
    public function suspend(Service $service): ProvisioningResult;

    /** Reactivate a suspended service. */
    public function unsuspend(Service $service): ProvisioningResult;

    /** Permanently terminate. Caller is responsible for retention policy checks. */
    public function terminate(Service $service): ProvisioningResult;

    /** Up/downgrade resources to match a new plan's resource config. */
    public function changePackage(Service $service, array $newResources): ProvisioningResult;

    /** Fetch current usage statistics. */
    public function getUsageStats(Service $service): UsageStats;

    /** Reset the primary credential; returns the new secret exactly once. */
    public function resetPassword(Service $service): string;

    /** Generate a single-use SSO login URL for the customer, if supported. */
    public function loginAsUser(Service $service): ?string;

    /** Lightweight health check of the backend connection. */
    public function testConnection(): bool;
}
