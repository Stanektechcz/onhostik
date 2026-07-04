<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Contracts\View\View;

class SecurityController extends Controller
{
    public function index(): View
    {
        $since24h = now()->subDay();

        $failedCount   = SecurityEvent::where('event_type', 'login_failed')
            ->where('created_at', '>=', $since24h)
            ->count();

        $suspiciousIps = SecurityEvent::where('event_type', 'login_failed')
            ->where('created_at', '>=', $since24h)
            ->distinct()
            ->count('ip_address');

        $newIpCount = SecurityEvent::where('event_type', 'new_ip_login')
            ->where('created_at', '>=', $since24h)
            ->count();

        $recentEvents = SecurityEvent::with('user:id,email,name')
            ->latest()
            ->limit(50)
            ->get();

        // Top attacking IPs (most failed logins in last 24h)
        $topAttackers = SecurityEvent::where('event_type', 'login_failed')
            ->where('created_at', '>=', $since24h)
            ->selectRaw('ip_address, COUNT(*) as attempts, MAX(email) as last_email')
            ->groupBy('ip_address')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        return view('admin.security', compact(
            'failedCount',
            'suspiciousIps',
            'newIpCount',
            'recentEvents',
            'topAttackers',
        ));
    }
}
