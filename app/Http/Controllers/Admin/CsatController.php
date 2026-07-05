<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Models\TicketRating;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class CsatController extends Controller
{
    public function index(): View
    {
        $avgScore = round((float) TicketRating::avg('score'), 2);
        $total    = TicketRating::count();

        $distribution = DB::table('ticket_ratings')
            ->select('score', DB::raw('COUNT(*) as count'))
            ->groupBy('score')
            ->orderByDesc('score')
            ->get()
            ->keyBy('score')
            ->map(fn ($row) => (int) $row->count);

        $scoreDist = collect([5, 4, 3, 2, 1])->mapWithKeys(
            fn ($s) => [$s => $distribution->get($s, 0)]
        );

        $trend = DB::table('ticket_ratings')
            ->selectRaw("strftime('%Y-%m', created_at) as month, ROUND(AVG(score), 2) as avg_score, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $recent = TicketRating::with('ticket.customer.user')
            ->orderByDesc('rated_at')
            ->limit(20)
            ->get();

        return view('admin.support.csat', [
            'avgScore'    => $avgScore,
            'total'       => $total,
            'scoreDist'   => $scoreDist,
            'trend'       => $trend,
            'recent'      => $recent,
        ]);
    }
}
