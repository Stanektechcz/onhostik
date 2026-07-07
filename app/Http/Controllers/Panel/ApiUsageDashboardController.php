<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Api\Models\ApiUsageLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApiUsageDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $totalRequests  = ApiUsageLog::where('user_id', $user->id)->count();
        $requestsToday  = ApiUsageLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        $requests7d     = ApiUsageLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->count();
        $errorCount     = ApiUsageLog::where('user_id', $user->id)
            ->where('status_code', '>=', 400)
            ->count();

        $dayExpr = config('database.default') === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : 'DATE(created_at)';

        $dailyVolume = \Illuminate\Support\Facades\DB::table('api_usage_logs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("{$dayExpr} as day, COUNT(*) as cnt")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $days = collect();
        for ($i = 29; $i >= 0; $i--) {
            $day   = now()->subDays($i)->toDateString();
            $row   = $dailyVolume->get($day);
            $days->push(['day' => $day, 'cnt' => $row !== null ? (int) $row->cnt : 0]);
        }

        return view('panel.api.usage-dashboard', compact(
            'totalRequests', 'requestsToday', 'requests7d', 'errorCount', 'days'
        ));
    }
}
