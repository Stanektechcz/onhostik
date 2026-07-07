<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceUptimeCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceUptimeController extends Controller
{
    public function index(): View
    {
        $services = Service::withCount([
            'uptimeChecks as total_checks',
            'uptimeChecks as down_checks' => fn ($q) => $q->where('is_up', false)->where('checked_at', '>=', now()->subDay()),
        ])
        ->orderBy('label')
        ->get();

        return view('admin.service-uptime.index', compact('services'));
    }

    public function show(Service $service): View
    {
        $checks = ServiceUptimeCheck::where('service_id', $service->id)
            ->orderByDesc('checked_at')
            ->paginate(50);

        $uptime = ServiceUptimeCheck::where('service_id', $service->id)
            ->where('checked_at', '>=', now()->subDays(30))
            ->selectRaw('SUM(CASE WHEN is_up THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as uptime_pct')
            ->value('uptime_pct');

        return view('admin.service-uptime.show', compact('service', 'checks', 'uptime'));
    }

    public function store(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'check_type' => ['required', 'in:ping,http,tcp'],
            'target'     => ['required', 'string', 'max:255'],
            'is_up'      => ['required', 'boolean'],
            'response_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        ServiceUptimeCheck::create(array_merge($validated, [
            'service_id' => $service->id,
            'checked_at' => now(),
        ]));

        return back()->with('status', 'Check zaznamenán.');
    }
}
