<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceUsageAlertController extends Controller
{
    public function update(Request $request, Service $service): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($service->customer_id !== $customer?->id, 403);

        $validated = $request->validate([
            'usage_alert_threshold' => ['required', 'integer', 'min:50', 'max:100'],
        ]);

        $service->update(['usage_alert_threshold' => $validated['usage_alert_threshold']]);

        return back()->with('status', 'Práh upozornění nastaven na ' . $validated['usage_alert_threshold'] . '%.');
    }
}
