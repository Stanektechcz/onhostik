<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The web sessions a person has open — one row per signed-in browser (TASK-0070, audit 2026-10 package C11).
 *
 * Production keeps sessions in Redis: there is no list of a person's sessions to show, and nothing to delete when the person wants
 * one of them gone. So every web session carries an id of ours (`SESSION_KEY`), and this table says when it signed in, from where,
 * when it was last used and whether it was ended. WebSessionGate asks the row on every request the web guard serves: a session
 * whose row is ended is logged out at once. The row is the truth — a cache flush must not revive what was ended (TASK-0067).
 *
 * The id is not the framework's session id and gives nothing to whoever sees it: it only names the row.
 */
final class WebSessions
{
    public const SESSION_KEY = 'onhost_web_session';

    public const SIGNED_OUT = 'signed_out';

    public const ENDED_BY_USER = 'ended_by_user';

    public const ENDED_ALL = 'ended_all';

    public const PASSWORD_CHANGED = 'password_changed';

    /** A session in use is touched at most this often (one write per person and five minutes, not one per request). */
    private const TOUCH_SECONDS = 300;

    /** Rows of a person's sessions older than this are dropped when a new one opens. */
    private const KEEP_DAYS = 90;

    /** Opens the row of a session that signed in now; returns its id. */
    public function open(string $userId, ?string $ip, ?string $userAgent): string
    {
        $now = CarbonImmutable::now();
        DB::table('web_sessions')->where('user_id', $userId)->where('last_seen_at', '<', $now->subDays(self::KEEP_DAYS))->delete();
        $id = 'ws_'.strtolower((string) Str::ulid());
        DB::table('web_sessions')->insert([
            'id' => $id, 'user_id' => $userId, 'ip' => $ip === null ? null : mb_substr($ip, 0, 45), 'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 250),
            'signed_in_at' => $now, 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    }

    public const ALIVE = 'alive';

    public const ENDED = 'ended';

    public const UNKNOWN = 'unknown';

    /**
     * What the row `$id` says about a session of `$userId`: ENDED (log it out), ALIVE (go on; touched now and then, with the
     * address it is used from) or UNKNOWN — no row, or the row of somebody else. The id lives in the server-side session, never
     * in the cookie, so UNKNOWN is not an attack but a session that changed hands without a sign-in (a test acting as another
     * person) or whose row was pruned: it gets a row of its own and touches nobody else's.
     */
    public function state(string $id, string $userId, ?string $ip): string
    {
        $row = DB::table('web_sessions')->where('id', $id)->first(['user_id', 'ended_at', 'last_seen_at', 'ip']);
        if ($row === null || (string) $row->user_id !== $userId) {
            return self::UNKNOWN;
        }
        if ($row->ended_at !== null) {
            return self::ENDED;
        }
        $now = CarbonImmutable::now();
        if (CarbonImmutable::parse((string) $row->last_seen_at)->addSeconds(self::TOUCH_SECONDS)->isBefore($now) || ($ip !== null && $ip !== $row->ip)) {
            DB::table('web_sessions')->where('id', $id)->update(['last_seen_at' => $now, 'ip' => $ip === null ? $row->ip : mb_substr($ip, 0, 45), 'updated_at' => $now]);
        }

        return self::ALIVE;
    }

    /**
     * The person's sessions that are still open: not ended and used within the session lifetime (an abandoned browser drops off
     * the list by itself). Newest use first.
     *
     * @return list<array{id:string, ip:?string, user_agent:?string, signed_in_at:string, last_seen_at:string, current:bool}>
     */
    public function listOpen(string $userId, ?string $currentId): array
    {
        $since = CarbonImmutable::now()->subMinutes(max(1, (int) config('session.lifetime', 120)));

        return DB::table('web_sessions')->where('user_id', $userId)->whereNull('ended_at')
            ->where(fn ($q) => $q->where('last_seen_at', '>=', $since)->when($currentId !== null, fn ($q) => $q->orWhere('id', $currentId)))
            ->orderByDesc('last_seen_at')->orderByDesc('id')->limit(100)->get()
            ->map(fn (object $row) => [
                'id' => (string) $row->id, 'ip' => $row->ip, 'user_agent' => $row->user_agent,
                'signed_in_at' => CarbonImmutable::parse((string) $row->signed_in_at)->toIso8601String(),
                'last_seen_at' => CarbonImmutable::parse((string) $row->last_seen_at)->toIso8601String(),
                'current' => $currentId !== null && (string) $row->id === $currentId,
            ])->values()->all();
    }

    /** The row `$id` when it is an open session of `$userId`; null otherwise (unknown, ended, or another person's). */
    public function findOpen(string $id, string $userId): ?object
    {
        return DB::table('web_sessions')->where('id', $id)->where('user_id', $userId)->whereNull('ended_at')->first();
    }

    /** Ends one session; true when it was open. */
    public function end(string $id, string $userId, string $reason): bool
    {
        return $this->close(DB::table('web_sessions')->where('id', $id)->where('user_id', $userId), $reason) > 0;
    }

    /** Ends every open session of the person but `$keepId` (null: every one); returns how many. */
    public function endAllBut(string $userId, ?string $keepId, string $reason): int
    {
        return $this->close(DB::table('web_sessions')->where('user_id', $userId)->when($keepId !== null, fn ($q) => $q->where('id', '!=', $keepId)), $reason);
    }

    private function close(Builder $rows, string $reason): int
    {
        $now = CarbonImmutable::now();

        return $rows->whereNull('ended_at')->update(['ended_at' => $now, 'ended_reason' => mb_substr($reason, 0, 40), 'updated_at' => $now]);
    }
}
