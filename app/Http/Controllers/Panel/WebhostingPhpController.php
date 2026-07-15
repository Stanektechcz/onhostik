<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\WebhostingPhpVersionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer-triggered PHP version change for aaPanel webhosting.
 * Queued, task-tracked, simulated while the provider is mocked.
 */
class WebhostingPhpController extends Controller
{
    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        abort_unless($service->provisioning_driver === ProvisioningDriver::AAPanel, 404);

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['php' => 'Změna PHP verze je dostupná jen pro aktivní služby.']);
        }

        $validated = $request->validate([
            'php_version' => 'required|in:' . implode(',', array_keys(WebhostingPhpVersionJob::VERSIONS)),
        ]);

        WebhostingPhpVersionJob::dispatch($service->id, $validated['php_version'], $request->user()->id);

        activity('service')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['php_version' => $validated['php_version']])
            ->log('service.php_version_requested');

        return back()->with('status', 'Požadavek na změnu PHP verze byl zařazen.');
    }
}
