<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceResourceSnapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceResourceSnapshotController extends Controller
{
    public function index(Request $request): View
    {
        $query = ServiceResourceSnapshot::with('service.customer')
            ->orderByDesc('recorded_at');

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->input('service_id'));
        }

        $snapshots = $query->paginate(30);

        return view('admin.service-resource-snapshots.index', compact('snapshots'));
    }

    public function store(Request $request, Service $service): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'disk_gb'      => ['nullable', 'integer', 'min:0'],
            'bandwidth_gb' => ['nullable', 'integer', 'min:0'],
            'cpu_percent'  => ['nullable', 'integer', 'min:0', 'max:100'],
            'ram_mb'       => ['nullable', 'integer', 'min:0'],
        ]);

        ServiceResourceSnapshot::create([
            ...$validated,
            'service_id'  => $service->id,
            'recorded_at' => now(),
        ]);

        return back()->with('status', 'Snapshot zaznamenán.');
    }
}
