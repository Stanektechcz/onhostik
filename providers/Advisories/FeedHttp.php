<?php

declare(strict_types=1);

namespace Onhost\Providers\Advisories;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;

/**
 * One JSON call to a vulnerability feed through the provider HTTP client (breaker, quota bucket, call log). The answer is a large
 * public document of third-party text: it is not kept in `provider_calls` (`secretResponse`), only the call itself is.
 */
final class FeedHttp
{
    public function __construct(private readonly ProviderHttpClient $http) {}

    /**
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>  $headers
     * @param  array<string,mixed>|null  $body
     * @return array<mixed>
     */
    public function json(string $feed, string $method, string $url, string $action, array $query = [], array $headers = [], ?array $body = null, int $maxBytes = 33_554_432, int $timeout = 60): array
    {
        $response = $this->http->send(new ProviderRequest(
            provider: 'advisories', instanceKey: "advisories-{$feed}", method: $method, url: $url, action: $action,
            headers: ['Accept' => 'application/json'] + $headers, body: $body, query: $query, timeoutSeconds: $timeout, connectTimeoutSeconds: 10,
            idempotent: true, maxBodyBytes: $maxBytes, secretResponse: true,
        ));
        if ($response->status < 200 || $response->status >= 300) {
            throw new ProviderException("advisories-{$feed}", match (true) {
                $response->status === 401 || $response->status === 403 => ProviderErrorCode::AUTH, $response->status === 429 => ProviderErrorCode::RATE_LIMIT, $response->status >= 500 => ProviderErrorCode::TRANSIENT, default => ProviderErrorCode::PROVIDER_BUG
            }, "answered HTTP {$response->status}", (string) $response->status, retryAfterSeconds: $response->retryAfterSeconds());
        }
        $json = json_decode($response->rawBody, true);
        if (! is_array($json)) {
            throw new ProviderException("advisories-{$feed}", ProviderErrorCode::PROVIDER_BUG, 'did not answer with JSON');
        }

        return $json;
    }
}
