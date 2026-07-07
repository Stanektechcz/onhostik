<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DomainTransferRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainTransferStatisticsController extends Controller
{
    public function index(Request $request): View
    {
        $statusStats = DomainTransferRequest::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $totalRequests = DomainTransferRequest::count();

        $recentRequests = DomainTransferRequest::with(['customer'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.domain-transfer-statistics.index', compact(
            'statusStats',
            'totalRequests',
            'recentRequests',
        ));
    }
}
