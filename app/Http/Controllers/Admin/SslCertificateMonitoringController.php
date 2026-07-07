<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SslCertificateCheck;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SslCertificateMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $statusStats = SslCertificateCheck::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $expiringCount = SslCertificateCheck::where('status', 'expiring_soon')->count();
        $expiredCount  = SslCertificateCheck::where('status', 'expired')->count();

        $recentChecks = SslCertificateCheck::with(['service'])
            ->orderByDesc('checked_at')
            ->limit(20)
            ->get();

        return view('admin.ssl-certificate-monitoring.index', compact(
            'statusStats',
            'expiringCount',
            'expiredCount',
            'recentChecks',
        ));
    }
}
