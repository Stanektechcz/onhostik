<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Carbon;
use Onhost\Domain\Provisioning\Models\Operation;

/**
 * What an operation must forget.
 *
 * An operation carries what it needs to act: the password of the database, the FTP account, the mailbox, the shell
 * user, the customer's mailbox at ANOTHER provider (fetchmail), the private key of an uploaded certificate, the
 * generated WordPress administrator password. It needs them until it has acted — and the row kept them for good, in
 * plain JSON: in the database, in every backup of it, in the staff console, and (the WordPress password) in the
 * answer to anybody who may list the operations of the service.
 *
 *  • succeeded or cancelled → the request (`desired`) forgets its secrets at once; what the run produced for the
 *    customer to read once (`REVEALED`) stays in the result for `reveal_minutes`, then goes too;
 *  • failed → kept while a retry can still use them (`failed_days`), then forgotten;
 *  • pending, running, waiting → untouched: a change queued while a panel is away still has to be carried out.
 */
final class OperationSecrets
{
    /** keys whose values are secrets wherever they stand in the request, the context or the result */
    public const KEYS = ['password', 'admin_password', 'new_password', 'old_password', 'db_password', 'dbpass', 'private_key', 'auth_info', 'secret', 'token', 'totp', 'recovery_code'];

    /** produced by the run for the customer to read once (the generated WordPress administrator password) */
    public const REVEALED = ['admin_password'];

    public const GONE = '[forgotten]';

    public static function revealMinutes(): int
    {
        return max(1, (int) config('onhost.provisioning.secret_reveal_minutes', 30));
    }

    public static function failedDays(): int
    {
        return max(1, (int) config('onhost.provisioning.secret_failed_days', 7));
    }

    /** Until when the result of this operation may still show what it generated; null once that is over (or never was). */
    public static function revealUntil(Operation $operation): ?Carbon
    {
        if ($operation->state !== Operation::SUCCEEDED || $operation->finished_at === null || $operation->secrets_scrubbed_at !== null) {
            return null;
        }
        $until = $operation->finished_at->copy()->addMinutes(self::revealMinutes());

        return $until->isFuture() ? $until : null;
    }

    /** Called when an operation ends: forgets what is no longer needed right now. */
    public static function onFinished(Operation $operation): void
    {
        if (! in_array($operation->state, [Operation::SUCCEEDED, Operation::CANCELLED], true)) {
            return; // a failed run may be retried with what it was given
        }
        $reveals = $operation->state === Operation::SUCCEEDED && self::holds((array) $operation->result, self::REVEALED);
        $operation->forceFill([
            'desired' => self::forget((array) $operation->desired),
            'context' => self::forget((array) $operation->context, $reveals ? self::REVEALED : []),
            'result' => $operation->result === null ? null : self::forget((array) $operation->result, $reveals ? self::REVEALED : []),
            'secrets_scrubbed_at' => $reveals ? null : now(), // the sweep comes back for the revealed part
        ])->save();
    }

    /**
     * The sweep: finished operations still holding something (legacy rows, revealed results whose window is over,
     * failed runs past their retry days).
     *
     * @return array{scrubbed:int}
     */
    public static function sweep(int $limit = 500): array
    {
        $scrubbed = 0;
        $due = Operation::query()->whereNull('secrets_scrubbed_at')
            ->where(fn ($q) => $q
                ->where(fn ($done) => $done->whereIn('state', [Operation::SUCCEEDED, Operation::CANCELLED])->where(fn ($t) => $t->whereNull('finished_at')->orWhere('finished_at', '<=', now()->subMinutes(self::revealMinutes()))))
                ->orWhere(fn ($failed) => $failed->where('state', Operation::FAILED)->where(fn ($t) => $t->whereNull('finished_at')->orWhere('finished_at', '<=', now()->subDays(self::failedDays())))))
            ->orderBy('queued_at')->limit(max(1, $limit))->get();
        foreach ($due as $operation) {
            $operation->forceFill([
                'desired' => self::forget((array) $operation->desired), 'context' => self::forget((array) $operation->context),
                'result' => $operation->result === null ? null : self::forget((array) $operation->result), 'secrets_scrubbed_at' => now(),
            ])->save();
            $scrubbed++;
        }

        return ['scrubbed' => $scrubbed];
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @param  list<string>  $keep  secret keys that stay for now
     * @return array<array-key,mixed>
     */
    public static function forget(array $data, array $keep = []): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::forget($value, $keep);
            } elseif (is_string($key) && ! in_array(strtolower($key), $keep, true) && self::isSecret(strtolower($key), $value, $data)) {
                $data[$key] = self::GONE;
            }
        }

        return $data;
    }

    /** @param array<array-key,mixed> $siblings the array the key stands in */
    private static function isSecret(string $key, mixed $value, array $siblings): bool
    {
        if (! is_string($value) || $value === '' || $value === self::GONE) {
            return false;
        }

        return match ($key) {
            // `key` is the private key of an uploaded certificate — and the NAME of a game server variable
            'key' => strlen($value) > 64 || str_contains($value, 'PRIVATE KEY'),
            // the value of a variable that is a secret by its name (RCON_PASSWORD, API_TOKEN)
            'value' => is_string($siblings['key'] ?? null) && preg_match('/PASS|SECRET|TOKEN|API_?KEY|PRIVATE/i', (string) $siblings['key']) === 1,
            default => in_array($key, self::KEYS, true),
        };
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @param  list<string>  $keys
     */
    private static function holds(array $data, array $keys): bool
    {
        foreach ($data as $key => $value) {
            if (is_array($value) ? self::holds($value, $keys) : (is_string($key) && in_array(strtolower($key), $keys, true) && self::isSecret(strtolower($key), $value, $data))) {
                return true;
            }
        }

        return false;
    }
}
