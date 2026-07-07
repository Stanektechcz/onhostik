<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Api\Models\ApiUsageLog;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

class ApiUsageAnalyticsController extends Controller
{
    public function index(): View
    {
        $since7d = now()->subDays(7);
        $today   = now()->startOfDay();

        $totalRequests = ApiUsageLog::count();
        $requestsToday = ApiUsageLog::where('created_at', '>=', $today)->count();
        $requests7d    = ApiUsageLog::where('created_at', '>=', $since7d)->count();
        $errorCount    = ApiUsageLog::where('status_code', '>=', 400)->count();
        $avgResponseMs = (int) round((float) ApiUsageLog::average('response_time_ms'));

        $errorRate = $totalRequests > 0
            ? round(($errorCount / $totalRequests) * 100, 1)
            : 0.0;

        // Top 10 endpoints by volume (last 7 days)
        $topEndpoints = ApiUsageLog::query()
            ->selectRaw('endpoint, method, COUNT(*) as total,
                         ROUND(AVG(response_time_ms)) as avg_ms,
                         SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->where('created_at', '>=', $since7d)
            ->groupBy('endpoint', 'method')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Top 10 consumers by volume (last 7 days)
        $consumerRows = ApiUsageLog::query()
            ->selectRaw('user_id, COUNT(*) as total,
                         SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->where('created_at', '>=', $since7d)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $userMap = User::query()
            ->whereIn('id', $consumerRows->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $topConsumers = $consumerRows;

        // Daily volume for the last 7 days (for a mini sparkline display)
        $dailyVolume = ApiUsageLog::query()
            ->selectRaw("DATE(created_at) as day, COUNT(*) as total")
            ->where('created_at', '>=', $since7d)
            ->groupByRaw("DATE(created_at)")
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        return view('admin.api-analytics', compact(
            'totalRequests',
            'requestsToday',
            'requests7d',
            'errorCount',
            'errorRate',
            'avgResponseMs',
            'topEndpoints',
            'topConsumers',
            'userMap',
            'dailyVolume'
        ));
    }
}
