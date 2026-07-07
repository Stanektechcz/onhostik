<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FailedAdminLogin;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FailedLoginMonitorController extends Controller
{
    public function index(): View
    {
        $recent = FailedAdminLogin::orderByDesc('attempted_at')->take(100)->get();

        $topIps = DB::table('failed_admin_logins')
            ->selectRaw('ip_address, COUNT(*) as attempts')
            ->groupBy('ip_address')
            ->orderByDesc('attempts')
            ->take(10)
            ->get();

        $topEmails = DB::table('failed_admin_logins')
            ->selectRaw('email, COUNT(*) as attempts')
            ->groupBy('email')
            ->orderByDesc('attempts')
            ->take(10)
            ->get();

        return view('admin.failed-logins.index', compact('recent', 'topIps', 'topEmails'));
    }
}
