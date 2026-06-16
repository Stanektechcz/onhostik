<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Drivers;

use App\Domains\Provisioning\Contracts\DomainRegistrarInterface;
use App\Domains\Provisioning\DTOs\DomainCheckResult;
use App\Domains\Provisioning\DTOs\ProvisioningResult;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Production WEDOS WAPI domain registrar.
 *
 * Authentication: every request includes a time-based SHA1 token.
 *   auth = SHA1( user . SHA1(password) . HOUR )
 *   HOUR = current UTC hour, zero-padded (gmdate('H'))
 *
 * Credentials required in config/provisioning.php (from .env):
 *   WAPI_USER     — WEDOS account e-mail
 *   WAPI_PASSWORD — WEDOS account password (plaintext; never logged)
 *   WAPI_URL      — https://api.wedos.com/wapi/json  (default)
 *   WAPI_TEST_MODE=true — sends "test":1 in every request; WEDOS processes
 *                         the command but does NOT actually register/renew.
 *
 * Env gates:
 *   WAPI_ALLOW_REAL_WRITES=true  — must be explicitly enabled for
 *     registerDomain() and renewDomain() to send live WAPI commands.
 *
 * Security:
 *   - WAPI_PASSWORD never logged (only 'password_set: true' is safe).
 *   - $this->password never appears in exceptions or activity logs.
 *
 * WEDOS rate limits (enforced by WEDOS, not here):
 *   1000 req/h total — 100 req/h for domain-check / domain-create.
 *
 * Response code 1000 = success. Anything else is an error.
 */
final class WedosProductionRegistrar implements DomainRegistrarInterface
{
    private readonly string $user;
    private readonly string $password;
    private readonly string $url;
    private readonly bool   $testMode;
    private readonly int    $timeout;

    public function __construct()
    {
        $this->user     = (string) config('provisioning.wedos.user', '');
        $this->password = (string) config('provisioning.wedos.password', '');
        $this->url      = (string) config('provisioning.wedos.url', 'https://api.wedos.com/wapi/json');
        $this->testMode = (bool) config('provisioning.wedos.test_mode', true);
        $this->timeout  = (int) config('provisioning.wedos.timeout', 30);

        if ($this->user === '' || $this->password === '') {
            throw new ProvisioningException(
                'WEDOS WAPI credentials not configured — set WAPI_USER and WAPI_PASSWORD.',
                driver: 'wedos',
                retryable: false,
            );
        }
    }

    public function checkDomain(string $fqdn): DomainCheckResult
    {
        $fqdn = Str::lower(trim($fqdn));
        [$sld, $tld] = $this->splitFqdn($fqdn);

        if ($sld === null || $tld === null) {
            return DomainCheckResult::unavailable($fqdn, 'invalid_syntax');
        }

        try {
            $data  = $this->command('domain-check', ['name' => $sld, 'tld' => $tld]);
            $avail = (int) ($data['avail'] ?? 0);

            return $avail === 1
                ? DomainCheckResult::available($fqdn)
                : DomainCheckResult::unavailable($fqdn, 'taken');
        } catch (\Throwable) {
            return DomainCheckResult::unavailable($fqdn, 'check_failed');
        }
    }

    /** @param array<string, mixed> $options */
    public function registerDomain(string $fqdn, array $options = []): ProvisioningResult
    {
        if (! (bool) config('provisioning.wedos.allow_real_writes', false)) {
            return ProvisioningResult::failure(
                'WAPI_ALLOW_REAL_WRITES is not enabled — refusing to register domain.',
            );
        }

        $fqdn = Str::lower(trim($fqdn));
        [$sld, $tld] = $this->splitFqdn($fqdn);

        if ($sld === null || $tld === null) {
            return ProvisioningResult::failure("Invalid domain FQDN: {$fqdn}");
        }

        /** @var list<string> $nameservers */
        $nameservers = is_array($options['nameservers'] ?? null)
            ? array_values($options['nameservers'])
            : $this->defaultNameservers();

        $commandData = [
            'name'   => $sld,
            'tld'    => $tld,
            'period' => (int) ($options['period'] ?? 1),
        ];

        foreach ($nameservers as $i => $ns) {
            $commandData['nserver' . ($i + 1)] = (string) $ns;
        }

        try {
            $data    = $this->command('domain-create', $commandData);
            $orderId = $data['id'] ?? $data['order_id'] ?? null;

            return ProvisioningResult::ok(
                externalId: (string) ($orderId ?? $fqdn),
                metadata: [
                    'fqdn'          => $fqdn,
                    'test_mode'     => $this->testMode,
                    'registrar'     => 'wedos',
                    'nameservers'   => $nameservers,
                    'registered_at' => now()->toIso8601String(),
                    'expires_at'    => now()->addYear()->toIso8601String(),
                ],
            );
        } catch (ProvisioningException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ProvisioningException::connectionFailed('wedos', $e);
        }
    }

