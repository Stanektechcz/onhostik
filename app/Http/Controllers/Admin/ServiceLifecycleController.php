<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceLifecycleService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceLifecycleController extends Controller
{
    public function __construct(private readonly ServiceLifecycleService $lifecycle) {}

    public function index(Request $request): View
    {
        $tab = $request->string('tab', 'suspended')->toString();

        $query = Service::with(['customer', 'product'])->orderByDesc('updated_at');

        $services = match ($tab) {
            'expiring' => $query->where('status', ServiceStatus::Active)
                ->whereNotNull('next_due_date')
                ->whereDate('next_due_date', '<=', now()->addDays(14)->toDateString())
                ->paginate(20),
            default => $query->where('status', ServiceStatus::Suspended)->paginate(20),
        };

        $stats = $this->lifecycle->lifecycleStats();

        return view('admin.lifecycle.index', compact('services', 'stats', 'tab'));
    }

    public function suspend(Request $request, Service $service): RedirectResponse
    {
        $reason = $request->string('reason', 'manual_admin')->toString();
        $this->lifecycle->suspend($service, $reason);

        return back()->with('status', "Požadavek na pozastavení služby #{$service->id} odeslán.");
    }

    public function unsuspend(Service $service): RedirectResponse
    {
        $this->lifecycle->unsuspend($service, 'manual_admin');

        return back()->with('status', "Požadavek na obnovení služby #{$service->id} odeslán.");
    }

    public function terminate(Service $service): RedirectResponse
    {
        $this->lifecycle->terminate($service, 'manual_admin');

        return back()->with('status', "Požadavek na ukončení služby #{$service->id} odeslán.");
    }
}
