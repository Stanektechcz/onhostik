<?php

declare(strict_types=1);

namespace Onhost\Platform\Observability;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Context;
use Onhost\Platform\Redaction\Redactor;
use Throwable;

/**
 * OpenTelemetry traces without the SDK (audit §5q-2): spans for every command, every operation step and every provider
 * call, exported as OTLP/HTTP JSON to `{OTEL_EXPORTER_OTLP_ENDPOINT}/v1/traces`. The trace id is derived from the
 * correlation id, so one customer action — request → command → outbox → operation → provider calls across web and
 * worker processes — is one trace in Tempo / Jaeger / Grafana. Spans are buffered per process and flushed when the
 * buffer fills or the process ends; without an endpoint nothing is collected. Attributes pass the redactor.
 */
final class Tracer
{
    public const FLUSH_AT = 50;

    /** @var list<array<string,mixed>> */
    private array $spans = [];

    private bool $shutdownRegistered = false;

    public function __construct(private readonly HttpFactory $http, private readonly Redactor $redactor) {}

    public function enabled(): bool
    {
        return (string) config('onhost.observability.otlp_endpoint', '') !== '';
    }

    /**
     * Run `$fn` inside a span named `$name`; the exception (if any) marks the span and is rethrown.
     *
     * @template T
     *
     * @param  callable():T  $fn
     * @param  array<string,mixed>  $attributes
     * @return T
     */
    public function span(string $name, array $attributes, callable $fn): mixed
    {
        if (! $this->enabled()) {
            return $fn();
        }
        $start = hrtime(true);
        $spanId = bin2hex(random_bytes(8));
        $parent = Context::get('otel_parent_span');
        Context::add('otel_parent_span', $spanId);
        $error = null;
        try {
            return $fn();
        } catch (Throwable $e) {
            $error = $e;
            throw $e;
        } finally {
            Context::add('otel_parent_span', $parent);
            $this->record($name, $spanId, is_string($parent) ? $parent : null, $start, hrtime(true), $attributes, $error);
        }
    }

    /** A finished span the caller timed itself (provider calls already know their duration). @param array<string,mixed> $attributes */
    public function finished(string $name, int $durationMs, array $attributes, ?string $error = null): void
    {
        if (! $this->enabled()) {
            return;
        }
        $end = hrtime(true);
        $parent = Context::get('otel_parent_span');
        $this->record($name, bin2hex(random_bytes(8)), is_string($parent) ? $parent : null, $end - max(0, $durationMs) * 1_000_000, $end, $attributes, $error !== null ? new \RuntimeException($error) : null);
    }

    /** Send what is buffered; returns how many spans went out (0 when nothing or the collector refused). */
    public function flush(): int
    {
        if ($this->spans === []) {
            return 0;
        }
        $spans = $this->spans;
        $this->spans = [];
        $headers = ['Content-Type' => 'application/json'];
        foreach (array_filter(explode(',', (string) config('onhost.observability.otlp_headers', ''))) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            if (trim($k) !== '') {
                $headers[trim($k)] = trim($v);
            }
        }
        $body = ['resourceSpans' => [[
            'resource' => ['attributes' => [
                self::attr('service.name', (string) config('onhost.observability.service_name', 'onhost-control-plane')),
                self::attr('deployment.environment', (string) config('onhost.observability.environment', 'production')),
                self::attr('service.version', (string) config('onhost.version', '4.0')),
            ]],
            'scopeSpans' => [['scope' => ['name' => 'onhost.platform'], 'spans' => $spans]],
        ]]];
        try {
            $ok = $this->http->withHeaders($headers)->timeout(3)->connectTimeout(2)->post(rtrim((string) config('onhost.observability.otlp_endpoint'), '/').'/v1/traces', $body)->successful();
        } catch (Throwable) {
            $ok = false;
        }

        return $ok ? count($spans) : 0;
    }

    /**
     * The link that opens a correlation's trace in Grafana / Tempo / Jaeger (audit §5r-2): `ONHOST_TRACE_URL` with
     * `{trace_id}` (and optionally `{correlation_id}`) placeholders; null without a template or a correlation id.
     */
    public static function urlFor(?string $correlationId): ?string
    {
        $template = (string) config('onhost.observability.trace_url', '');
        if ($template === '' || $correlationId === null || $correlationId === '') {
            return null;
        }

        return strtr($template, ['{trace_id}' => md5($correlationId), '{correlation_id}' => rawurlencode($correlationId)]);
    }

    /** The 32-hex trace id of the current correlation id (what the logs and audit rows carry). */
    public static function traceId(): string
    {
        $correlation = Context::get('correlation_id');

        return md5(is_string($correlation) && $correlation !== '' ? $correlation : (string) microtime(true));
    }

    /** @param array<string,mixed> $attributes */
    private function record(string $name, string $spanId, ?string $parentId, int $startNs, int $endNs, array $attributes, ?Throwable $error): void
    {
        $epochOffset = (int) (microtime(true) * 1e9) - hrtime(true);
        $attrs = [];
        foreach ((array) $this->redactor->redact($attributes) as $k => $v) { // secret-looking keys never leave the process
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $attrs[] = self::attr($k, is_scalar($v) ? $v : json_encode($this->redactor->redact($v), JSON_UNESCAPED_UNICODE));
        }
        $attrs[] = self::attr('onhost.correlation_id', (string) (Context::get('correlation_id') ?? ''));
        if (is_string($request = Context::get('request_id'))) {
            $attrs[] = self::attr('onhost.request_id', $request);
        }
        $span = [
            'traceId' => self::traceId(), 'spanId' => $spanId, 'name' => mb_substr($name, 0, 120), 'kind' => 1,
            'startTimeUnixNano' => (string) ($startNs + $epochOffset), 'endTimeUnixNano' => (string) ($endNs + $epochOffset), 'attributes' => $attrs,
            'status' => $error !== null ? ['code' => 2, 'message' => mb_substr($this->redactor->redactString($error->getMessage()), 0, 250)] : ['code' => 1],
        ];
        if ($parentId !== null) {
            $span['parentSpanId'] = $parentId;
        }
        $this->spans[] = $span;
        if (! $this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function(fn () => $this->flush());
        }
        if (count($this->spans) >= self::FLUSH_AT) {
            $this->flush();
        }
    }

    /** @return array{key:string, value:array<string,mixed>} */
    private static function attr(string $key, mixed $value): array
    {
        $typed = match (true) {
            is_bool($value) => ['boolValue' => $value],
            is_int($value) => ['intValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            default => ['stringValue' => mb_substr((string) $value, 0, 500)],
        };

        return ['key' => $key, 'value' => $typed];
    }
}
