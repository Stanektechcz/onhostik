<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Clients\Concerns;

use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Exceptions\ProvisioningException;

/**
 * Five-layer refusal gate for REAL write operations.
 *
 * A real call is allowed ONLY when ALL of these hold:
 *  1. the provider row is active,
 *  2. mock_mode is off,
 *  3. dry_run is off,
 *  4. the env-level approval gate (config integrations.real_write_gates.*)
 *     is explicitly true,
 *  5. required credentials are present.
 *
 * Anything else throws (non-retryable) BEFORE any HTTP client is touched.
 * In dry-run the caller gets a simulated result instead — see the clients.
 */
trait GuardsRealCalls
{
    /** @param list<string> $requiredCredentials */
    protected function assertRealCallAllowed(
        IntegrationSetting $setting,
        string $gateKey,
        string $operation,
        array $requiredCredentials,
    ): void {
        if (!$setting->is_active) {
            throw new ProvisioningException(
                "[{$setting->provider}] {$operation}: provider is inactive.",
                driver: $setting->provider,
                retryable: false,
            );
        }

        if ($setting->mock_mode) {
            throw new ProvisioningException(
                "[{$setting->provider}] {$operation}: provider is in MOCK mode — real call refused.",
                driver: $setting->provider,
                retryable: false,
            );
        }

        if ($setting->dry_run) {
            throw new ProvisioningException(
                "[{$setting->provider}] {$operation}: provider is in DRY-RUN mode — real call refused.",
                driver: $setting->provider,
                retryable: false,
            );
        }

        if (config("integrations.real_write_gates.{$gateKey}") !== true) {
            throw new ProvisioningException(
                "[{$setting->provider}] {$operation}: env approval gate is closed — real call refused.",
                driver: $setting->provider,
                retryable: false,
            );
        }

        $credentials = $setting->credentials;

        foreach ($requiredCredentials as $key) {
            if (($credentials[$key] ?? '') === '') {
                throw new ProvisioningException(
                    "[{$setting->provider}] {$operation}: missing credential [{$key}] — real call refused.",
                    driver: $setting->provider,
                    retryable: false,
                );
            }
        }
    }

    protected function isDryRun(IntegrationSetting $setting): bool
    {
        return $setting->mock_mode || $setting->dry_run || !$setting->is_active;
    }
}
