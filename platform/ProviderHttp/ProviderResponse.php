<?php

declare(strict_types=1);

namespace Onhost\Platform\ProviderHttp;

final class ProviderResponse
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public readonly int $status,
        public readonly string $rawBody,
        public readonly array $headers,
        public readonly int $durationMs,
        public readonly string $callId,
    ) {}

    /** Decoded JSON body or null when the body is not JSON. */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = json_decode($this->rawBody, true);
        if (! is_array($decoded)) {
            return $key === null ? null : $default;
        }
        if ($key === null) {
            return $decoded;
        }

        return data_get($decoded, $key, $default);
    }

    public function isJson(): bool
    {
        return is_array(json_decode($this->rawBody, true));
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    public function retryAfterSeconds(): ?int
    {
        $value = $this->header('Retry-After');
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $ts = strtotime($value);

        return $ts === false ? null : max(0, $ts - time());
    }

    public function serverDate(): ?int
    {
        $value = $this->header('Date');
        $ts = $value === null ? false : strtotime($value);

        return $ts === false ? null : $ts;
    }
}
