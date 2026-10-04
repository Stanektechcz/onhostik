<?php

declare(strict_types=1);

namespace Onhost\Platform\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Platform\Commands\IdempotencyStore;
use Onhost\Platform\Redaction\Redactor;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Public API contract (`docs/api`): a write with `Idempotency-Key` replays the
 * original response for 24 h; same key + different body => 409.
 *
 * TASK-0041 (P0-16 red team, TASK-0036 follow-up): an answer that hands out a secret — the plaintext token of POST /v1/tokens,
 * an action hook's URL, a generated password — is never kept. The store used to hold the raw body for a day, readable by
 * anybody who reads `idempotency_keys` (a backup, a support query). Such an answer leaves only a marker, and its replay is
 * 409 `already_done`: the request was carried out, the secret was shown once. Any other answer is kept as it was.
 *
 * Phase D5: the key belongs to one person, one organization and one API token. It was the person's alone, with the organization
 * outside the fingerprint: the same key and body sent to two organizations was answered with the first one's result and nothing
 * ran for the second; two tokens of one person shared their keys. The key is now reserved before the request runs (an atomic
 * insert, IdempotencyStore::reserveHttp) — a duplicate that arrives while the first still runs is 409 `idempotency_in_progress`
 * instead of a second execution — and DELETE is covered like the other writes. The middleware's own refusals name their status.
 */
final class IdempotencyKey
{
    /** What is kept in place of an answer that carried a secret. */
    private const SHOWN_ONCE = '{"replay":"secret_shown_once"}';

    /** The writes a key protects. */
    private const METHODS = ['POST', 'PATCH', 'PUT', 'DELETE'];

    /** How long a reservation holds the key while its request runs; a worker that died frees it after this. */
    private const IN_FLIGHT_SECONDS = 600;

    /** What a duplicate of a running request is told to wait before it asks again. */
    private const RETRY_AFTER_SECONDS = 2;

    /** The session key under which the panel keeps the organization chosen in it (App\Http\Support\CurrentOrganization::SESSION_KEY). */
    public const SESSION_ORGANIZATION = 'onhost_organization';

    public function __construct(private readonly IdempotencyStore $store, private readonly Redactor $redactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get('Idempotency-Key');
        if (! is_string($key) || $key === '' || ! in_array($request->method(), self::METHODS, true)) {
            return $next($request);
        }
        if (strlen($key) > 200) {
            return $this->problem(422, 'invalid_idempotency_key', 'Idempotency-Key must be at most 200 characters.');
        }
        $organization = $this->organization($request);
        $scope = $this->scope($request, $organization);
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$organization.'|'.$request->getContent());
        $held = $this->store->reserveHttp($key, $scope, $hash, self::IN_FLIGHT_SECONDS);
        if ($held !== null) {
            return $this->answerHeld($held['status'], (string) $held['body']);
        }
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->store->releaseHttp($key, $scope, $hash);

            throw $e;
        }
        // 401/403/429 mean the request never executed (sign-in, step-up or approval missing, throttled): the client
        // repeats it with the same key once it has fixed that, so those answers must not be replayed — nor a server error
        $status = $response->getStatusCode();
        if ($status < 500 && ! in_array($status, [401, 403, 429], true) && is_string($response->getContent())) {
            $this->store->completeHttp($key, $scope, $hash, $status, $this->keepable($response->getContent()));
        } else {
            $this->store->releaseHttp($key, $scope, $hash);
        }

        return $response;
    }

    /** The answer for a key another request already holds: still running, done with a secret, or done (replayed as it was). */
    private function answerHeld(?int $status, string $body): Response
    {
        if ($status === null) {
            return $this->problem(409, 'idempotency_in_progress', 'A request with this Idempotency-Key is still being carried out. Repeat it with the same key in a moment to get its answer.')
                ->header('Retry-After', (string) self::RETRY_AFTER_SECONDS);
        }
        if ($body === self::SHOWN_ONCE) {
            return $this->problem(409, 'already_done', 'This request was already carried out. What it handed out (a token, a link, a password) was shown once and is not kept; it cannot be shown again.', ['original_status' => $status])
                ->header('Idempotent-Replayed', 'true');
        }

        return response($body, $status)
            ->header('Content-Type', 'application/json')
            ->header('Idempotent-Replayed', 'true');
    }

    /** @param array<string,mixed> $extra */
    private function problem(int $status, string $error, string $message, array $extra = []): JsonResponse
    {
        return response()->json(['error' => $error, 'message' => $message, 'status' => $status] + $extra, $status);
    }

    /** The body as it may be kept for a day: as it was, or the marker when it hands out a secret (a JSON answer or plain text alike). */
    private function keepable(string $body): string
    {
        $decoded = json_decode($body, true);
        $secret = is_array($decoded) ? $this->redactor->carriesSecret($decoded) : $this->redactor->carriesSecret($body);

        return $secret ? self::SHOWN_ONCE : $body;
    }

    /**
     * The organization the request acts for, as the request names it: `X-Organization`, `?organization=`, else the one chosen
     * in the web session. Only a discriminator here — whether the caller may act for it is the controller's question.
     */
    private function organization(Request $request): string
    {
        $named = $request->headers->get('X-Organization') ?: $request->query('organization');
        if (is_string($named) && $named !== '') {
            return $named;
        }
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_ORGANIZATION) : null;

        return is_string($chosen) ? $chosen : '';
    }

    /** One person (or one address for a visitor), one API token, one organization — hashed, the column holds 120. */
    private function scope(Request $request, string $organization): string
    {
        $user = $request->user();
        if ($user === null) {
            return 'ip:'.(string) $request->ip().'|'.substr(hash('sha256', 'org:'.$organization), 0, 32);
        }
        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $tokenId = $token instanceof Model ? (string) $token->getKey() : '';

        return 'user:'.$user->getAuthIdentifier().'|'.substr(hash('sha256', 'org:'.$organization.'|token:'.$tokenId), 0, 32);
    }
}
