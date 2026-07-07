<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\PortalAnnouncement;
use Illuminate\Contracts\View\View;

class PortalAnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = PortalAnnouncement::where('is_published', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('target_audience', ['all', 'customers'])
            ->orderByDesc('published_at')
            ->paginate(10);

        return view('panel.portal-announcements.index', compact('announcements'));
    }
}
