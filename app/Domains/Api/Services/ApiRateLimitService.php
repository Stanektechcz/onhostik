<?php

declare(strict_types=1);

namespace App\Domains\Api\Services;

use App\Domains\Api\Models\ApiTokenRateLimit;
use App\Domains\Api\Models\ApiUsageLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ApiRateLimitService
{
    /** Default limits applied to tokens without explicit configuration. */
    private const DEFAULTS = [
        'requests_per_minute' => 30,
        'requests_per_hour'   => 500,
        'requests_per_day'    => 5000,
    ];

    /**
     * Returns current usage for a specific token across time windows.
     *
     * @return array{minute: int, hour: int, day: int}
     */
    public function usageForToken(int $tokenId): array
    {
        $minute = ApiUsageLog::where('token_id', $tokenId)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        $hour = ApiUsageLog::where('token_id', $tokenId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $day = ApiUsageLog::where('token_id', $tokenId)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return compact('minute', 'hour', 'day');
    }

    /**
     * Returns limits for a token (either configured or defaults).
     *
     * @return array{requests_per_minute: int, requests_per_hour: int, requests_per_day: int}
     */
    public function limitsForToken(int $tokenId): array
    {
        $configured = ApiTokenRateLimit::where('token_id', $tokenId)->first();

        if ($configured === null) {
            return self::DEFAULTS;
        }

        return [
            'requests_per_minute' => $configured->requests_per_minute,
            'requests_per_hour'   => $configured->requests_per_hour,
            'requests_per_day'    => $configured->requests_per_day,
        ];
    }

    /**
     * Returns a percentage-used summary for a token across all time windows.
     *
     * @return array{minute_pct: float, hour_pct: float, day_pct: float, is_exceeded: bool}
     */
    public function statusForToken(int $tokenId): array
    {
        $usage  = $this->usageForToken($tokenId);
        $limits = $this->limitsForToken($tokenId);

        $minutePct = $limits['requests_per_minute'] > 0
            ? round($usage['minute'] / $limits['requests_per_minute'] * 100, 1)
            : 0.0;

        $hourPct = $limits['requests_per_hour'] > 0
            ? round($usage['hour'] / $limits['requests_per_hour'] * 100, 1)
            : 0.0;

        $dayPct = $limits['requests_per_day'] > 0
            ? round($usage['day'] / $limits['requests_per_day'] * 100, 1)
            : 0.0;

        return [
            'minute_pct'  => $minutePct,
            'hour_pct'    => $hourPct,
            'day_pct'     => $dayPct,
            'is_exceeded' => $minutePct >= 100 || $hourPct >= 100 || $dayPct >= 100,
        ];
    }

    /**
     * Returns active tokens with their rate limit status.
     * Joins personal_access_tokens with api_usage_logs to find recently active tokens.
     *
     * @return Collection<int, object>
     */
    public function activeTokenStatus(): Collection
    {
        $activeTokenIds = DB::table('api_usage_logs')
            ->select('token_id')
            ->whereNotNull('token_id')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('token_id')
            ->pluck('token_id');

        return $activeTokenIds->map(function (int $tokenId): object {
            $token = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
            if ($token === null) {
                return (object) [];
            }

            $usage  = $this->usageForToken($tokenId);
            $limits = $this->limitsForToken($tokenId);
            $status = $this->statusForToken($tokenId);

            return (object) [
                'token_id'   => $tokenId,
                'token_name' => $token->name,
                'user_id'    => $token->tokenable_id,
                'usage'      => $usage,
                'limits'     => $limits,
                'status'     => $status,
            ];
        })->filter(fn (object $t): bool => isset($t->token_id));
    }

    /**
     * Save or update rate limit configuration for a token.
     *
     * @param array{requests_per_minute: int, requests_per_hour: int, requests_per_day: int} $limits
     */
    public function setLimits(int $tokenId, array $limits): ApiTokenRateLimit
    {
        /** @var ApiTokenRateLimit $record */
        $record = ApiTokenRateLimit::updateOrCreate(
            ['token_id' => $tokenId],
            [
                'requests_per_minute' => $limits['requests_per_minute'],
                'requests_per_hour'   => $limits['requests_per_hour'],
                'requests_per_day'    => $limits['requests_per_day'],
                'is_active'           => true,
            ]
        );

        return $record;
    }

    /**
     * Summary stats: how many tokens are over any limit in the last 24h.
     *
     * @return array{active_tokens: int, exceeded_tokens: int, near_limit_tokens: int}
     */
    public function summary(): array
    {
        $statuses = $this->activeTokenStatus();

        $exceeded   = $statuses->filter(fn (object $t): bool => $t->status['is_exceeded'])->count();
        $nearLimit  = $statuses->filter(fn (object $t): bool =>
            !$t->status['is_exceeded']
            && ($t->status['minute_pct'] >= 75 || $t->status['hour_pct'] >= 75 || $t->status['day_pct'] >= 75)
        )->count();

        return [
            'active_tokens'    => $statuses->count(),
            'exceeded_tokens'  => $exceeded,
            'near_limit_tokens' => $nearLimit,
        ];
    }
}
