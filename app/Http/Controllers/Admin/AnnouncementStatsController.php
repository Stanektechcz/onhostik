<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Communication\Models\SystemAnnouncement;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnnouncementStatsController extends Controller
{
    public function index(): View
    {
        $announcements = SystemAnnouncement::withCount('dismissedBy')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.announcement-stats.index', compact('announcements'));
    }

    public function show(SystemAnnouncement $announcement): View
    {
        $dismissals = $announcement->dismissedBy()
            ->withPivot('dismissed_at')
            ->orderByDesc('announcement_dismissals.dismissed_at')
            ->paginate(50);

        $hourlyStats = DB::table('announcement_dismissals')
            ->where('announcement_id', $announcement->id)
            ->selectRaw("strftime('%Y-%m-%d %H', dismissed_at) as hour, COUNT(*) as cnt")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return view('admin.announcement-stats.show', compact('announcement', 'dismissals', 'hourlyStats'));
    }
}
