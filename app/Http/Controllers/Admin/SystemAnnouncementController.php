<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Communication\Models\SystemAnnouncement;
use App\Domains\Communication\Services\AnnouncementService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SystemAnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcementService) {}

    public function index(): View
    {
        $announcements = SystemAnnouncement::with('creator')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        return view('admin.announcements.form', ['announcement' => new SystemAnnouncement()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'body'           => 'required|string',
            'type'           => 'required|in:info,warning,maintenance,feature',
            'icon'           => 'nullable|string|max:50',
            'send_email'     => 'boolean',
            'expires_at'     => 'nullable|date|after:now',
            'scheduled_at'   => 'nullable|date|after:now',
            'target_segment' => 'nullable|in:vip,healthy,at_risk,churned',
        ]);

        $data['send_email'] = $request->boolean('send_email');
        $data['created_by'] = $request->user()->id;

        SystemAnnouncement::create($data);

        return redirect()->route('admin.announcements.index')->with('status', 'Oznámení vytvořeno.');
    }

    public function edit(SystemAnnouncement $announcement): View
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, SystemAnnouncement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'body'           => 'required|string',
            'type'           => 'required|in:info,warning,maintenance,feature',
            'icon'           => 'nullable|string|max:50',
            'send_email'     => 'boolean',
            'expires_at'     => 'nullable|date',
            'scheduled_at'   => 'nullable|date',
            'target_segment' => 'nullable|in:vip,healthy,at_risk,churned',
        ]);

        $data['send_email'] = $request->boolean('send_email');
        $announcement->update($data);

        return redirect()->route('admin.announcements.index')->with('status', 'Oznámení aktualizováno.');
    }

    public function publish(SystemAnnouncement $announcement): RedirectResponse
    {
        $count = $this->announcementService->broadcast($announcement);

        return redirect()->route('admin.announcements.index')
            ->with('status', "Oznámení odesláno {$count} zákazníkům.");
    }

    public function destroy(SystemAnnouncement $announcement): RedirectResponse
    {
        $announcement->delete();
        return redirect()->route('admin.announcements.index')->with('status', 'Oznámení smazáno.');
    }
}
