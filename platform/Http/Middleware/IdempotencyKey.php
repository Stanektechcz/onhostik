<?php

declare(strict_types=1);

namespace Onhost\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Onhost\Platform\Commands\IdempotencyStore;
use Onhost\Platform\Redaction\Redactor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API contract (`docs/api`): POST with `Idempotency-Key` replays the
 * original response for 24 h; same key + different body => 409.
 *
 * TASK-0041 (P0-16 red team, TASK-0036 follow-up): an answer that hands out a secret — the plaintext token of POST /v1/tokens,
 * an action hook's URL, a generated password — is never kept. The store used to hold the raw body for a day, readable by
 * anybody who reads `idempotency_keys` (a backup, a support query). Such an answer leaves only a marker, and its replay is
 * 409 `already_done`: the request was carried out, the secret was shown once. Any other answer is kept as it was.
 */
final class IdempotencyKey
{
    /** What is kept in place of an answer that carried a secret. */
    private const SHOWN_ONCE = '{"replay":"secret_shown_once"}';

    public function __construct(private readonly IdempotencyStore $store, private readonly Redactor $redactor) {}

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
        if ($replay !== null && $replay['body'] === self::SHOWN_ONCE) {
            return response()->json([
                'error' => 'already_done',
                'message' => 'This request was already carried out. What it handed out (a token, a link, a password) was shown once and is not kept; it cannot be shown again.',
                'original_status' => $replay['status'],
            ], 409)->header('Idempotent-Replayed', 'true');
        }
        if ($replay !== null) {
            return response($replay['body'], $replay['status'])
                ->header('Content-Type', 'application/json')
                ->header('Idempotent-Replayed', 'true');
        }
        $response = $next($request);
        // 401/403/429 mean the request never executed (sign-in, step-up or approval missing, throttled): the client
        // repeats it with the same key once it has fixed that, so those answers must not be replayed
        if ($response->getStatusCode() < 500 && ! in_array($response->getStatusCode(), [401, 403, 429], true) && is_string($response->getContent())) {
            $this->store->rememberHttp($key, $scope, $hash, $response->getStatusCode(), $this->keepable($response->getContent()));
        }

        return $response;
    }

    /** The body as it may be kept for a day: as it was, or the marker when it hands out a secret (a JSON answer or plain text alike). */
    private function keepable(string $body): string
    {
        $decoded = json_decode($body, true);
        $secret = is_array($decoded) ? $this->redactor->carriesSecret($decoded) : $this->redactor->carriesSecret($body);

        return $secret ? self::SHOWN_ONCE : $body;
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
