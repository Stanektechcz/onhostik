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

    /** F7: upper bounds (seconds) of the onhost_http_request_duration_seconds histogram; +Inf is implicit. */
    public const BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    /** Cache key of the (non-cumulative) bucket a duration falls into; the exporter accumulates. */
    public static function bucketKey(string $family, float $seconds): string
    {
        foreach (self::BUCKETS as $i => $le) {
            if ($seconds <= $le) {
                return "metrics:http:{$family}:b{$i}";
            }
        }

        return "metrics:http:{$family}:binf";
    }

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
            Cache::store('metrics')->increment("metrics:http:{$family}:{$class}");
            $seconds = microtime(true) - $started;
            $ms = (int) round($seconds * 1000);
            Cache::store('metrics')->increment(self::bucketKey($family, $seconds));
            Cache::store('metrics')->increment("metrics:http:{$family}:ms", $ms);
            Cache::store('metrics')->increment("metrics:http:{$family}:n");
            if ($ms > 1000) {
                Cache::store('metrics')->increment("metrics:http:{$family}:slow");
            }
        } catch (\Throwable) {
            // metrics must never break a request
        }

        return $response;
    }
}
