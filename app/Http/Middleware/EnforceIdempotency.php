<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the Idempotency-Key header on API writes (audit J136).
 *
 * A client whose connection drops mid-request cannot tell whether the order
 * was placed. Without idempotency its only options are to retry (risking a
 * duplicate charge) or not to (risking a lost request). With it, the retry
 * replays the original outcome and nothing happens twice.
 *
 * Behaviour:
 *  - no header            → passes through untouched (opt-in)
 *  - first use of a key   → request runs, response is stored and returned
 *  - repeat, same body    → stored response replayed, handler NOT re-run
 *  - repeat, other body   → 422; reusing a key for a different request is a
 *                           client bug and silently replaying would hide it
 *  - still in flight      → 409; the first attempt has not finished yet
 */
class EnforceIdempotency
{
    /** Only bodies of writes are considered; GET/HEAD are already idempotent. */
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '' || ! in_array($request->method(), self::METHODS, true)) {
            return $next($request);
        }

        if (mb_strlen($key) > 128) {
            return response()->json(['error' => 'Idempotency-Key is too long (max 128 characters).'], 400);
        }

        $tokenId = $request->user()?->currentAccessToken()?->id;
        $hash    = hash('sha256', $request->getContent() ?: '');

        /** @var IdempotencyKey|null $existing */
        $existing = null;

        // Claim the key atomically so two simultaneous retries cannot both
        // execute the handler.
        DB::transaction(function () use ($key, $tokenId, $request, $hash, &$existing): void {
            $existing = IdempotencyKey::query()
                ->where('token_id', $tokenId)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                $existing = IdempotencyKey::create([
                    'token_id'        => $tokenId,
                    'idempotency_key' => $key,
                    'method'          => $request->method(),
                    'path'            => mb_substr($request->path(), 0, 255),
                    'request_hash'    => $hash,
                ]);
                $existing->wasRecentlyCreated = true;
            }
        });

        if ($existing === null) {
            return $next($request); // defensive; should not happen
        }

        if (! $existing->wasRecentlyCreated) {
            if (! hash_equals($existing->request_hash, $hash)) {
                return response()->json([
                    'error' => 'This Idempotency-Key was already used with a different request body.',
                ], 422);
            }

            if (! $existing->isCompleted()) {
                return response()->json([
                    'error' => 'A request with this Idempotency-Key is still being processed.',
                ], 409);
            }

            /** @var array<string, mixed>|null $body */
            $body = json_decode((string) $existing->response_body, true);

            return response()->json($body ?? [], (int) $existing->response_status)
                ->header('Idempotent-Replay', 'true');
        }

        $response = $next($request);

        // Only successful outcomes are worth replaying — a failure should be
        // retryable with the same key once the cause is fixed.
        if ($response->getStatusCode() < 400) {
            $existing->update([
                'response_status' => $response->getStatusCode(),
                'response_body'   => $response->getContent(),
                'completed_at'    => now(),
            ]);
        } else {
            $existing->delete();
        }

        return $response;
    }
}
