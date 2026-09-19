<?php

declare(strict_types=1);

namespace Onhost\Providers\Wedos;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Clock\Clock;
use Onhost\Platform\Clock\SystemClock;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\Resilience\CircuitBreaker;

/**
 * The only path to WEDOS WAPI (blueprint §45.5). Responsibilities:
 *  - schema validation (command allow-list + required fields) before anything is sent,
 *  - token buckets: all requests 1000/h and the domain family 100/h, each with a 15 % reserve
 *    for renewals/reconcile (`critical` calls may use the reserve),
 *  - hourly SHA-1 auth in Europe/Prague, regenerated for every attempt (never cached across the hour),
 *  - clock health gate: |offset| > 1 s or two consecutive auth errors opens the circuit (S33),
 *  - invalid-request circuit breaker (>10 invalid requests in a window) to avoid the WAPI penalty,
 *  - clTRID correlation on every command, vendor error normalisation, redaction (auth, AUTH-ID).
 * Nothing here retries `domain-create` blindly: a timeout after create is resolved by the caller with `domain-info` (S34).
 */
final class WapiGateway
{
    public const DOMAIN_FAMILY = ['domain-check', 'domain-create', 'domain-transfer', 'domain-transfer-check', 'domain-tld-period-check'];

    /** @var array<string, list<string>> command => required data fields */
    private const SCHEMA = [
        'ping' => [], 'domain-check' => ['name'], 'domain-info' => ['name'], 'domains-list' => [], 'domain-create' => ['name', 'period', 'owner_c', 'admin_c'],
        'domain-renew' => ['name', 'period'], 'domain-update-ns' => ['name'], 'domain-transfer-check' => ['name'], 'domain-transfer' => ['name', 'auth_info'],
        'domain-send-auth-info' => ['name'], 'domain-update-keyset' => ['name'], 'domain-tld-period-check' => ['tld', 'period'],
        'contact-check' => ['tld', 'cname'], 'contact-info' => ['tld', 'cname'], 'contact-create' => ['tld'], 'contact-update' => ['tld', 'cname'], 'contact-transfer' => ['tld', 'cname', 'auth_info'], 'contact-send-auth-info' => ['tld', 'cname'],
        'nsset-check' => ['nsset'], 'nsset-info' => ['nsset'], 'nsset-create' => ['nsset', 'dns'], 'nsset-update' => ['nsset'], 'nsset-transfer' => ['nsset', 'auth_info'], 'nsset-send-auth-info' => ['nsset'],
        'dns-domains-list' => [], 'dns-domain-info' => ['name'], 'dns-domain-add' => ['name'], 'dns-domain-update' => ['name'], 'dns-domain-delete' => ['name'], 'dns-domain-axfr-run' => ['name'], 'dns-domain-axfr-tsig' => ['name'],
        'dns-domain-copy' => ['name', 'name_from'], 'dns-domain-commit' => ['name'], 'dns-rows-list' => ['domain'], 'dns-row-detail' => ['domain', 'row_id'], 'dns-row-add' => ['domain', 'name', 'ttl', 'type', 'rdata'], 'dns-row-update' => ['domain', 'row_id'], 'dns-row-delete' => ['domain', 'row_id'],
        'credit-info' => [], 'account-list' => [], 'poll-req' => [], 'poll-ack' => ['id'],
    ];

