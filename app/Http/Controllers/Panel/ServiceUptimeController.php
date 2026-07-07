<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceUptimeCheck;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceUptimeController extends Controller
{
    public function show(Request $request, Service $service): View
    {
        $customer = $request->user()->customer;
        abort_if($service->customer_id !== $customer?->id, 403);

        $checks = ServiceUptimeCheck::where('service_id', $service->id)
            ->orderByDesc('checked_at')
            ->paginate(50);

        $uptime = ServiceUptimeCheck::where('service_id', $service->id)
            ->where('checked_at', '>=', now()->subDays(30))
            ->selectRaw('SUM(CASE WHEN is_up THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) as uptime_pct')
            ->value('uptime_pct');

        return view('panel.services.uptime', compact('service', 'checks', 'uptime'));
    }
}
