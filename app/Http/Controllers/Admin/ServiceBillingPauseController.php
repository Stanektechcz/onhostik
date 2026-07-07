<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceBillingPauseController extends Controller
{
    public function index(): View
    {
        $pending = Service::with('customer')
            ->whereNotNull('billing_pause_requested_at')
            ->orderBy('billing_pause_requested_at')
            ->get();

        return view('admin.service-billing-pause', compact('pending'));
    }

    public function approve(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'paused_until' => ['required', 'date', 'after:today'],
        ]);

        $service->update([
            'billing_paused_until'       => $validated['paused_until'],
            'billing_pause_requested_at' => null,
        ]);

        return back()->with('status', 'Pozastavení fakturace schváleno do ' . $validated['paused_until'] . '.');
    }

    public function reject(Service $service): RedirectResponse
    {
        $service->update(['billing_pause_requested_at' => null]);

        return back()->with('status', 'Žádost o pozastavení zamítnuta.');
    }
}
