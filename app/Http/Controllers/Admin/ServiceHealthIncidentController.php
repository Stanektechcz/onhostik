<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceHealthIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceHealthIncidentController extends Controller
{
    public function index(Request $request): View
    {
        $serviceId = $request->query('service_id');
        $status    = $request->query('status');

        $incidents = ServiceHealthIncident::when($serviceId, fn($q) => $q->where('service_id', $serviceId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.service-health-incidents.index', compact('incidents', 'serviceId', 'status'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id'  => 'required|integer',
            'severity'    => 'required|in:info,warning,critical',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:open,investigating,resolved',
        ]);

        $validated['created_by'] = $request->user()->id;

        if ($validated['status'] === 'resolved') {
            $validated['resolved_at'] = now();
        }

        ServiceHealthIncident::create($validated);

        return back()->with('status', 'Incident vytvořen.');
    }

    public function update(Request $request, ServiceHealthIncident $serviceHealthIncident): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,investigating,resolved',
        ]);

        $update = ['status' => $validated['status']];

        if ($validated['status'] === 'resolved' && $serviceHealthIncident->resolved_at === null) {
            $update['resolved_at'] = now();
        }

        $serviceHealthIncident->update($update);

        return back()->with('status', 'Incident aktualizován.');
    }
}
