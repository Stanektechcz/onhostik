<?php

declare(strict_types=1);

namespace Onhost\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Onhost\Platform\Commands\IdempotencyStore;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API contract (`docs/api`): POST with `Idempotency-Key` replays the
 * original response for 24 h; same key + different body => 409.
 */
final class IdempotencyKey
{
    public function __construct(private readonly IdempotencyStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get('Idempotency-Key');
        if (! is_string($key) || $key === '' || ! in_array($request->method(), ['POST', 'PATCH', 'PUT'], true)) {
            return $next($request);
        }
        if (strlen($key) > 200) {
            return response()->json(['error' => 'invalid_idempotency_key', 'message' => 'Idempotency-Key must be at most 200 characters.'], 422);
        }
        $scope = $this->scope($request);
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
        $replay = $this->store->findHttp($key, $scope, $hash);
        if ($replay !== null) {
            return response($replay['body'], $replay['status'])
                ->header('Content-Type', 'application/json')
                ->header('Idempotent-Replayed', 'true');
        }
        $response = $next($request);
        // 401/403/429 mean the request never executed (sign-in, step-up or approval missing, throttled): the client
        // repeats it with the same key once it has fixed that, so those answers must not be replayed
        if ($response->getStatusCode() < 500 && ! in_array($response->getStatusCode(), [401, 403, 429], true) && is_string($response->getContent())) {
            $this->store->rememberHttp($key, $scope, $hash, $response->getStatusCode(), $response->getContent());
        }

        return $response;
    }

    private function scope(Request $request): string
    {
        $user = $request->user();
        if ($user !== null) {
            return 'user:'.$user->getAuthIdentifier();
        }

        return 'ip:'.(string) $request->ip();
    }
}
