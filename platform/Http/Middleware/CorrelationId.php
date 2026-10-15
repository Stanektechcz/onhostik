<?php

declare(strict_types=1);

namespace Onhost\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request gets `X-Request-Id` (N21): a client-supplied value is kept only when it
 * is a valid UUID, anything else is replaced by a fresh ULID. The id lands in every log
 * line through Laravel Context. A client-supplied `X-Correlation-Id` (or the
 * request id) is carried through commands, jobs, provider calls and audit rows via
 * Laravel Context (propagated to queued jobs automatically).
 */
final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientRequestId = $request->headers->get('X-Request-Id');
        $requestId = is_string($clientRequestId) && Str::isUuid($clientRequestId) ? strtolower($clientRequestId) : (string) Str::ulid();
        $incoming = $request->headers->get('X-Correlation-Id');
        $correlation = is_string($incoming) && preg_match('/^[A-Za-z0-9_\-:.]{8,128}$/', $incoming) ? $incoming : $requestId;

        Context::add('request_id', $requestId);
        Context::add('correlation_id', $correlation);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Correlation-Id', $correlation);

        return $response;
    }
}
