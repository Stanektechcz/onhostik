<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ActiveUsersController extends Controller
{
    public function index(): View
    {
        $isSqlite  = DB::getDriverName() === 'sqlite';
        $dayExpr   = $isSqlite ? "strftime('%Y-%m-%d', created_at)" : "DATE(created_at)";

        // DAU — distinct users per day for last 30 days
        $dauRaw = DB::table('user_login_history')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("{$dayExpr} as day, COUNT(DISTINCT user_id) as users")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $days = collect();
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $days->push([
                'day'   => $day,
                'users' => isset($dauRaw[$day]) ? (int) $dauRaw[$day]->users : 0,
            ]);
        }

        $dau = (int) ($dauRaw[now()->format('Y-m-d')]->users ?? 0);

        $wau = (int) DB::table('user_login_history')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->distinct('user_id')
            ->count('user_id');

        $mau = (int) DB::table('user_login_history')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->distinct('user_id')
            ->count('user_id');

        $maxDau = $days->max('users') ?: 1;

        return view('admin.active-users', compact('days', 'dau', 'wau', 'mau', 'maxDau'));
    }
}
