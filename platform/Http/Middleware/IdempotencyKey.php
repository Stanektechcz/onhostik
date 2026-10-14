<?php

declare(strict_types=1);

namespace Onhost\Platform\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    /** The shortest window a reservation holds the key while its request runs; a worker that died frees it after the window. */
    private const MIN_IN_FLIGHT_SECONDS = 600;

    /** The window when PHP sets no limit on a request (max_execution_time = 0). */
    private const UNLIMITED_IN_FLIGHT_SECONDS = 3600;

    /** The longest key, unless configured (`onhost.api.idempotency_key_max_length`). */
    private const DEFAULT_KEY_MAX_LENGTH = 200;

    /** The longest wait a duplicate of a running request is told; the wait grows with the age of the reservation up to this. */
    private const MAX_RETRY_AFTER_SECONDS = 60;

    /** The session key under which the panel keeps the organization chosen in it (App\Http\Support\CurrentOrganization::SESSION_KEY). */
    public const SESSION_ORGANIZATION = 'onhost_organization';

    public function __construct(private readonly IdempotencyStore $store, private readonly Redactor $redactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get('Idempotency-Key');
        if (! is_string($key) || $key === '' || ! in_array($request->method(), self::METHODS, true)) {
            return $next($request);
        }
        $maxLength = (int) config('onhost.api.idempotency_key_max_length', self::DEFAULT_KEY_MAX_LENGTH);
        if (strlen($key) > $maxLength) {
            return $this->problem(422, 'invalid_idempotency_key', "Idempotency-Key must be at most {$maxLength} characters.");
        }
        $organization = $this->organization($request);
        $scope = $this->scope($request, $organization);
        // the query is part of the request (`DELETE …?force=1` is another request than without it), in a canonical order
        $query = Request::normalizeQueryString($request->getQueryString());
        $hash = hash('sha256', $request->method().'|'.$request->path().'?'.$query.'|'.$organization.'|'.$request->getContent().self::uploads($request));
        $held = $this->store->reserveHttp($key, $scope, $hash, self::inFlightSeconds());
        $token = $held['token'];
        if ($token === null) {
            return $this->answerHeld($held['status'], (string) $held['body'], $held['age']);
        }
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->store->releaseHttp($key, $scope, $token);

            throw $e;
        }
        // 401/403/429 mean the request never executed (sign-in, step-up or approval missing, throttled): the client
        // repeats it with the same key once it has fixed that, so those answers must not be replayed — nor a server error
        $status = $response->getStatusCode();
        if ($status < 500 && ! in_array($status, [401, 403, 429], true) && is_string($response->getContent())) {
            // false: this request outlived its reservation and another took the key over — its answer is not kept (H1)
            $this->store->completeHttp($key, $scope, $token, $status, $this->keepable($response->getContent()));
        } else {
            $this->store->releaseHttp($key, $scope, $token);
        }

        return $response;
    }

    /**
     * What a multipart request carries (TASK-0110 review M4): PHP leaves the body of `multipart/form-data` out of `getContent()`,
     * so two different files under one key hashed the same and the second upload was answered with the first one's result. The
     * fields and the SHA-256 of every file go into the fingerprint instead: another file under the same key is a conflict (409).
     * A request without files adds nothing — every other fingerprint stays exactly what it was.
     */
    private static function uploads(Request $request): string
    {
        $files = $request->allFiles();
        if ($files === []) {
            return '';
        }
        $digests = [];
        array_walk_recursive($files, function ($file, $name) use (&$digests): void {
            if ($file instanceof UploadedFile) {
                $path = (string) $file->getRealPath();
                $digests[] = $name.'='.($path !== '' && is_file($path) ? (string) hash_file('sha256', $path) : 'unreadable');
            }
        });
        sort($digests);
        $fields = $request->except(array_keys($files));
        ksort($fields);

        return '|files:'.implode(',', $digests).'|fields:'.json_encode($fields);
    }

    /** The answer for a key another request already holds: still running, done with a secret, or done (replayed as it was). */
    private function answerHeld(?int $status, string $body, int $age): Response
    {
        if ($status === null) {
            // a request that has run for a while will likely run a while longer: wait about half its age, 1 s to 60 s
            $retryAfter = min(self::MAX_RETRY_AFTER_SECONDS, max(1, intdiv($age, 2) + 1));

            return $this->problem(409, 'idempotency_in_progress', 'A request with this Idempotency-Key is still being carried out. Repeat it with the same key in a moment to get its answer.')
                ->header('Retry-After', (string) $retryAfter);
        }
        if ($body === self::SHOWN_ONCE) {
            return $this->problem(409, 'already_done', 'This request was already carried out. What it handed out (a token, a link, a password) was shown once and is not kept; it cannot be shown again.', ['original_status' => $status])
                ->header('Idempotent-Replayed', 'true');
        }

        return response($body, $status)
            ->header('Content-Type', 'application/json')
            ->header('Idempotent-Replayed', 'true');
    }

    /**
     * How long a reservation holds the key: `onhost.api.idempotency_in_flight_seconds` when configured, else longer than PHP
     * lets a request run — twice max_execution_time plus a minute (on Linux that limit counts CPU time, not time spent waiting on
     * a panel), at least 10 minutes, an hour when PHP sets no limit. A request still running after its window has lost the key:
     * a duplicate may run, and the late answer is not kept.
     */
    private static function inFlightSeconds(): int
    {
        $configured = config('onhost.api.idempotency_in_flight_seconds');
        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }
        $limit = (int) ini_get('max_execution_time');

        return $limit <= 0 ? self::UNLIMITED_IN_FLIGHT_SECONDS : max(self::MIN_IN_FLIGHT_SECONDS, 2 * $limit + 60);
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
     * in the web session — read as ApiContext::organization reads it (a value that is not a non-empty string, an array from
     * `?organization[]=`, names none). Only a discriminator here — whether the caller may act for it is the controller's question.
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