    private int $consecutiveAuthErrors = 0;

    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
        private readonly CacheRepository $cache,
        private readonly Clock $clock,
    ) {
        $limits = (array) config('onhost.wapi.limits');
        $this->http->configureBucket('wapi:all', (int) ($limits['all_per_hour'] ?? 1000), 3600, (float) ($limits['reserve'] ?? 0.15));
        $this->http->configureBucket('wapi:domain', (int) ($limits['domain_family_per_hour'] ?? 100), 3600, (float) ($limits['reserve'] ?? 0.15));
    }

    public function instance(): ProviderInstance
    {
        return $this->instance;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{code:int, result:string, data:array<string,mixed>, clTRID:string, svTRID:?string, raw:array<string,mixed>}
     */
    public function command(string $command, array $data = [], ?string $clTrid = null, bool $critical = false, ?bool $testMode = null, ?string $operationId = null): array
    {
        $this->validate($command, $data);
        $this->assertClockHealthy();
        $breaker = $this->invalidRequestBreaker();
        if (! $breaker->allowsRequest()) {
            throw new ProviderException('wedos', ProviderErrorCode::CIRCUIT_OPEN, 'WAPI circuit open after repeated invalid requests or auth failures', retryAfterSeconds: 300);
        }
        $clTrid ??= self::clTrid($command, $operationId);
        $payload = ['request' => array_filter([
            'user' => (string) ($this->credentials['login'] ?? ''),
            'auth' => $this->auth(),
            'command' => $command,
            'clTRID' => $clTrid,
            'test' => ($testMode ?? (bool) config('onhost.wapi.test_mode', true)) ? 1 : null,
            'data' => $data === [] ? null : $data,
        ], fn ($v) => $v !== null)];

        $response = $this->http->send(new ProviderRequest(
            provider: 'wedos', instanceKey: $this->instance->key, method: 'POST', url: (string) config('onhost.wapi.endpoint'), action: $command,
            headers: ['Accept' => 'application/json'], body: ['request' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], bodyType: 'form',
            timeoutSeconds: 30, critical: $critical, operationId: $operationId, bucket: in_array($command, self::DOMAIN_FAMILY, true) ? 'wapi:domain' : 'wapi:all',
            options: array_filter(['force_ip_resolve' => config('onhost.wapi.force_ip_resolve')]), // WAPI answers IPv6 clients with a redirect to a 404 page (observed 2026-09-07): stay on the allow-listed IPv4 egress
        ));
        $this->http->bucket('wapi:all')?->tryConsume($critical, 1, $this->http->inDiagnostic()); // the domain family counts against the global limit too
        if ($response->status >= 500) {
            throw new ProviderException('wedos', ProviderErrorCode::TRANSIENT, "WAPI HTTP {$response->status}", (string) $response->status);
        }
        $serverDate = $response->serverDate();
        if ($serverDate !== null) {
            SystemClock::recordOffset((float) (time() - $serverDate));
        }
        $body = $response->json('response');
        if (! is_array($body) || ! isset($body['code'])) {
            throw new ProviderException('wedos', ProviderErrorCode::PROVIDER_BUG, "WAPI {$command} returned no response envelope (HTTP {$response->status})");
        }
        $code = (int) $body['code'];
        $result = ['code' => $code, 'result' => (string) ($body['result'] ?? ''), 'data' => (array) ($body['data'] ?? []), 'clTRID' => (string) ($body['clTRID'] ?? $clTrid), 'svTRID' => isset($body['svTRID']) ? (string) $body['svTRID'] : null, 'raw' => $body];

        if (WedosErrorMap::isSuccess($code)) {
            $this->consecutiveAuthErrors = 0;
            $breaker->recordSuccess();
            $this->http->recordSuccess($this->instance->key);

            return $result;
        }
        $mapped = WedosErrorMap::map($code);
        if ($mapped['code'] === ProviderErrorCode::AUTH) {
            $this->consecutiveAuthErrors++;
            if ($this->consecutiveAuthErrors >= 2) {
                $breaker->trip();
            }
        } elseif ($mapped['code'] === ProviderErrorCode::VALIDATION) {
            $breaker->recordFailure(); // >10 invalid requests would trigger the WEDOS penalty; we stop earlier
        } elseif ($mapped['code'] === ProviderErrorCode::TRANSIENT) {
            $this->http->recordFailure($this->instance->key);
        }
        throw new ProviderException('wedos', $mapped['code'], "WAPI {$command} failed: [{$code}] {$result['result']}", (string) $code, ['normalized' => $mapped['normalized'], 'clTRID' => $clTrid, 'svTRID' => $result['svTRID'], 'data' => $result['data']], $mapped['retry_after']);
    }

    /** `onhost:v4:<command>:<op id or ulid>` — the correlation/idempotency anchor (§45.6). */
    public static function clTrid(string $command, ?string $operationId = null): string
    {
        return 'onhost:v4:'.$command.':'.($operationId ?? strtolower((string) Str::ulid()));
    }

    /** sha1(login . sha1(password) . H) where H is the current hour in Europe/Prague (§45.2). */
    public function auth(?int $timestamp = null): string
    {
        $login = (string) ($this->credentials['login'] ?? '');
        $password = (string) ($this->credentials['wapi_password'] ?? '');
        if ($login === '' || $password === '') {
            throw new ProviderException('wedos', ProviderErrorCode::AUTH, 'WAPI credentials are not configured');
        }
        $hour = (new \DateTimeImmutable('@'.($timestamp ?? $this->clock->now()->getTimestamp())))->setTimezone(new \DateTimeZone((string) config('onhost.wapi.timezone', 'Europe/Prague')))->format('H');

        return sha1($login.sha1($password).$hour);
    }

    /** @return array{used_all:int, remaining_all:int, used_domain:int, remaining_domain:int, reset_in:int} */
    public function quota(): array
    {
        $all = $this->http->bucket('wapi:all');
        $domain = $this->http->bucket('wapi:domain');

        return ['used_all' => $all?->used() ?? 0, 'remaining_all' => $all?->remaining() ?? 0, 'used_domain' => $domain?->used() ?? 0, 'remaining_domain' => $domain?->remaining() ?? 0, 'reset_in' => $all?->secondsUntilReset() ?? 0];
    }

    private function validate(string $command, array $data): void
    {
        if (! array_key_exists($command, self::SCHEMA)) {
            throw new ProviderException('wedos', ProviderErrorCode::VALIDATION, "WAPI command {$command} is not allow-listed");
        }
        $row = str_starts_with($command, 'dns-row-'); // a zone row: `name` is the label relative to the zone (empty for the apex), not a domain name
        foreach (self::SCHEMA[$command] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || ($data[$field] === '' && ! ($row && $field === 'name'))) {
                throw new ProviderException('wedos', ProviderErrorCode::VALIDATION, "WAPI {$command} requires field {$field}");
            }
        }
        if ($row) {
            if (isset($data['name']) && is_string($data['name']) && $data['name'] !== '' && ! preg_match('/^(\*|[a-z0-9_-]+)(\.[a-z0-9_-]+)*$/i', $data['name'])) {
                throw new ProviderException('wedos', ProviderErrorCode::VALIDATION, "WAPI {$command}: invalid record name {$data['name']}");
            }
        } elseif (isset($data['name']) && is_string($data['name']) && ! preg_match('/^(xn--)?[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $data['name'])) {
            throw new ProviderException('wedos', ProviderErrorCode::VALIDATION, "WAPI {$command}: invalid domain name {$data['name']}");
        }
    }

    private function assertClockHealthy(): void
    {
        $offset = $this->clock->offsetSeconds();
        $max = (float) config('onhost.wapi.clock_max_offset_seconds', 5.0);
        if ($offset !== null && abs($offset) > $max) {
            $this->invalidRequestBreaker()->trip();
            throw new ProviderException('wedos', ProviderErrorCode::CIRCUIT_OPEN, sprintf('Worker clock offset %.2fs exceeds %.2fs; WAPI hourly auth would fail (S33)', $offset, $max), retryAfterSeconds: 300);
        }
    }

    private function invalidRequestBreaker(): CircuitBreaker
    {
        return new CircuitBreaker($this->cache, 'wapi:invalid', 10, 900, 3600);
    }
}
