<?php

declare(strict_types=1);

namespace Onhost\Platform\ProviderHttp;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Resilience\CircuitBreaker;
use Onhost\Platform\Resilience\TokenBucket;
use Throwable;

/**
 * Transport layer shared by every adapter: circuit breaker, quota buckets, timeouts,
 * TLS pinning, call logging with redaction. Business-level failures (HTTP 200 with an
 * error in the body — WEDOS, aaPanel, ISPConfig) are decided by the adapter, which
 * reports them back with `recordFailure()` so the breaker sees them too.
 */
final class ProviderHttpClient
{
    /** Quotas below this many calls per window carry no diagnostic slice (H323). */
    public const DIAGNOSTIC_MIN_LIMIT = 20;

    /** @var array<string, TokenBucket> */
    private array $buckets = [];

    /** @var array<string, array{limit:int, window:int, reserve:float}> */
    private array $bucketConfig = [];

    private bool $diagnostic = false;

    private int $localRefusals = 0;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly ProviderCallLogger $logger,
        private readonly int $breakerThreshold = 5,
        private readonly int $breakerCooldown = 60,
        private readonly float $diagnosticReserve = 0.05,
    ) {}

    /**
     * Run health reads in the diagnostic lane (H323): every call sent inside may use the slice of the quota that ordinary
     * and critical work cannot touch. The worker is single-threaded, so a scoped flag is enough; it nests and always resets.
     *
     * @template T
     *
     * @param  callable(): T  $reads
     * @return T
     */
    public function diagnostic(callable $reads): mixed
    {
        $before = $this->diagnostic;
        $this->diagnostic = true;
        try {
            return $reads();
        } finally {
            $this->diagnostic = $before;
        }
    }

    public function inDiagnostic(): bool
    {
        return $this->diagnostic;
    }

    /** Calls this process refused on its own quota, before anything reached a vendor: a refusal is not an outage. */
    public function localRefusals(): int
    {
        return $this->localRefusals;
    }

    public function configureBucket(string $name, int $limit, int $windowSeconds, float $reserve = 0.0): void
    {
        $this->bucketConfig[$name] = ['limit' => $limit, 'window' => $windowSeconds, 'reserve' => $reserve];
        unset($this->buckets[$name]);
    }

    public function bucket(string $name): ?TokenBucket
    {
        if (! isset($this->bucketConfig[$name])) {
            return null;
        }
        $cfg = $this->bucketConfig[$name];

        // a probe is a handful of reads, so never fewer than three; a quota too small to spare them keeps all of it for work
        $slice = $this->diagnosticReserve <= 0 || $cfg['limit'] < self::DIAGNOSTIC_MIN_LIMIT ? 0 : max(3, (int) ceil($cfg['limit'] * $this->diagnosticReserve));

        return $this->buckets[$name] ??= new TokenBucket($this->cache, $name, $cfg['limit'], $cfg['window'], $cfg['reserve'], $slice);
    }

    public function breaker(string $instanceKey): CircuitBreaker
    {
        return new CircuitBreaker($this->cache, $instanceKey, $this->breakerThreshold, $this->breakerCooldown);
    }

    /** Adapters call this for body-level provider failures (5xx-equivalent). */
    public function recordFailure(string $instanceKey): void
    {
        $this->breaker($instanceKey)->recordFailure();
    }

    public function recordSuccess(string $instanceKey): void
    {
        $this->breaker($instanceKey)->recordSuccess();
    }

    public function send(ProviderRequest $request): ProviderResponse
    {
        $breaker = $this->breaker($request->instanceKey);
        if (! $breaker->allowsRequest()) {
            $this->logger->log($request, null, 'circuit_open', false, 0, $this->summarize($request), null, 'circuit open');
            throw new ProviderException($request->provider, ProviderErrorCode::CIRCUIT_OPEN, "Circuit open for {$request->instanceKey}", retryAfterSeconds: $this->breakerCooldown);
        }

        $bucketName = $request->bucket ?? $request->instanceKey;
        $bucket = $this->bucket($bucketName);
        if ($bucket !== null && ! $bucket->tryConsume($request->critical, 1, $this->diagnostic)) {
            $this->localRefusals++;
            $this->logger->log($request, null, 'quota_exhausted', false, 0, $this->summarize($request), null, 'local quota exhausted');
            throw new ProviderException($request->provider, ProviderErrorCode::RATE_LIMIT, "Local quota for {$bucketName} exhausted", retryAfterSeconds: $bucket->secondsUntilReset());
        }

        $pending = $this->prepare($request);
        $started = hrtime(true);

        // Guzzle's `query` option replaces the URL query string, so it is only passed when non-empty
        // (aaPanel/ISPConfig address the function in the query string).
        $query = $request->query !== [] ? ['query' => $request->query] : [];
        try {
            $response = match ($request->bodyType) {
                'form' => $pending->asForm()->send($request->method, $request->url, array_merge(['form_params' => is_array($request->body) ? $request->body : []], $query)),
                'raw' => $pending->withBody((string) $request->body, $request->headers['Content-Type'] ?? 'text/plain')->send($request->method, $request->url, $query),
                'multipart' => $pending->send($request->method, $request->url, array_merge(['multipart' => $this->multipart($request)], $query)), // file uploads (aaPanel file manager)
                default => $pending->send($request->method, $request->url, array_merge(is_array($request->body) ? ['json' => $request->body] : [], $query)),
            };
        } catch (ConnectionException $e) {
            $duration = (int) ((hrtime(true) - $started) / 1_000_000);
            $breaker->recordFailure();
            $this->logger->log($request, null, 'connection_error', false, $duration, $this->summarize($request), null, $e->getMessage());
            throw new ProviderException($request->provider, ProviderErrorCode::TRANSIENT, 'Connection failed: '.$e->getMessage(), previous: $e);
        } catch (Throwable $e) {
            $duration = (int) ((hrtime(true) - $started) / 1_000_000);
            $breaker->recordFailure();
            $this->logger->log($request, null, 'transport_error', false, $duration, $this->summarize($request), null, $e->getMessage());
            throw new ProviderException($request->provider, ProviderErrorCode::UNKNOWN, 'Transport error: '.$e->getMessage(), previous: $e);
        }

        // the body is read in chunks against a ceiling (H318): a panel that answers with gigabytes — a broken export,
        // a proxy error page in a loop — ends in one controlled error instead of an exhausted worker
        $limit = $request->maxBodyBytes ?? max(65536, (int) config('onhost.provisioning.provider_max_body_bytes', 8388608));
        $body = $this->readBody($response, $limit);
        $duration = (int) ((hrtime(true) - $started) / 1_000_000);
        if ($body === null) {
            $breaker->recordFailure();
            $this->logger->log($request, $response->status(), 'response_too_large', false, $duration, $this->summarize($request), null, "response body exceeds {$limit} bytes");
            throw new ProviderException($request->provider, ProviderErrorCode::PROVIDER_BUG, "Response of {$request->action} exceeds {$limit} bytes; refused before it could exhaust the worker");
        }
        $status = $response->status();
        $transportOk = $status < 500 && $status !== 429;
        $json = json_decode($body, true);
        $bodyCode = $this->bodyCode(is_array($json) ? $json : null);
        $callId = $this->logger->log($request, $status, $bodyCode, $transportOk, $duration, $this->summarize($request), is_array($json) ? $json : mb_substr($body, 0, 2000));

        if ($status >= 500) {
            $breaker->recordFailure();
        } elseif ($status !== 429) {
            $breaker->recordSuccess();
        }

        return new ProviderResponse($status, $body, $response->headers(), $duration, $callId);
    }

    /** The body up to `$limit` bytes, or null when the provider sent more than that. */
    private function readBody(Response $response, int $limit): ?string
    {
        $stream = $response->toPsrResponse()->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $body = '';
        while (! $stream->eof()) {
            $chunk = $stream->read(65536);
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
            if (strlen($body) > $limit) {
                $stream->close();

                return null;
            }
        }

        return $body;
    }

    /**
     * Guzzle multipart parts: the plain fields from `body`, then the file parts (`files`); file contents may be a string or a stream.
     *
     * @return list<array{name:string, contents:mixed, filename?:string}>
     */
    private function multipart(ProviderRequest $request): array
    {
        $parts = [];
        foreach (is_array($request->body) ? $request->body : [] as $name => $value) {
            $parts[] = ['name' => (string) $name, 'contents' => is_array($value) ? json_encode($value) : (string) $value];
        }
        foreach ($request->files as $name => $file) {
            $parts[] = ['name' => (string) $name, 'contents' => $file['contents'], 'filename' => (string) ($file['filename'] ?? 'blob')];
        }

        return $parts;
    }

    private function prepare(ProviderRequest $request): PendingRequest
    {
        $pending = $this->http
            ->timeout($request->timeoutSeconds)
            ->connectTimeout($request->connectTimeoutSeconds)
            ->withHeaders(array_merge(['User-Agent' => 'ONhost-ControlPlane/1.0'], $request->headers))
            // streamed so the size ceiling applies before the body sits in memory; the three transport rules come last, so no
            // adapter's own options (verify, cert, proxy) can switch them off — a redirect is never followed (H312)
            ->withOptions(array_merge($request->options, ['http_errors' => false, 'allow_redirects' => false, 'stream' => true]));
        if ($request->bodyType === 'json') {
            $pending = $pending->acceptJson();
        }

        return $pending;
    }

    /** @return array<string,mixed> */
    private function summarize(ProviderRequest $request): array
    {
        return [
            'method' => $request->method,
            'url' => $request->url,
            'query' => $request->query,
            'body' => is_string($request->body) ? mb_substr($request->body, 0, 2000) : $request->body,
            'headers' => array_keys($request->headers),
        ];
    }

    /** @param array<string,mixed>|null $json */
    private function bodyCode(?array $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }
        foreach (['response.code', 'code', 'status', 'errors.0.code', 'error'] as $path) {
            $value = data_get($json, $path);
            if (is_scalar($value)) {
                return (string) (is_bool($value) ? var_export($value, true) : $value);
            }
        }

        return null;
    }
}
