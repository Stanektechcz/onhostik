<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Marketplace\Models\AppInstallation;
use App\Domains\Marketplace\Models\MarketplaceApp;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index(Service $service): View
    {
        $this->authorize('view', $service);

        $apps = MarketplaceApp::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $installed = AppInstallation::query()
            ->where('service_id', $service->id)
            ->whereIn('status', ['installed', 'installing', 'pending'])
            ->pluck('status', 'marketplace_app_id')
            ->map(fn (string $s): string => $s);

        return view('panel.marketplace.index', compact('service', 'apps', 'installed'));
    }

    public function install(Request $request, Service $service, MarketplaceApp $app): RedirectResponse
    {
        $this->authorize('view', $service);

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['install' => 'Instalace je dostupná pouze pro aktivní služby.']);
        }

        if (!$app->is_active) {
            return back()->withErrors(['install' => 'Aplikace není dostupná.']);
        }

        // Idempotent — if already installed/installing, skip
        $existing = AppInstallation::query()
            ->where('service_id', $service->id)
            ->where('marketplace_app_id', $app->id)
            ->whereIn('status', ['installed', 'installing', 'pending'])
            ->first();

        if ($existing !== null) {
            return back()->with('status', "{$app->name} je již nainstalována nebo se instaluje.");
        }

        $installation = AppInstallation::create([
            'service_id'          => $service->id,
            'marketplace_app_id'  => $app->id,
            'status'              => 'installing',
        ]);

        // Mock provisioning task (same pattern as WordPress install)
        $service->provisioningTasks()->create([
            'operation'    => 'install_app',
            'status'       => TaskStatus::Success,
            'attempts'     => 1,
            'max_attempts' => 1,
            'payload'      => ['app_slug' => $app->slug, 'installation_id' => $installation->id, 'mock' => true],
            'result'       => ['mock' => true, 'app' => $app->slug, 'admin_url' => 'https://' . ($service->label ?? 'web') . '/' . $app->slug . '/admin'],
            'started_at'   => now(),
            'finished_at'  => now(),
        ]);

        $installation->update(['status' => 'installed', 'installed_at' => now(), 'version' => 'latest']);

        activity('marketplace')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['app_slug' => $app->slug])
            ->log('marketplace.app_installed');

        return back()->with('status', "{$app->name} byla úspěšně nainstalována (mock).");
    }

    public function remove(Request $request, Service $service, MarketplaceApp $app): RedirectResponse
    {
        $this->authorize('view', $service);

        $installation = AppInstallation::query()
            ->where('service_id', $service->id)
            ->where('marketplace_app_id', $app->id)
            ->whereIn('status', ['installed', 'installing', 'pending'])
            ->first();

        if ($installation === null) {
            return back()->withErrors(['remove' => 'Instalace nebyla nalezena.']);
        }

        $installation->update(['status' => 'removed']);

        activity('marketplace')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['app_slug' => $app->slug])
            ->log('marketplace.app_removed');

        return back()->with('status', "{$app->name} odstraněna.");
    }
}
