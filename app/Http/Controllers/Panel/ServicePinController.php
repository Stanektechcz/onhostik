<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServicePin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ServicePinController extends Controller
{
    public function show(Request $request, Service $service): View
    {
        abort_unless($service->customer_id === $request->user()->customer?->id, 403);

        $pin = ServicePin::where('service_id', $service->id)->first();

        return view('panel.service-pins.show', compact('service', 'pin'));
    }

    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->customer_id === $request->user()->customer?->id, 403);

        $validated = $request->validate([
            'pin'              => 'required|digits_between:4,8',
            'pin_confirmation' => 'same:pin',
            'hint'             => 'nullable|string|max:100',
        ]);

        ServicePin::updateOrCreate(
            ['service_id' => $service->id],
            [
                'pin_hash' => Hash::make($request->pin),
                'hint'     => $validated['hint'] ?? null,
                'set_at'   => now(),
            ]
        );

        return back()->with('status', 'PIN nastaven.');
    }
}
