<?php

declare(strict_types=1);

namespace Onhost\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request gets `X-Request-Id`; a client-supplied `X-Correlation-Id` (or the
 * request id) is carried through commands, jobs, provider calls and audit rows via
 * Laravel Context (propagated to queued jobs automatically).
 */
final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::ulid();
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
