<?php

declare(strict_types=1);

namespace Onhost\Providers\OnCall;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Providers\Contracts\OnCallProvider;
use Throwable;

/**
 * Opsgenie Alert API (audit §5q-1): `POST /v2/alerts` with the platform's dedup key as the alias, acknowledge / close
 * by alias. Priority follows the severity (P1 hot, P2 warn, P3 info); an escalation re-creates the alert with a higher
 * priority and the escalation number in the message, so the on-call is paged again.
 */
final class OpsgenieOnCallProvider implements OnCallProvider
{
    public function __construct(private readonly HttpFactory $http, private readonly string $apiKey, private readonly string $baseUrl = 'https://api.opsgenie.com') {}

    public function name(): string
    {
        return 'opsgenie';
    }

    public function trigger(array $alert): ?string
    {
        $priority = match ($alert['severity']) {
            'hot' => 'P1', 'warn' => $alert['escalation'] > 0 ? 'P1' : 'P2', default => 'P3',
        };
        $body = [
            'message' => mb_substr(($alert['escalation'] > 0 ? "[eskalace {$alert['escalation']}] " : '').$alert['title'], 0, 130), 'alias' => $alert['dedup_key'], 'description' => (string) ($alert['body'] ?? ''),
            'priority' => $priority, 'source' => 'onhost', 'tags' => ['onhost', $alert['event']], 'details' => ['surface' => (string) ($alert['surface'] ?? ''), 'escalation' => (string) $alert['escalation']],
        ];
        try {
            $response = $this->client()->post($this->baseUrl.'/v2/alerts', $body);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? $alert['dedup_key'] : null;
    }

    public function acknowledge(string $dedupKey, ?string $providerRef, string $by): bool
    {
        return $this->action($dedupKey, 'acknowledge', $by);
    }

    public function resolve(string $dedupKey, ?string $providerRef, string $by): bool
    {
        return $this->action($dedupKey, 'close', $by);
    }

    private function action(string $alias, string $action, string $by): bool
    {
        try {
            return $this->client()->post($this->baseUrl.'/v2/alerts/'.rawurlencode($alias).'/'.$action.'?identifierType=alias', ['user' => mb_substr($by, 0, 100), 'source' => 'onhost'])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function client()
    {
        return $this->http->timeout(8)->connectTimeout(3)->acceptJson()->withHeaders(['Authorization' => 'GenieKey '.$this->apiKey]);
    }
}
