<?php

declare(strict_types=1);

namespace Onhost\Providers\OnCall;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Providers\Contracts\OnCallProvider;
use Throwable;

/**
 * Any pager with an HTTP intake (audit §5q-1): a signed JSON envelope `{action: trigger|acknowledge|resolve, dedup_key,
 * alert}` with `X-ONhost-Signature: v1=<hmac-sha256(timestamp.body)>` — the same envelope the customer webhooks use, so
 * a small relay to SMS, Signal or a phone bridge can verify it.
 */
final class WebhookOnCallProvider implements OnCallProvider
{
    public function __construct(private readonly HttpFactory $http, private readonly string $url, private readonly string $secret) {}

    public function name(): string
    {
        return 'webhook';
    }

    public function trigger(array $alert): ?string
    {
        return $this->send(['action' => 'trigger', 'dedup_key' => $alert['dedup_key'], 'alert' => $alert]) ? $alert['dedup_key'] : null;
    }

    public function acknowledge(string $dedupKey, ?string $providerRef, string $by): bool
    {
        return $this->send(['action' => 'acknowledge', 'dedup_key' => $dedupKey, 'by' => $by]);
    }

    public function resolve(string $dedupKey, ?string $providerRef, string $by): bool
    {
        return $this->send(['action' => 'resolve', 'dedup_key' => $dedupKey, 'by' => $by]);
    }

    private function send(array $payload): bool
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $this->secret);
        try {
            return $this->http->timeout(8)->connectTimeout(3)->withHeaders(['Content-Type' => 'application/json', 'User-Agent' => 'ONhost-OnCall/1.0', 'X-ONhost-Timestamp' => $timestamp, 'X-ONhost-Signature' => "v1={$signature}"])->withBody($body, 'application/json')->post($this->url)->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
