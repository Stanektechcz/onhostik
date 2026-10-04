<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Tokens;

/**
 * How long a personal API token lives (owner decision R9 of the 2026-10 readiness audit, TASK-0044).
 *
 * A token without an end is a credential nobody remembers handing out. Every new token ends: by default after
 * `onhost.tokens.default_days` (365), never later than `onhost.tokens.max_days` (365, the operator's cap — lower it and the
 * default follows). A token asking for more is cut to the cap, one asking for nothing gets the default. GrantPolicy then ends it
 * no later than the membership and the bindings behind its scopes (I5). Sanctum refuses a token whose `expires_at` has passed
 * (and IdentityCommandAuthorizer::asToken decides nothing for one), so an expired token is a 401, not a quiet success.
 *
 * Tokens issued before an end existed are not changed here (S1-05: no forced retrofit); `operator:tokens:unbound` lists them.
 */
final class TokenLifetime
{
    public const FALLBACK_DAYS = 365;

    public static function maxDays(): int
    {
        return max(1, (int) config('onhost.tokens.max_days', self::FALLBACK_DAYS));
    }

    public static function defaultDays(): int
    {
        return min(self::maxDays(), max(1, (int) config('onhost.tokens.default_days', self::FALLBACK_DAYS)));
    }

    /** The days a token asked for (`expires_in_days`, null = not said) as it will be issued: the default, or cut to the cap. */
    public static function days(mixed $asked): int
    {
        if ($asked === null || $asked === '') {
            return self::defaultDays();
        }

        return max(1, min(self::maxDays(), (int) $asked));
    }
}
