<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();
        $dueFilter = $request->string('due')->toString();

        $services = Service::query()
            ->with(['customer', 'product', 'server', 'domainRegistration'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($dueFilter === 'soon', fn ($query) => $query
                ->whereNotNull('next_due_date')
                ->whereDate('next_due_date', '<=', now()->addDays(30))
                ->whereDate('next_due_date', '>=', now()))
            ->when($dueFilter === 'overdue', fn ($query) => $query
                ->whereNotNull('next_due_date')
                ->whereDate('next_due_date', '<', now()))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('label', 'like', "%{$search}%")
                      ->orWhere('external_id', 'like', "%{$search}%")
                      ->orWhereHas('customer', fn ($c) => $c->where('email', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $activeCount   = Service::where('status', ServiceStatus::Active)->count();
        $suspendedCount = Service::where('status', ServiceStatus::Suspended)->count();
        $expiringCount = Service::whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', now()->addDays(30))
            ->whereDate('next_due_date', '>=', now())
            ->count();
        $overdueCount  = Service::whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<', now())
            ->count();

        return view('admin.services', [
            'services'       => $services,
            'filter'         => $status,
            'search'         => $search,
            'dueFilter'      => $dueFilter,
            'activeCount'    => $activeCount,
            'suspendedCount' => $suspendedCount,
            'expiringCount'  => $expiringCount,
            'overdueCount'   => $overdueCount,
        ]);
    }

    public function show(Service $service): View
    {
        $service->load([
            'customer.user',
            'product',
            'server',
            'orderItem.order',
            'provisioningTasks' => fn ($q) => $q->latest('id')->limit(20),
            'domainRegistration',
        ]);

        $audit = Activity::query()
            ->where('subject_type', Service::class)
            ->where('subject_id', $service->id)
            ->with('causer')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin.service-show', [
            'service' => $service,
            'audit'   => $audit,
        ]);
    }

    public function suspend(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['service' => 'Only active services can be suspended.']);
        }

        ChangeServiceStateJob::dispatch($service->id, 'suspend', $validated['reason']);

        activity('provisioning')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['operation' => 'suspend', 'reason' => $validated['reason']])
            ->log('service.suspend_requested');

        return back()->with('status', __('panel.admin.service_suspend_queued'));
    }

    public function unsuspend(Request $request, Service $service): RedirectResponse
    {
        if ($service->status !== ServiceStatus::Suspended) {
            return back()->withErrors(['service' => 'Only suspended services can be reactivated.']);
        }

        ChangeServiceStateJob::dispatch($service->id, 'unsuspend');

        activity('provisioning')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['operation' => 'unsuspend'])
            ->log('service.unsuspend_requested');

        return back()->with('status', __('panel.admin.service_unsuspend_queued'));
    }
}
