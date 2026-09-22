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

    /**
     * The change log row a write of ours will become. ISPConfig applies nothing in the response: the server cron reads
     * `sys_datalog` and reports back through it. Until this, the adapter watched the length of the WHOLE server's queue
     * (`monitor_jobqueue_count`) — an empty queue meant "succeeded" even when our job had failed, and somebody else's
     * writes on a busy server kept ours waiting until the timeout. Knowing the row lets `awaitStatus` watch its own.
     *
     * Only the tables named here are followed; a function this map does not know leaves no last write and the adapter
     * falls back to the queue count. A wrong guess can only miss (the row is matched on table AND index), never match
     * somebody else's record.
     *
     * @var array<string,array{0:string,1:string}> function prefix => [datalog table, its primary key column]
     */
    private const DATALOG_TABLES = [
        'sites_web_domain' => ['web_domain', 'domain_id'],
        'sites_web_subdomain' => ['web_domain', 'domain_id'],
        'sites_web_aliasdomain' => ['web_domain', 'domain_id'],
        'sites_database_user' => ['web_database_user', 'database_user_id'],
        'sites_database' => ['web_database', 'database_id'],
        'sites_ftp_user' => ['ftp_user', 'ftp_user_id'],
        'sites_shell_user' => ['shell_user', 'shell_user_id'],
        'sites_cron' => ['cron', 'cron_id'],
        'mail_domain' => ['mail_domain', 'domain_id'],
        'mail_user' => ['mail_user', 'mailuser_id'],
        'mail_forward' => ['mail_forwarding', 'forwarding_id'],
        'mail_alias' => ['mail_forwarding', 'forwarding_id'],
        'client' => ['client', 'client_id'],
    ];

    /** @var array{dbtable:string, dbidx:string, at:int}|null what the last write of this connector will look like in the change log */
    private ?array $lastWrite = null;

    /** @return array{dbtable:string, dbidx:string, at:int}|null */
    public function lastWrite(): ?array
    {
        return $this->lastWrite;
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
        $this->rememberWrite($function, $params, $result['response']);

        return $result['response'];
    }

    /** @param array<string,mixed> $params */
    private function rememberWrite(string $function, array $params, mixed $response): void
    {
        $verb = (string) (explode('_', $function)[count(explode('_', $function)) - 1] ?? '');
        if (! in_array($verb, ['add', 'update', 'delete'], true)) {
            return;
        }
        $prefix = mb_substr($function, 0, -mb_strlen($verb) - 1);
        $table = self::DATALOG_TABLES[$prefix] ?? null;
        if ($table === null) {
            $this->lastWrite = null; // a write we cannot name: the adapter falls back to the queue count

            return;
        }
        // an add answers with the new id; an update and a delete were given it
        $id = $verb === 'add' ? (int) (is_array($response) ? ($response['id'] ?? 0) : $response) : (int) ($params['primary_id'] ?? 0);
        if ($id <= 0) {
            $this->lastWrite = null;

            return;
        }
        $this->lastWrite = ['dbtable' => $table[0], 'dbidx' => $table[1].':'.$id, 'at' => time()];
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
            $this->http->recordSuccess($this->instance->key); // the panel answered; a refused login is ours to fix, not the panel's failure
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
        // these answer with a credential under the neutral key `response` (H12): `login` with the session — whoever reads it holds the
        // remote API for ten minutes — and `client_login_get` with a one-time link straight into the customer's panel
        $secretAnswer = in_array($function, ['login', 'client_login_get'], true);
        $options = TlsOptions::verify($this->instance, 'ispconfig');
        $response = $this->http->send(new ProviderRequest(
            provider: 'ispconfig', instanceKey: $this->instance->key, method: 'POST',
            url: rtrim((string) $this->instance->base_url, '/').'/remote/json.php?'.$function, action: $function,
            headers: ['Content-Type' => 'application/json', 'Accept' => 'application/json'], body: $body, bodyType: 'json',
            timeoutSeconds: 20, critical: $critical, options: $options, operationId: $operationId, secretResponse: $secretAnswer,
            judgedByCaller: true, // ISPConfig reports its own failures in the body of an HTTP 200: call() and mapFault() judge them
        ));
        if ($response->status >= 500) {
            $this->http->recordFailure($this->instance->key);

            throw new ProviderException('ispconfig', ProviderErrorCode::TRANSIENT, "ISPConfig {$function} returned HTTP {$response->status}", (string) $response->status);
        }
        if (in_array($response->status, [401, 403], true)) {
            $this->http->recordSuccess($this->instance->key); // the panel answered: it refuses us, it is not down

            throw new ProviderException('ispconfig', ProviderErrorCode::AUTH, "ISPConfig {$function} rejected (HTTP {$response->status}); check remote user IP allow-list", (string) $response->status);
        }
        $json = $response->json();
        if (! is_array($json) || ! isset($json['code'])) {
            $this->http->recordFailure($this->instance->key); // not an answer of the remote API: an error page in its place

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
        // the panel's own trouble (its database, a timeout) counts against it; a refusal about one record is an answer
        if ($code === ProviderErrorCode::TRANSIENT) {
            $this->http->recordFailure($this->instance->key);
        } else {
            $this->http->recordSuccess($this->instance->key);
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
