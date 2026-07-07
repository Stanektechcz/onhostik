<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceConfigSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceConfigSnapshotController extends Controller
{
    public function index(Service $service): View
    {
        $snapshots = ServiceConfigSnapshot::where('service_id', $service->id)
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.service-config-snapshots', compact('service', 'snapshots'));
    }

    public function store(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        ServiceConfigSnapshot::create([
            'service_id' => $service->id,
            'created_by' => $request->user()->id,
            'config'     => $service->resources ?? [],
            'reason'     => $validated['reason'] ?? null,
        ]);

        return back()->with('status', 'Snapshot konfigurace uložen.');
    }
}
