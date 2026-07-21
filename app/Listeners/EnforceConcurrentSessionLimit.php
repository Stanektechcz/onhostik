<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Caps the number of simultaneous sessions per user (audit 29).
 *
 * Listing sessions and revoking them by hand already existed; what was missing
 * was an automatic ceiling. Without one, an attacker who obtains a password can
 * hold a session indefinitely alongside the rightful owner — neither party
 * evicts the other, and the owner has no signal anything is wrong.
 *
 * The rule is "newest device wins": once the owner logs in again they are back
 * under the cap and the oldest (intruder's) session is dropped. This matters
 * more here than in most apps because two-factor authentication is optional —
 * the session cap is one of the few automatic defences a password-only account
 * has against a shared credential.
 */
final class EnforceConcurrentSessionLimit
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $limit = (int) config('auth.max_concurrent_sessions', 0);

        // 0 = disabled (previous behaviour). The count lives in the `sessions`
        // table, which only exists under the database driver — with any other
        // driver there is nothing to count, so enforce nothing rather than
        // give a false sense of a limit that isn't really applied.
        if ($limit <= 0 || config('session.driver') !== 'database') {
            return;
        }

        $userId = $event->user->getAuthIdentifier();

        /*
         | This fires DURING authentication, before the current request's own
         | session row is written (that happens when the response is sent). So
         | to land at exactly $limit sessions afterwards, keep the $limit - 1
         | most-recently-active existing rows and drop the rest; the row being
         | created now becomes the $limit-th.
         */
        $keep = $this->mostRecentSessionIds($userId, $limit - 1);

        $deleted = DB::table('sessions')
            ->where('user_id', $userId)
            ->when($keep !== [], fn ($q) => $q->whereNotIn('id', $keep))
            ->delete();

        if ($deleted === 0) {
            return;
        }

        // A dropped device could otherwise re-authenticate from its remember-me
        // cookie and reappear over the cap — cycling the token invalidates every
        // outstanding cookie. The current browser stays in via its session, not
        // its cookie, so the owner is not logged out of the device they just
        // used.
        $event->user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        activity('security')
            ->performedOn($event->user)
            ->causedBy($event->user)
            ->withProperties(['evicted_sessions' => $deleted, 'limit' => $limit])
            ->log('user.session_limit_enforced');
    }

    /**
     * @return list<string> ids of the $count most-recently-active sessions
     */
    private function mostRecentSessionIds(int|string $userId, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->limit($count)
            ->pluck('id')
            ->all();
    }
}
