<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortalAnnouncement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalAnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = PortalAnnouncement::orderByDesc('created_at')->paginate(15);

        return view('admin.portal-announcements.index', compact('announcements'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'body'            => 'required|string',
            'type'            => 'required|in:info,warning,success,danger',
            'target_audience' => 'required|in:all,customers,resellers',
            'expires_at'      => 'nullable|date|after:now',
        ]);

        $validated['is_published'] = false;
        $validated['created_by']   = $request->user()->id;

        PortalAnnouncement::create($validated);

        return back()->with('status', 'Oznámení vytvořeno.');
    }

    public function update(Request $request, PortalAnnouncement $portalAnnouncement): RedirectResponse
    {
        $isPublished = ! $portalAnnouncement->is_published;

        $portalAnnouncement->update([
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : $portalAnnouncement->published_at,
        ]);

        return back()->with('status', 'Stav oznámení aktualizován.');
    }

    public function destroy(PortalAnnouncement $portalAnnouncement): RedirectResponse
    {
        $portalAnnouncement->delete();

        return back()->with('status', 'Oznámení odstraněno.');
    }
}
