<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use Illuminate\Support\Facades\Log;

/**
 * Structured logging context (audit M166).
 *
 * A raw log line — "provisioning failed" — forces whoever is on call to grep
 * back through the surrounding lines to work out WHOSE service, on WHICH server.
 * Pushing customer_id / service_id into the log context stamps every line
 * emitted inside the block, so one search on a service id returns the whole
 * story of that operation.
 *
 * Values pass through SecretRedactor for the same reason ErrorContext does:
 * this context can be shipped to a log aggregator, and a stray token in a
 * caller-supplied field must not ride along.
 */
final class LogContext
{
    /**
     * Run $callback with the given fields stamped on every log line, then
     * restore the previous context. Nesting composes; the inner block's fields
     * win on a key clash and are dropped again on exit.
     *
     * @template T
     * @param  array<string, scalar|null>  $fields
     * @param  callable(): T  $callback
     * @return T
     */
    public static function with(array $fields, callable $callback): mixed
    {
        /** @var array<string, mixed> $safe */
        $safe = SecretRedactor::redact(array_filter(
            $fields,
            static fn (mixed $v): bool => $v !== null,
        ));

        // Laravel has no "pop" — shareContext merges. Snapshot so we can restore.
        Log::withContext($safe);

        try {
            return $callback();
        } finally {
            // Blank the keys we added rather than clearing everything, so a
            // request-level context (request_id) survives the block.
            Log::withContext(array_map(static fn (): null => null, $safe));
        }
    }
}
