<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceResourceSnapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceResourceSnapshotController extends Controller
{
    public function show(Request $request, Service $service): View
    {
        abort_unless($service->customer_id === $request->user()->customer?->id, 403);

        $snapshots = ServiceResourceSnapshot::where('service_id', $service->id)
            ->orderByDesc('recorded_at')
            ->paginate(30);

        return view('panel.service-snapshots.show', compact('service', 'snapshots'));
    }
}
