<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * TASK-0076 (D7): a 401 never reached `throttle:api` (authentication runs first), so guessing bearer tokens was free. Every
 * 401 answered on a signed-in route counts against the caller's ADDRESS, whatever token was sent; once the ceiling is reached
 * the address gets 429 before authentication is even attempted. Successful requests cost nothing.
 */
final class ThrottleFailedAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // only a request that PRESENTED a bearer token can be a guess: a logged-out SPA polling /me with no credentials (or an expired
        // session cookie) answers 401 all day and must not lock its own address out
        if ($request->bearerToken() === null) {
            return $next($request);
        }
        // only a request that PRESENTED a bearer token can be a guess: a logged-out SPA polling /me with no credentials (or an expired
        // session cookie) answers 401 all day and must not lock its own address out
        if ($request->bearerToken() === null) {
            return $next($request);
        }
        $key = 'auth-failed:'.$request->ip();
        $max = (int) config('onhost.api.failed_auth_per_minute', 30);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return response()->json(['error' => 'too_many_requests', 'message' => 'Too many failed authentication attempts.', 'status' => 429], 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        $response = $next($request);
        if ($response->getStatusCode() === 401) {
            RateLimiter::hit($key, 60);
        }

        return $response;
    }
}
