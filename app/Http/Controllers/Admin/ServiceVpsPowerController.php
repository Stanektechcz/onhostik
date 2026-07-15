<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Jobs\VpsPowerActionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin VPS power actions — unlike the customer variant this works for
 * suspended services too (an admin may need to stop a suspended VM).
 */
class ServiceVpsPowerController extends Controller
{
    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->provisioning_driver === ProvisioningDriver::Proxmox, 404);

        $validated = $request->validate([
            'action' => 'required|in:start,stop,restart',
        ]);

        VpsPowerActionJob::dispatch($service->id, $validated['action'], $request->user()->id);

        activity('provisioning')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['action' => $validated['action']])
            ->log('service.vps_action_requested');

        $labels = ['start' => 'spuštění', 'stop' => 'vypnutí', 'restart' => 'restart'];

        return back()->with('status', 'Požadavek na ' . $labels[$validated['action']] . ' VPS byl zařazen.');
    }
}
