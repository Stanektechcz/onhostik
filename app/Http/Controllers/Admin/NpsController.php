<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NpsResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class NpsController extends Controller
{
    public function index(): View
    {
        $submitted = NpsResponse::whereNotNull('submitted_at');

        $total      = (clone $submitted)->count();
        $promoters  = (clone $submitted)->where('score', '>=', 9)->count();
        $passives   = (clone $submitted)->whereBetween('score', [7, 8])->count();
        $detractors = (clone $submitted)->where('score', '<=', 6)->count();

        $npsScore = $total > 0
            ? round(($promoters / $total - $detractors / $total) * 100)
            : null;

        $avgScore = $total > 0
            ? round((float) NpsResponse::whereNotNull('submitted_at')->avg('score'), 1)
            : null;

        $trend = DB::table('nps_responses')
            ->whereNotNull('submitted_at')
            ->selectRaw("strftime('%Y-%m', submitted_at) as month, ROUND(AVG(score), 1) as avg_score, COUNT(*) as count")
            ->where('submitted_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $recent = NpsResponse::with('customer.user', 'ticket')
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->limit(30)
            ->get();

        $sentCount = NpsResponse::count();

        return view('admin.nps.index', compact(
            'total', 'promoters', 'passives', 'detractors',
            'npsScore', 'avgScore', 'trend', 'recent', 'sentCount',
        ));
    }
}
