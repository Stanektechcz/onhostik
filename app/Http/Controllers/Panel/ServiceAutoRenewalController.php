<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceAutoRenewalController extends Controller
{
    public function update(Request $request, Service $service): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($service->customer_id !== $customer?->id, 403);

        $validated = $request->validate([
            'auto_renew'          => ['required', 'boolean'],
            'renewal_notice_days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $service->update($validated);

        return back()->with('status', $validated['auto_renew']
            ? 'Automatická obnova zapnuta.'
            : 'Automatická obnova vypnuta.');
    }
}
