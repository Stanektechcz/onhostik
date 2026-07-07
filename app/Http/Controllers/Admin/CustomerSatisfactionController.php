<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerSatisfactionController extends Controller
{
    public function index(): View
    {
        $overall = DB::table('support_tickets')
            ->whereNotNull('csat_score')
            ->selectRaw('AVG(csat_score) as avg_rating, COUNT(*) as rated_count, COUNT(CASE WHEN csat_score >= 4 THEN 1 END) as positive_count')
            ->first();

        $byRating = DB::table('support_tickets')
            ->whereNotNull('csat_score')
            ->selectRaw('csat_score as rating, COUNT(*) as cnt')
            ->groupBy('csat_score')
            ->orderBy('csat_score')
            ->get();

        $monthly = DB::table('support_tickets')
            ->whereNotNull('csat_score')
            ->whereNotNull('csat_rated_at')
            ->selectRaw("strftime('%Y-%m', csat_rated_at) as month, ROUND(AVG(csat_score), 2) as avg_rating, COUNT(*) as rated_count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $recentComments = DB::table('support_tickets')
            ->whereNotNull('csat_comment')
            ->whereNotNull('csat_score')
            ->orderByDesc('csat_rated_at')
            ->limit(20)
            ->get(['id', 'csat_score as rating', 'csat_comment as rating_comment', 'csat_rated_at as rated_at']);

        return view('admin.csat.index', compact('overall', 'byRating', 'monthly', 'recentComments'));
    }
}
