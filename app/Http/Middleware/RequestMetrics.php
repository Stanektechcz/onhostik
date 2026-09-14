<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts requests per route family and status class for the /metrics endpoint (onhost_http_requests_total).
 * Cheap: one cache increment per request; the exporter reads the counters. Families: v1, staff, surfaces, web.
 */
final class RequestMetrics
{
    public const FAMILIES = ['v1', 'staff', 'surfaces', 'web'];

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        try {
            $family = match (true) {
                $request->is('v1/staff/*') => 'staff',
                $request->is('v1/*') => 'v1',
                $request->is('surfaces/*') => 'surfaces',
                default => 'web',
            };
            $class = intdiv($response->getStatusCode(), 100).'xx';
            Cache::increment("metrics:http:{$family}:{$class}");
            $ms = (int) round((microtime(true) - $started) * 1000);
            Cache::increment("metrics:http:{$family}:ms", $ms);
            Cache::increment("metrics:http:{$family}:n");
            if ($ms > 1000) {
                Cache::increment("metrics:http:{$family}:slow");
            }
        } catch (\Throwable) {
            // metrics must never break a request
        }

        return $response;
    }
}
