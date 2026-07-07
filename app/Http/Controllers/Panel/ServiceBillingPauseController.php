<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceBillingPauseController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($service->customer_id !== $customer?->id, 403);
        abort_if($service->billing_pause_requested_at !== null, 422, 'Žádost již existuje.');

        $service->update(['billing_pause_requested_at' => now()]);

        return back()->with('status', 'Žádost o pozastavení fakturace byla odeslána ke schválení.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($service->customer_id !== $customer?->id, 403);

        $service->update([
            'billing_pause_requested_at' => null,
            'billing_paused_until'       => null,
        ]);

        return back()->with('status', 'Žádost o pozastavení fakturace zrušena.');
    }
}
