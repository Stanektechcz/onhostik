<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Api\Models\ApiUsageLog;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ApiUsageController extends Controller
{
    public function index(): View
    {
        $total     = ApiUsageLog::count();
        $errorRate = $total > 0
            ? round(ApiUsageLog::where('status_code', '>=', 400)->count() / $total * 100, 1)
            : 0.0;

        $avgResponse = round((float) ApiUsageLog::avg('response_time_ms'));

        $topEndpoints = DB::table('api_usage_logs')
            ->select('endpoint', 'method', DB::raw('COUNT(*) as hits'), DB::raw('ROUND(AVG(response_time_ms)) as avg_ms'))
            ->groupBy('endpoint', 'method')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        $topUsers = DB::table('api_usage_logs')
            ->join('users', 'users.id', '=', 'api_usage_logs.user_id')
            ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(*) as hits'), DB::raw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        $trend = DB::table('api_usage_logs')
            ->selectRaw("strftime('%Y-%m-%d', created_at) as day, COUNT(*) as hits, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors")
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $statusDist = DB::table('api_usage_logs')
            ->select('status_code', DB::raw('COUNT(*) as count'))
            ->groupBy('status_code')
            ->orderBy('status_code')
            ->get();

        $recent = ApiUsageLog::with('user')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('admin.api-usage', [
            'total'       => $total,
            'errorRate'   => $errorRate,
            'avgResponse' => $avgResponse,
            'topEndpoints' => $topEndpoints,
            'topUsers'    => $topUsers,
            'trend'       => $trend,
            'statusDist'  => $statusDist,
            'recent'      => $recent,
        ]);
    }
}
