<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/**
 * Web sessions end with access whatever stores them (TASK-0044, D20 / S1-08).
 *
 * SessionKill deleted the rows of the database session driver — but production keeps sessions in Redis, where there is no row per
 * person to delete. So the end is a mark per person instead (endAll), and every session is asked against it when the web guard
 * loads its user (Authenticated): a session signed in before the mark is logged out on its next request. A session remembers when
 * it signed in (Login, `onhost_signed_in_at`); one that does not know (opened before this release) counts as older than any mark.
 * The "remember me" cookie is ended separately (SessionKill rotates the remember token), so a new sign-in is a real one.
 * API tokens never pass through the web guard; they are revoked on their own (ApiAccessRevocation).
 *
 * TASK-0067 (PR #53 review): the mark was a cache entry only — a flush or an eviction revived every session it had ended. It is a
 * row of `session_ends` now (SessionEnds), the cache a copy; every sign-in path of the portal goes through the web guard's login()
 * (password, MFA, password reset, registration, guest checkout, the local dev link), which fires Login and stamps the session.
 */
final class WebSessionGate
{
    public const SESSION_KEY = 'onhost_signed_in_at';

    /** Every web session of `$userId` signed in until now ends on its next request. */
    public static function endAll(string $userId): void
    {
        app(SessionEnds::class)->mark($userId, SessionEnds::WEB); // in the database: a cache flush must not revive what this ended
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
        $ended = app(SessionEnds::class)->endedAt((string) $event->user->getAuthIdentifier(), SessionEnds::WEB);
        if ($ended === null) {
            return;
        }
        $signedIn = request()->session()->get(self::SESSION_KEY);
        if (is_string($signedIn) && $signedIn !== '' && CarbonImmutable::parse($signedIn)->isAfter($ended)) {
            return;
        }
        Auth::guard('web')->logout();
        request()->session()->invalidate();
    }
}
