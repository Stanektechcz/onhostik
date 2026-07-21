<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit J141: give every request an id and put it in the log context.
 *
 * Without this, "it broke around 14:30" turns into grepping a day of logs and
 * guessing which lines belong together. With it, the customer reads the id off
 * the error page and every log line and (later) every tracker event for that
 * request is one search away.
 *
 * An inbound X-Request-Id is honoured so a load balancer or an upstream caller
 * can correlate across hops — but it is length-capped and stripped of anything
 * exotic, because it lands in log files that people read and tools parse.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveId($request);

        $request->attributes->set('request_id', $requestId);

        Log::shareContext(['request_id' => $requestId]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveId(Request $request): string
    {
        $inbound = (string) $request->headers->get('X-Request-Id', '');

        // Only accept something that is plainly an id. Anything else is either
        // a mistake or someone trying to inject newlines into the log stream.
        if (preg_match('/^[A-Za-z0-9._-]{8,64}$/', $inbound) === 1) {
            return $inbound;
        }

        return (string) Str::uuid();
    }
}
