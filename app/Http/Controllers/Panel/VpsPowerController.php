<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\VpsPowerActionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer-triggered VPS power actions (Proxmox only). The queued job is
 * task-tracked and the ProxmoxClient stays simulated while the provider
 * is in mock mode — no real HTTP without an explicit production cutover.
 */
class VpsPowerController extends Controller
{
    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        abort_unless($service->provisioning_driver === ProvisioningDriver::Proxmox, 404);

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['vps' => 'Akce napájení jsou dostupné jen pro aktivní služby.']);
        }

        $validated = $request->validate([
            'action' => 'required|in:start,stop,restart',
        ]);

        VpsPowerActionJob::dispatch($service->id, $validated['action'], $request->user()->id);

        activity('service')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['action' => $validated['action']])
            ->log('service.vps_action_requested');

        $labels = ['start' => 'spuštění', 'stop' => 'vypnutí', 'restart' => 'restart'];

        return back()->with('status', 'Požadavek na ' . $labels[$validated['action']] . ' VPS byl zařazen.');
    }
}
