<?php

declare(strict_types=1);

namespace Onhost\Platform\Observability;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Redaction\Redactor;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Error tracking without the SDK (audit §5q-2): every unexpected exception becomes a Sentry envelope on the store
 * API of `SENTRY_DSN` — message and frames pass the redactor, the correlation id, request id, actor and the current
 * command travel as tags, so the event links to the audit row and the trace. Expected outcomes (domain errors,
 * validation, 4xx, auth) are never reported. Without a DSN nothing leaves the process.
 */
final class ErrorReporter
{
    public function __construct(private readonly HttpFactory $http, private readonly Redactor $redactor) {}

    public function enabled(): bool
    {
        return (string) config('onhost.observability.sentry_dsn', '') !== '';
    }

    public static function expected(Throwable $e): bool
    {
        return $e instanceof DomainError || $e instanceof ValidationException || $e instanceof AuthenticationException || ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500);
    }

    /** Ships the exception; returns the event id, null when disabled, expected or refused. */
    public function report(Throwable $e, array $extra = []): ?string
    {
        if (! $this->enabled() || self::expected($e)) {
            return null;
        }
        $dsn = parse_url((string) config('onhost.observability.sentry_dsn'));
        if (! is_array($dsn) || empty($dsn['host']) || empty($dsn['user']) || empty($dsn['path'])) {
            return null;
        }
        $project = trim((string) $dsn['path'], '/');
        $eventId = str_replace('-', '', (string) Str::uuid());
        $tags = array_filter([
            'correlation_id' => Context::get('correlation_id'), 'request_id' => Context::get('request_id'), 'actor' => Context::get('actor'), 'command' => Context::get('command'), 'operation' => Context::get('operation'),
        ], fn ($v) => is_string($v) && $v !== '');
        $frames = [];
        foreach (array_reverse(array_slice($e->getTrace(), 0, 30)) as $frame) {
            $frames[] = ['filename' => (string) ($frame['file'] ?? ''), 'function' => (string) ($frame['function'] ?? ''), 'lineno' => (int) ($frame['line'] ?? 0), 'in_app' => ! str_contains((string) ($frame['file'] ?? ''), '/vendor/')];
        }
        $event = [
            'event_id' => $eventId, 'timestamp' => now()->toIso8601String(), 'platform' => 'php', 'level' => 'error', 'logger' => 'onhost',
            'environment' => (string) config('onhost.observability.environment', 'production'), 'release' => (string) config('onhost.version', '4.0'), 'server_name' => (string) gethostname(),
            'tags' => $tags + ['service' => (string) config('onhost.observability.service_name', 'onhost-control-plane')],
            'extra' => $this->redactor->redact($extra),
            'exception' => ['values' => [['type' => get_class($e), 'value' => mb_substr($this->redactor->redactString($e->getMessage()), 0, 1000), 'stacktrace' => ['frames' => $frames]]]],
        ];
        $header = json_encode(['event_id' => $eventId, 'sent_at' => now()->toIso8601String(), 'dsn' => config('onhost.observability.sentry_dsn')]);
        $item = json_encode(['type' => 'event', 'content_type' => 'application/json']);
        $body = $header."\n".$item."\n".json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $scheme = (string) ($dsn['scheme'] ?? 'https');
        $port = isset($dsn['port']) ? ':'.$dsn['port'] : '';
        try {
            $ok = $this->http->withHeaders([
                'Content-Type' => 'application/x-sentry-envelope', 'X-Sentry-Auth' => 'Sentry sentry_version=7, sentry_client=onhost/1.0, sentry_key='.$dsn['user'],
            ])->timeout(3)->connectTimeout(2)->withBody($body, 'application/x-sentry-envelope')->post("{$scheme}://{$dsn['host']}{$port}/api/{$project}/envelope/")->successful();
        } catch (Throwable) {
            $ok = false;
        }

        return $ok ? $eventId : null;
    }
}
