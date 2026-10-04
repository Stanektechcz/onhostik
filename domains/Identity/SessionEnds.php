<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * When a person's web sessions or consoles were last ended — kept in the database, the cache only a fast path (TASK-0067).
 *
 * TASK-0044 wrote the marks of SessionKill (WebSessionGate::endAll, ConsoleSessions::endFor) to the cache alone. A cache flush (a
 * Redis restart, an eviction under memory pressure, `cache:clear` on a deploy) or the end of the mark's TTL quietly revived every
 * web session and console the kill had ended — the account still in doubt, the session working again. Now the row in
 * `session_ends` is the mark; the cache holds a copy of a person's marks of one kind (also when there are none, so the per-request
 * check of a person never ended costs no query), refreshed on every write and loaded from the database whenever it is missing.
 *
 * A mark has a scope: `*` (everywhere) or one organization (a console kill after a removal from that organization). A later end
 * of the same scope moves it forward; nothing ever moves it back.
 */
final class SessionEnds
{
    public const WEB = 'web';

    public const CONSOLE = 'console';

    public const EVERYWHERE = '*';

    /** How long the copy in the cache lives; the database answers after that (or after any flush). */
    private const CACHED_SECONDS = 24 * 3600;

    /** Ends what `$userId` has of `$kind` until now — everywhere, or in `$organizationId` only. */
    public function mark(string $userId, string $kind, ?string $organizationId = null): CarbonImmutable
    {
        $at = CarbonImmutable::now()->startOfSecond();
        DB::table('session_ends')->upsert(
            [['user_id' => $userId, 'kind' => $kind, 'scope' => $organizationId ?? self::EVERYWHERE, 'ended_at' => $at, 'created_at' => $at, 'updated_at' => $at]],
            ['user_id', 'kind', 'scope'],
            ['ended_at', 'updated_at'],
        );
        Cache::put(self::key($userId, $kind), $this->load($userId, $kind), self::CACHED_SECONDS);

        return $at;
    }

    /** The latest end of `$kind` that reaches `$organizationId` (an everywhere end always does); null when there was none. */
    public function endedAt(string $userId, string $kind, ?string $organizationId = null): ?CarbonImmutable
    {
        $marks = $this->marks($userId, $kind);
        $latest = null;
        foreach (array_filter([$marks[self::EVERYWHERE] ?? null, $organizationId !== null ? ($marks[$organizationId] ?? null) : null]) as $at) {
            $parsed = CarbonImmutable::parse((string) $at);
            $latest = $latest === null || $parsed->isAfter($latest) ? $parsed : $latest;
        }

        return $latest;
    }

    /** @return array<string, string> scope => ISO time of the end */
    private function marks(string $userId, string $kind): array
    {
        $cached = Cache::get(self::key($userId, $kind));
        if (is_array($cached)) {
            return $cached;
        }
        $marks = $this->load($userId, $kind);
        Cache::put(self::key($userId, $kind), $marks, self::CACHED_SECONDS);

        return $marks;
    }

    /** @return array<string, string> */
    private function load(string $userId, string $kind): array
    {
        return DB::table('session_ends')->where('user_id', $userId)->where('kind', $kind)->get(['scope', 'ended_at'])
            ->mapWithKeys(fn (object $row) => [(string) $row->scope => CarbonImmutable::parse((string) $row->ended_at)->toIso8601String()])->all();
    }

    private static function key(string $userId, string $kind): string
    {
        return "onhost:session-ends:{$kind}:{$userId}";
    }
}
