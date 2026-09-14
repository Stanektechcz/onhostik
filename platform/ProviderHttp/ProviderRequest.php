<?php

declare(strict_types=1);

namespace Onhost\Platform\ProviderHttp;

/**
 * One outbound call to a vendor API. `action` is the human label logged to
 * provider_calls (e.g. `domain-create`, `qemu.clone`); `critical` lets the call
 * use the reserved share of the quota bucket (renewals, suspend, reconcile).
 */
final class ProviderRequest
{
    /**
     * @param  array<string,mixed>  $headers
     * @param  array<string,mixed>|string|null  $body
     * @param  array<string,mixed>  $query
     * @param  array<string,mixed>  $options  extra Guzzle options (verify, cert, proxy)
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $instanceKey,
        public readonly string $method,
        public readonly string $url,
        public readonly string $action,
        public readonly array $headers = [],
        public readonly array|string|null $body = null,
        public readonly string $bodyType = 'json', // json | form | raw
        public readonly array $query = [],
        public readonly int $timeoutSeconds = 10,
        public readonly int $connectTimeoutSeconds = 5,
        public readonly bool $critical = false,
        public readonly bool $idempotent = false,
        public readonly array $options = [],
        public readonly ?string $operationId = null,
        public readonly ?string $bucket = null, // token bucket name; null = provider default
        /** @var array<string, array{contents:string|resource, filename:string}> multipart file parts (bodyType `multipart`; `body` carries the plain fields) */
        public readonly array $files = [],
    ) {}
}
