<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
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
 *
 * TASK-0070 (audit 2026-10 C11): one session at a time as well. Every session has a row of `web_sessions` (WebSessions) — opened at
 * sign-in, or on the first request of a session older than this release — so the person can see their sessions and end one of
 * them from another device. A session whose row was ended is logged out on its next request, like one signed in before an end
 * mark; one whose row is missing or another person's gets a row of its own (nobody else's row is touched). A sign-out ends its
 * own row.
 */
final class WebSessionGate
{
    public const SESSION_KEY = 'onhost_signed_in_at';

    /** Every web session of `$userId` signed in until now ends on its next request. */
    public static function endAll(string $userId): void
    {
        app(SessionEnds::class)->mark($userId, SessionEnds::WEB); // in the database: a cache flush must not revive what this ended
        app(WebSessions::class)->endAllBut($userId, null, WebSessions::ENDED_ALL); // and the list of sessions says so (TASK-0070)
    }

    public function signedIn(Login $event): void
    {
        if ($event->guard === 'web' && request()->hasSession()) {
            request()->session()->put(self::SESSION_KEY, now()->toIso8601String());
            request()->session()->put(WebSessions::SESSION_KEY, app(WebSessions::class)->open((string) $event->user->getAuthIdentifier(), request()->ip(), request()->userAgent()));
        }
    }

    /** A sign-out ends the row of its own session (TASK-0070). */
    public function signedOut(Logout $event): void
    {
        if ($event->guard !== 'web' || $event->user === null || ! request()->hasSession()) {
            return;
        }
        $id = request()->session()->get(WebSessions::SESSION_KEY);
        if (is_string($id) && $id !== '') {
            app(WebSessions::class)->end($id, (string) $event->user->getAuthIdentifier(), WebSessions::SIGNED_OUT);
        }
    }

    public function authenticated(Authenticated $event): void
    {
        if ($event->guard !== 'web' || ! request()->hasSession()) {
            return;
        }
        $userId = (string) $event->user->getAuthIdentifier();
        $ended = app(SessionEnds::class)->endedAt($userId, SessionEnds::WEB);
        $signedIn = request()->session()->get(self::SESSION_KEY);
        if ($ended !== null && ! (is_string($signedIn) && $signedIn !== '' && CarbonImmutable::parse($signedIn)->isAfter($ended))) {
            $this->logOut();

            return;
        }
        // TASK-0070: this one session may have been ended from another device
        $id = request()->session()->get(WebSessions::SESSION_KEY);
        $state = is_string($id) && $id !== '' ? app(WebSessions::class)->state($id, $userId, request()->ip()) : WebSessions::UNKNOWN;
        if ($state === WebSessions::ENDED) {
            $this->logOut();

            return;
        }
        if ($state === WebSessions::ALIVE) {
            return;
        }
        // a session opened before this release (or whose row is not this person's) gets its own row now, so it is listed and can be ended
        request()->session()->put(WebSessions::SESSION_KEY, app(WebSessions::class)->open($userId, request()->ip(), request()->userAgent()));
    }

    private function logOut(): void
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
    }
}
