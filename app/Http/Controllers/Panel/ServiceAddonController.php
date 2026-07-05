<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceAddon;
use App\Domains\Provisioning\Models\ServiceAddonSubscription;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceAddonController extends Controller
{
    public function index(Service $service): View
    {
        $this->authorizeService($service);

        $subscriptions = $service->addonSubscriptions()
            ->with('addon')
            ->whereNull('cancelled_at')
            ->get();

        $subscribed = $subscriptions->pluck('service_addon_id')->toArray();

        $available = ServiceAddon::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('panel.services.addons', [
            'service'       => $service,
            'available'     => $available,
            'subscriptions' => $subscriptions,
            'subscribed'    => $subscribed,
        ]);
    }

    public function activate(Request $request, Service $service): RedirectResponse
    {
        $this->authorizeService($service);

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['addon' => 'Doplňky lze přidat pouze k aktivním službám.']);
        }

        $validated = $request->validate([
            'addon_id' => ['required', 'integer', 'exists:service_addons,id'],
        ]);

        $addon = ServiceAddon::findOrFail($validated['addon_id']);

        if (! $addon->is_active) {
            return back()->withErrors(['addon' => 'Tento doplněk není dostupný.']);
        }

        $existing = ServiceAddonSubscription::where('service_id', $service->id)
            ->where('service_addon_id', $addon->id)
            ->whereNull('cancelled_at')
            ->first();

        if ($existing) {
            return back()->withErrors(['addon' => 'Tento doplněk je již aktivován.']);
        }

        ServiceAddonSubscription::updateOrCreate(
            ['service_id' => $service->id, 'service_addon_id' => $addon->id],
            ['activated_at' => now(), 'cancelled_at' => null, 'quantity' => 1]
        );

        return back()->with('status', "Doplněk \"{$addon->name}\" byl aktivován.");
    }

    public function cancel(Service $service, ServiceAddonSubscription $subscription): RedirectResponse
    {
        $this->authorizeService($service);

        if ($subscription->service_id !== $service->id) {
            abort(403);
        }

        $subscription->update(['cancelled_at' => now()]);

        return back()->with('status', 'Doplněk byl zrušen.');
    }

    private function authorizeService(Service $service): void
    {
        /** @var \App\Models\User $user */
        $user = request()->user();

        if ($service->customer_id !== $user->customer?->id) {
            abort(403);
        }
    }
}
