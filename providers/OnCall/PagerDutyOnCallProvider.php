<?php

declare(strict_types=1);

namespace Onhost\Providers\OnCall;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Providers\Contracts\OnCallProvider;
use Throwable;

/**
 * PagerDuty Events API v2 (audit §5q-1): `enqueue` with the integration's routing key; the platform's dedup key is
 * PagerDuty's `dedup_key`, so acknowledge / resolve address the same incident. An escalation re-triggers with a higher
 * severity — PagerDuty re-pages the on-call for it. The routing key never lands in logs.
 */
final class PagerDutyOnCallProvider implements OnCallProvider
{
    public const URL = 'https://events.pagerduty.com/v2/enqueue';

    public function __construct(private readonly HttpFactory $http, private readonly string $routingKey, private readonly string $source = 'onhost') {}

    public function name(): string
    {
        return 'pagerduty';
    }

    public function trigger(array $alert): ?string
    {
        $severity = match ($alert['severity']) {
            'hot' => 'critical', 'warn' => $alert['escalation'] > 0 ? 'critical' : 'error', default => 'warning',
        };
        $body = [
            'routing_key' => $this->routingKey, 'event_action' => 'trigger', 'dedup_key' => $alert['dedup_key'],
            'payload' => ['summary' => mb_substr(($alert['escalation'] > 0 ? "[eskalace {$alert['escalation']}] " : '').$alert['title'], 0, 1024), 'source' => $this->source, 'severity' => $severity, 'component' => $alert['event'], 'custom_details' => ['body' => $alert['body'], 'surface' => $alert['surface'], 'escalation' => $alert['escalation']]],
        ];

        return $this->send($body) ? $alert['dedup_key'] : null;
    }

    public function acknowledge(string $dedupKey, ?string $providerRef, string $by): bool
    {
        return $this->send(['routing_key' => $this->routingKey, 'event_action' => 'acknowledge', 'dedup_key' => $dedupKey]);
    }

    public function resolve(string $dedupKey, ?string $providerRef, string $by): bool
    {
        return $this->send(['routing_key' => $this->routingKey, 'event_action' => 'resolve', 'dedup_key' => $dedupKey]);
    }

    private function send(array $body): bool
    {
        try {
            return $this->http->timeout(8)->connectTimeout(3)->acceptJson()->post(self::URL, $body)->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
