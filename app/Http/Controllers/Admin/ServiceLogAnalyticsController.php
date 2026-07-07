<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceLogAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $levelStats = ServiceLog::selectRaw('level, count(*) as count')
            ->groupBy('level')
            ->get()
            ->keyBy('level');

        $topErrorServices = ServiceLog::selectRaw('service_id, count(*) as error_count')
            ->where('level', 'error')
            ->groupBy('service_id')
            ->orderByDesc('error_count')
            ->limit(10)
            ->get();

        $recentErrors = ServiceLog::where('level', 'error')
            ->with(['service'])
            ->orderByDesc('logged_at')
            ->limit(20)
            ->get();

        return view('admin.service-log-analytics.index', compact(
            'levelStats',
            'topErrorServices',
            'recentErrors',
        ));
    }
}
