<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceWindowController extends Controller
{
    public function index(Request $request): View
    {
        $status  = $request->query('status');
        $windows = MaintenanceWindow::when($status, fn($q) => $q->where('status', $status))
            ->orderBy('starts_at')
            ->paginate(15);
        return view('admin.maintenance-windows.index', compact('windows', 'status'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'server_id'        => 'nullable|integer',
            'starts_at'        => 'required|date',
            'ends_at'          => 'required|date|after:starts_at',
            'notify_customers' => 'boolean',
        ]);
        $validated['notify_customers'] = $request->boolean('notify_customers');
        $validated['status']           = 'scheduled';
        $validated['created_by']       = $request->user()->id;
        $validated['message']          = $validated['description'] ?? '';
        $validated['color']            = 'info';
        $validated['show_on_frontend'] = false;
        $validated['show_on_admin']    = true;
        MaintenanceWindow::create($validated);
        return back()->with('status', 'Okno údržby naplánováno.');
    }

    public function update(Request $request, MaintenanceWindow $maintenanceWindow): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
        ]);
        $maintenanceWindow->update($validated);
        return back()->with('status', 'Status okna údržby aktualizován.');
    }

    public function destroy(MaintenanceWindow $maintenanceWindow): RedirectResponse
    {
        $maintenanceWindow->delete();
        return back()->with('status', 'Okno údržby odstraněno.');
    }
}
