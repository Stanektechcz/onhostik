<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Jobs\WebhostingPhpVersionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin PHP version change for aaPanel webhosting services. */
class ServicePhpVersionController extends Controller
{
    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->provisioning_driver === ProvisioningDriver::AAPanel, 404);

        $validated = $request->validate([
            'php_version' => 'required|in:' . implode(',', array_keys(WebhostingPhpVersionJob::VERSIONS)),
        ]);

        WebhostingPhpVersionJob::dispatch($service->id, $validated['php_version'], $request->user()->id);

        activity('provisioning')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['php_version' => $validated['php_version']])
            ->log('service.php_version_requested');

        return back()->with('status', 'Požadavek na změnu PHP verze byl zařazen.');
    }
}
