<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\GameServerActionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer-triggered game server actions (Pterodactyl only). Queued,
 * task-tracked, simulated while the provider is mocked.
 */
class GameServerActionController extends Controller
{
    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        abort_unless($service->provisioning_driver === ProvisioningDriver::Pterodactyl, 404);

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['game' => 'Akce jsou dostupné jen pro aktivní služby.']);
        }

        $validated = $request->validate([
            'action' => 'required|in:reinstall',
        ]);

        GameServerActionJob::dispatch($service->id, $validated['action'], $request->user()->id);

        activity('service')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['action' => $validated['action']])
            ->log('service.game_action_requested');

        return back()->with('status', 'Požadavek na reinstalaci game serveru byl zařazen.');
    }
}
