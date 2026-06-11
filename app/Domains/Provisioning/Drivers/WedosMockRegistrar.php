<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Drivers;

use App\Domains\Provisioning\Contracts\DomainRegistrarInterface;
use App\Domains\Provisioning\DTOs\DomainCheckResult;
use App\Domains\Provisioning\DTOs\ProvisioningResult;
use Illuminate\Support\Str;

/**
 * MOCK WEDOS/VEDOS registrar — performs NO network I/O whatsoever.
 *
 * Deterministic availability rules (stable across requests, so tests and
 * manual QA behave predictably):
 *  - unsupported TLD                        → unavailable (unsupported_tld)
 *  - syntactically invalid second level     → unavailable (invalid_syntax)
 *  - listed in mock taken list (config)     → unavailable (taken)
 *  - second level starting with "taken"     → unavailable (taken)
 *  - everything else                        → available
 *
 * registerDomain() honours `$options['simulate_failure']` to exercise the
 * failure/retry path end to end.
 */
final class WedosMockRegistrar implements DomainRegistrarInterface
{
    private const DEFAULT_TAKEN = ['onhost.cz', 'google.cz', 'seznam.cz', 'wedos.cz'];

    private const DEFAULT_TLDS = ['cz', 'sk', 'eu', 'com', 'net'];

    public function checkDomain(string $fqdn): DomainCheckResult
    {
        $fqdn = Str::lower(trim($fqdn));
        [$sld, $tld] = $this->split($fqdn);

        if ($sld === null || $tld === null) {
            return DomainCheckResult::unavailable($fqdn, 'invalid_syntax');
        }

        if (!in_array($tld, $this->supportedTlds(), true)) {
            return DomainCheckResult::unavailable($fqdn, 'unsupported_tld');
        }

        if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $sld) !== 1) {
            return DomainCheckResult::unavailable($fqdn, 'invalid_syntax');
        }

        if (in_array($fqdn, $this->takenDomains(), true) || str_starts_with($sld, 'taken')) {
            return DomainCheckResult::unavailable($fqdn, 'taken');
        }

        return DomainCheckResult::available($fqdn);
    }

    /** @param array<string, mixed> $options */
    public function registerDomain(string $fqdn, array $options = []): ProvisioningResult
    {
        $fqdn = Str::lower(trim($fqdn));

        if (($options['simulate_failure'] ?? false) === true) {
            return ProvisioningResult::failure(
                errorMessage: 'Simulated WEDOS registration failure (mock mode).',
                externalRequestId: 'MOCK-WREQ-' . Str::lower(Str::random(10)),
            );
        }

        $check = $this->checkDomain($fqdn);

        if (!$check->available) {
            return ProvisioningResult::failure(
                errorMessage: "Domain {$fqdn} cannot be registered: {$check->reason} (mock mode).",
            );
        }

        /** @var list<string> $nameservers */
        $nameservers = is_array($options['nameservers'] ?? null)
            ? array_values($options['nameservers'])
            : $this->defaultNameservers();

        return ProvisioningResult::ok(
            externalId: 'MOCK-WD-' . random_int(100_000, 999_999),
            metadata: [
                'mock'          => true,
                'registered_at' => now()->toIso8601String(),
                'expires_at'    => now()->addYear()->toIso8601String(),
                'nameservers'   => $nameservers,
                'registrar'     => 'wedos',
            ],
        );
    }

    public function renewDomain(string $fqdn, int $years = 1): ProvisioningResult
    {
        return ProvisioningResult::ok(
            externalId: 'MOCK-WD-RENEW',
            metadata: ['mock' => true, 'placeholder' => true, 'fqdn' => $fqdn, 'years' => $years],
        );
    }

    /** @param list<string> $nameservers */
    public function updateNameservers(string $fqdn, array $nameservers): ProvisioningResult
    {
        return ProvisioningResult::ok(
            externalId: 'MOCK-WD-NSUPDATE',
            metadata: ['mock' => true, 'placeholder' => true, 'fqdn' => $fqdn, 'nameservers' => $nameservers],
        );
    }

    /** @return array<string, mixed> */
    public function getDomainInfo(string $fqdn): array
    {
        return [
            'mock'        => true,
            'placeholder' => true,
            'fqdn'        => $fqdn,
            'status'      => 'unknown',
        ];
    }

    // ---------------------------------------------------------------- internals

    /** @return array{0: ?string, 1: ?string} */
    private function split(string $fqdn): array
    {
        $parts = explode('.', $fqdn);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }

    /** @return list<string> */
    private function supportedTlds(): array
    {
        return $this->stringListFromConfig('provisioning.wedos.mock.supported_tlds', self::DEFAULT_TLDS);
    }

    /** @return list<string> */
    private function takenDomains(): array
    {
        return $this->stringListFromConfig('provisioning.wedos.mock.taken_domains', self::DEFAULT_TAKEN);
    }

    /** @return list<string> */
    private function defaultNameservers(): array
    {
        return $this->stringListFromConfig('provisioning.wedos.mock.nameservers', ['ns1.onhost.cz', 'ns2.onhost.cz']);
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function stringListFromConfig(string $key, array $default): array
    {
        $configured = config($key);

        if (!is_array($configured)) {
            return $default;
        }

        return array_values(array_filter($configured, 'is_string'));
    }
}
