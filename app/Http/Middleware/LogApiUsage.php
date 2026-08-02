<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Api\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records API usage (endpoint, status, latency) for analytics and per-token
 * rate-limit reporting.
 *
 * The write happens in `terminate()` — after the response has been sent to the
 * client (audit 500 #29/#30). Telemetry must not sit on the request's critical
 * path: an insert here previously added its latency to every single API call.
 * A logging failure is swallowed for the same reason — losing one analytics row
 * must never turn a successful API call into an error.
 */
class LogApiUsage
{
    private const START_KEY = 'api_usage_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        // Stored on the request, not on $this: Laravel resolves a fresh
        // middleware instance for terminate(), so instance state would be lost.
        $request->attributes->set(self::START_KEY, hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get(self::START_KEY);

        // handle() never ran — the request was rejected upstream (e.g. failed
        // auth), so it never counted as API usage. terminate() still fires for
        // every registered middleware, hence this guard.
        if (! is_float($startedAt) && ! is_int($startedAt)) {
            return;
        }

        $elapsed = (int) round((hrtime(true) - $startedAt) / 1_000_000); // ns → ms

        try {
            $user  = $request->user();
            $token = $user?->currentAccessToken();

            // Stateful (SPA-cookie) Sanctum auth yields a TransientToken, which
            // has no id — only a real personal access token has one to log.
            $tokenId = $token instanceof PersonalAccessToken ? $token->getKey() : null;

            ApiUsageLog::create([
                'user_id'          => $user?->id,
                'token_id'         => $tokenId,
                'endpoint'         => $request->path(),
                'method'           => $request->method(),
                'status_code'      => $response->getStatusCode(),
                'response_time_ms' => $elapsed,
                'ip_address'       => $request->ip(),
            ]);
        } catch (Throwable) {
            // Analytics are best-effort; never break a served response.
        }
    }
}