    public function renewDomain(string $fqdn, int $years = 1): ProvisioningResult
    {
        if (! (bool) config('provisioning.wedos.allow_real_writes', false)) {
            return ProvisioningResult::failure(
                'WAPI_ALLOW_REAL_WRITES is not enabled — refusing to renew domain.',
            );
        }

        $fqdn = Str::lower(trim($fqdn));

        try {
            $this->command('domain-renew', ['name' => $fqdn, 'period' => $years]);

            return ProvisioningResult::ok(
                externalId: $fqdn,
                metadata: [
                    'operation'  => 'renew',
                    'fqdn'       => $fqdn,
                    'years'      => $years,
                    'renewed_at' => now()->toIso8601String(),
                    'test_mode'  => $this->testMode,
                ],
            );
        } catch (ProvisioningException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ProvisioningException::connectionFailed('wedos', $e);
        }
    }

    /** @param list<string> $nameservers */
    public function updateNameservers(string $fqdn, array $nameservers): ProvisioningResult
    {
        $fqdn = Str::lower(trim($fqdn));

        $commandData = ['name' => $fqdn];
        foreach ($nameservers as $i => $ns) {
            $commandData['nserver' . ($i + 1)] = (string) $ns;
        }

        try {
            $this->command('domain-ns-update', $commandData);

            return ProvisioningResult::ok(
                externalId: $fqdn,
                metadata: [
                    'operation'   => 'ns_update',
                    'fqdn'        => $fqdn,
                    'nameservers' => $nameservers,
                    'test_mode'   => $this->testMode,
                ],
            );
        } catch (ProvisioningException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ProvisioningException::connectionFailed('wedos', $e);
        }
    }

    /** @return array<string, mixed> */
    public function getDomainInfo(string $fqdn): array
    {
        $fqdn = Str::lower(trim($fqdn));

        try {
            return $this->command('domain-info', ['name' => $fqdn]);
        } catch (\Throwable) {
            return [
                'fqdn'   => $fqdn,
                'status' => 'unknown',
                'error'  => 'wapi_unavailable',
            ];
        }
    }

    // ---------------------------------------------------------------- internals

    /**
     * Execute a single WAPI command and return its data payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ProvisioningException
     */
    private function command(string $command, array $data): array
    {
        $auth = sha1($this->user . sha1($this->password) . gmdate('H'));

        $payload = [
            'user' => $this->user,
            'auth' => $auth,
            'test' => $this->testMode ? 1 : 0,
            'commands' => [
                ['command' => $command, 'data' => $data],
            ],
        ];

        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post($this->url, ['request' => json_encode(['request' => $payload])]);

        if ($response->failed()) {
            throw new ProvisioningException(
                "WEDOS WAPI HTTP {$response->status()} for command {$command}.",
                driver: 'wedos',
                retryable: $response->serverError(),
            );
        }

        $json = $response->json() ?? [];
        $code = (int) ($json['response']['code'] ?? 0);

        if ($code !== 1000) {
            $msg = (string) ($json['response']['result'] ?? 'unknown error');
            throw new ProvisioningException(
                "WEDOS WAPI error {$code} for {$command}: {$msg}",
                driver: 'wedos',
                retryable: false,
            );
        }

        /** @var array<string, mixed> $commandResult */
        $commandResult = $json['response']['data']['commands'][$command] ?? [];
        $cmdCode       = (int) ($commandResult['code'] ?? 0);

        if ($cmdCode !== 1000) {
            $msg = (string) ($commandResult['result'] ?? 'unknown');
            throw new ProvisioningException(
                "WEDOS command {$command} error {$cmdCode}: {$msg}",
                driver: 'wedos',
                retryable: false,
            );
        }

        return (array) ($commandResult['data'] ?? []);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function splitFqdn(string $fqdn): array
    {
        $parts = explode('.', $fqdn, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }

    /** @return list<string> */
    private function defaultNameservers(): array
    {
        $configured = config('provisioning.wedos.mock.nameservers');

        if (is_array($configured)) {
            return array_values(array_filter($configured, 'is_string'));
        }

        return ['ns1.onhost.cz', 'ns2.onhost.cz'];
    }
}
