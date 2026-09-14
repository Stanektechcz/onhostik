<?php

declare(strict_types=1);

namespace Onhost\Providers\IspConfig;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Providers\Contracts\TlsOptions;

/**
 * ISPConfig 3.2/3.3 Remote API (remote/json.php?<function>). HTTP is always 200;
 * failure is `code: "remote_fault"` in the body. Sessions are cached per instance
 * (TTL 10 min) so a busy day does not create hundreds of remote logins. Writes are
 * not applied by the response but by the server cron (sys_datalog / jobqueue), so
 * every write is awaited through `monitor_jobqueue_count` (docs-provider-apis §5).
 */
final class IspConfigConnector
{
    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $this->http->configureBucket($instance->key, (int) ($instance->rate_limits['per_minute'] ?? 300), 60, 0.1);
    }

    public function instance(): ProviderInstance
    {
        return $this->instance;
    }

    /** @param array<string,mixed> $params ordered function arguments (without session_id) */
    public function call(string $function, array $params = [], bool $critical = false, ?string $operationId = null): mixed
    {
        $session = $this->session();
        $result = $this->raw($function, array_merge(['session_id' => $session], $params), $critical, $operationId);
        if ($result['code'] === 'remote_fault' && $this->isSessionError((string) $result['message'])) {
            $this->cache->forget($this->sessionKey());
            $session = $this->session();
            $result = $this->raw($function, array_merge(['session_id' => $session], $params), $critical, $operationId);
        }
        if ($result['code'] !== 'ok') {
            throw $this->mapFault($function, (string) $result['message']);
        }
        $this->http->recordSuccess($this->instance->key);

        return $result['response'];
    }

    public function logout(): void
    {
        $session = $this->cache->get($this->sessionKey());
        if (is_string($session)) {
            try {
                $this->raw('logout', ['session_id' => $session]);
            } catch (ProviderException) {
                // best effort
            }
            $this->cache->forget($this->sessionKey());
        }
    }

    private function session(): string
    {
        $cached = $this->cache->get($this->sessionKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $lock = $this->cache->lock('onhost:ispconfig:login:'.$this->instance->id, 15);
        try {
            $lock->block(10);
            $cached = $this->cache->get($this->sessionKey());
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
            $result = $this->raw('login', [
                'username' => (string) ($this->credentials['remote_user'] ?? ''),
                'password' => (string) ($this->credentials['remote_password'] ?? ''),
                'client_login' => false,
            ], true);
            if ($result['code'] !== 'ok' || ! is_string($result['response']) || $result['response'] === '') {
                throw new ProviderException('ispconfig', ProviderErrorCode::AUTH, 'ISPConfig remote login failed: '.$result['message'], 'remote_fault');
            }
            $this->cache->put($this->sessionKey(), $result['response'], 600);

            return $result['response'];
        } finally {
            optional($lock)->release();
        }
    }

    /** @return array{code:string, message:string, response:mixed} */
    private function raw(string $function, array $body, bool $critical = false, ?string $operationId = null): array
    {
        $options = TlsOptions::verify($this->instance, 'ispconfig');
        $response = $this->http->send(new ProviderRequest(
            provider: 'ispconfig', instanceKey: $this->instance->key, method: 'POST',
            url: rtrim((string) $this->instance->base_url, '/').'/remote/json.php?'.$function, action: $function,
            headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json'], body: $body, bodyType: 'json',
            timeoutSeconds: 20, critical: $critical, options: $options, operationId: $operationId,
        ));
        if ($response->status >= 500) {
            throw new ProviderException('ispconfig', ProviderErrorCode::TRANSIENT, "ISPConfig {$function} returned HTTP {$response->status}", (string) $response->status);
        }
        if (in_array($response->status, [401, 403], true)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::AUTH, "ISPConfig {$function} rejected (HTTP {$response->status}); check remote user IP allow-list", (string) $response->status);
        }
        $json = $response->json();
        if (! is_array($json) || ! isset($json['code'])) {
            throw new ProviderException('ispconfig', ProviderErrorCode::PROVIDER_BUG, "ISPConfig {$function} returned a non-JSON body (HTTP {$response->status})");
        }

        return ['code' => (string) $json['code'], 'message' => (string) ($json['message'] ?? ''), 'response' => $json['response'] ?? null];
    }

    private function mapFault(string $function, string $message): ProviderException
    {
        $lower = strtolower($message);
        $code = match (true) {
            str_contains($lower, 'session') && (str_contains($lower, 'invalid') || str_contains($lower, 'expired')) => ProviderErrorCode::AUTH,
            str_contains($lower, 'permission') || str_contains($lower, 'not allowed') || str_contains($lower, 'denied') => ProviderErrorCode::AUTH,
            str_contains($lower, 'already exist') || str_contains($lower, 'duplicate') => ProviderErrorCode::CONFLICT,
            str_contains($lower, 'not found') || str_contains($lower, 'no record') => ProviderErrorCode::NOT_FOUND,
            str_contains($lower, 'database') || str_contains($lower, 'mysql') || str_contains($lower, 'timeout') => ProviderErrorCode::TRANSIENT,
            default => ProviderErrorCode::VALIDATION,
        };
        if ($code === ProviderErrorCode::TRANSIENT) {
            $this->http->recordFailure($this->instance->key);
        }

        return new ProviderException('ispconfig', $code, "ISPConfig {$function}: {$message}", 'remote_fault');
    }

    private function isSessionError(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'session') && (str_contains($lower, 'invalid') || str_contains($lower, 'expired') || str_contains($lower, 'not logged'));
    }

    private function sessionKey(): string
    {
        return 'onhost:ispconfig:session:'.$this->instance->id;
    }
}
