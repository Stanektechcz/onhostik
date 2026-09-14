<?php

declare(strict_types=1);

namespace Onhost\Platform\ProviderHttp;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Platform\Observability\Tracer;
use Onhost\Platform\Redaction\Redactor;

/**
 * Every vendor call is written to `provider_calls` with masked bodies (retention 90
 * days, pruned by `onhost:provider-calls:prune`). Bodies above 16 KB are truncated.
 */
final class ProviderCallLogger
{
    public function __construct(private readonly Redactor $redactor, private readonly Tracer $tracer) {}

    /** @param array<string,mixed> $requestSummary */
    public function log(
        ProviderRequest $request,
        ?int $httpStatus,
        ?string $bodyCode,
        bool $ok,
        int $durationMs,
        array $requestSummary,
        mixed $responseSummary,
        ?string $error = null,
    ): string {
        $id = 'pcall_'.strtolower((string) Str::ulid());
        $actor = Context::get('actor') ?? 'worker';

        DB::table('provider_calls')->insert([
            'id' => $id,
            'provider' => $request->provider,
            'instance_key' => $request->instanceKey,
            'action' => $request->action,
            'method' => $request->method,
            'path' => $this->pathOf($request->url),
            'http_status' => $httpStatus,
            'body_code' => $bodyCode === null ? null : mb_substr($bodyCode, 0, 60),
            'ok' => $ok,
            'duration_ms' => $durationMs,
            'operation_id' => $request->operationId,
            'correlation_id' => Context::get('correlation_id'),
            'actor' => is_string($actor) ? mb_substr($actor, 0, 120) : 'worker',
            'request' => $this->truncate($this->redactor->redact($requestSummary)),
            'response' => $this->truncate($this->redactor->redact($responseSummary)),
            'error' => $error === null ? null : mb_substr($this->redactor->redactString($error), 0, 500),
            'created_at' => now(),
        ]);
        $this->tracer->finished('provider.call '.$request->provider.' '.$request->action, $durationMs, ['onhost.provider' => $request->provider, 'onhost.instance' => $request->instanceKey, 'http.method' => $request->method, 'http.status_code' => $httpStatus, 'onhost.provider_call' => $id, 'onhost.operation' => $request->operationId], $ok ? null : ((string) $error ?: 'provider call failed')); // audit §5q-2

        return $id;
    }

    private function pathOf(string $url): string
    {
        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return mb_substr($this->redactor->redactString($path), 0, 500);
    }

    private function truncate(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
        if (strlen($json) > 16384) {
            return substr($json, 0, 16384).'…[truncated]';
        }

        return $json;
    }
}
