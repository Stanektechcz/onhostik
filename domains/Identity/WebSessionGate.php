<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Web sessions end with access whatever stores them (TASK-0044, D20 / S1-08).
 *
 * SessionKill deleted the rows of the database session driver — but production keeps sessions in Redis, where there is no row per
 * person to delete. So the end is a mark per person instead (endAll), and every session is asked against it when the web guard
 * loads its user (Authenticated): a session signed in before the mark is logged out on its next request. A session remembers when
 * it signed in (Login, `onhost_signed_in_at`); one that does not know (opened before this release) counts as older than any mark.
 * The "remember me" cookie is ended separately (SessionKill rotates the remember token), so a new sign-in is a real one.
 * API tokens never pass through the web guard; they are revoked on their own (ApiAccessRevocation).
 */
final class WebSessionGate
{
    public const SESSION_KEY = 'onhost_signed_in_at';

    /** Every web session of `$userId` signed in until now ends on its next request. */
    public static function endAll(string $userId): void
    {
        $minutes = max(60 * 24, (int) config('session.lifetime', 120)); // as long as a session may live, at least a day
        Cache::put(self::key($userId), now()->toIso8601String(), now()->addMinutes($minutes));
    }

    public function signedIn(Login $event): void
    {
        if ($event->guard === 'web' && request()->hasSession()) {
            request()->session()->put(self::SESSION_KEY, now()->toIso8601String());
        }
    }

    public function authenticated(Authenticated $event): void
    {
        if ($event->guard !== 'web' || ! request()->hasSession()) {
            return;
        }
        $ended = Cache::get(self::key((string) $event->user->getAuthIdentifier()));
        if (! is_string($ended) || $ended === '') {
            return;
        }
        $signedIn = request()->session()->get(self::SESSION_KEY);
        if (is_string($signedIn) && $signedIn !== '' && CarbonImmutable::parse($signedIn)->isAfter(CarbonImmutable::parse($ended))) {
            return;
        }
        Auth::guard('web')->logout();
        request()->session()->invalidate();
    }

    private static function key(string $userId): string
    {
        return "onhost:web-sessions:ended:{$userId}";
    }
}
