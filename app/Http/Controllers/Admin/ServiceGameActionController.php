<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Jobs\GameServerActionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin game server actions (Pterodactyl). */
class ServiceGameActionController extends Controller
{
    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->provisioning_driver === ProvisioningDriver::Pterodactyl, 404);

        $validated = $request->validate([
            'action' => 'required|in:reinstall',
        ]);

        GameServerActionJob::dispatch($service->id, $validated['action'], $request->user()->id);

        activity('provisioning')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['action' => $validated['action']])
            ->log('service.game_action_requested');

        return back()->with('status', 'Požadavek na reinstalaci game serveru byl zařazen.');
    }
}
