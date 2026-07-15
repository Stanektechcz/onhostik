<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Backups\Models\BackupPolicy;
use App\Domains\Billing\Actions\IssueRenewalInvoiceAction;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceController extends Controller
{
    /** Stream services as CSV. */
    public function export(Request $request): StreamedResponse
    {
        $status    = $request->string('status')->toString();
        $search    = $request->string('q')->toString();
        $dueFilter = $request->string('due')->toString();

        $query = Service::query()
            ->with(['customer', 'product', 'server', 'domainRegistration'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($dueFilter === 'soon', fn ($q) => $q
                ->whereNotNull('next_due_date')
                ->whereDate('next_due_date', '<=', now()->addDays(30))
                ->whereDate('next_due_date', '>=', now()))
            ->when($dueFilter === 'overdue', fn ($q) => $q
                ->whereNotNull('next_due_date')
                ->whereDate('next_due_date', '<', now()))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('label', 'like', "%{$search}%")
                        ->orWhere('external_id', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('email', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id');

        $filename = 'sluzby-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'Label', 'Status', 'Zákazník', 'E-mail',
                'Produkt', 'Server', 'Ext. ID', 'Doména', 'Příští platba', 'Vytvořeno',
            ], ';');

            $query->chunk(200, function ($services) use ($out): void {
                foreach ($services as $service) {
                    fputcsv($out, [
                        $service->id,
                        $service->label,
                        $service->status->label(),
                        $service->customer?->company_name ?: ($service->customer->email ?: ''),
                        $service->customer->email ?: '',
                        $service->product->name ?: '',
                        $service->server->name ?: '',
                        $service->external_id ?? '',
                        $service->domainRegistration?->fqdn() ?? '',
                        $service->next_due_date?->format('d.m.Y') ?? '',
                        $service->created_at?->format('d.m.Y') ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

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
            'planChanges.fromPlan',
            'planChanges.toPlan',
            'planChanges.changedBy',
            'firewallRules'   => fn ($q) => $q->latest('id')->limit(20),
            'healthIncidents' => fn ($q) => $q->orderByRaw("CASE WHEN status = 'resolved' THEN 1 ELSE 0 END")->latest('id')->limit(10),
            'backupLogs'      => fn ($q) => $q->latest('started_at')->limit(5),
        ]);

        $audit = Activity::query()
            ->where('subject_type', Service::class)
            ->where('subject_id', $service->id)
            ->with('causer')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin.service-show', [
            'service'      => $service,
            'audit'        => $audit,
            'planChanges'  => $service->planChanges,
            'backupPolicy' => BackupPolicy::query()->where('service_id', $service->id)->first(),
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

    public function updateLabel(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:150'],
        ]);

        $old = $service->label;
        $service->update(['label' => $validated['label']]);

        activity('service')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['old_label' => $old, 'new_label' => $validated['label']])
            ->log('service.label_updated');

        return back()->with('status', __('panel.admin.service_label_updated'));
    }

    public function adjustDueDate(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'next_due_date' => ['required', 'date', 'after:today'],
        ]);

        $old = $service->next_due_date?->toDateString();
        $new = Carbon::parse($validated['next_due_date'])->toDateString();

        $service->update(['next_due_date' => $new]);

        activity('service')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['old_due_date' => $old, 'new_due_date' => $new])
            ->log('service.due_date_adjusted');

        return back()->with('status', __('panel.admin.service_due_date_updated'));
    }

    public function manualRenewal(Service $service, IssueRenewalInvoiceAction $action): RedirectResponse
    {
        if ($service->next_due_date === null) {
            return back()->withErrors(['service' => __('panel.admin.service_no_due_date')]);
        }

        $invoice = $action->execute($service);

        if ($invoice === null) {
            return back()->withErrors(['service' => __('panel.admin.service_renewal_failed')]);
        }

        activity('billing')
            ->performedOn($service)
            ->withProperties(['invoice_id' => $invoice->id, 'invoice_number' => $invoice->number])
            ->log('service.renewal_invoice_issued');

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', __('panel.admin.service_renewal_issued', ['number' => $invoice->number]));
    }

    public function batchSuspend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'    => ['required', 'array', 'min:1', 'max:50'],
            'ids.*'  => ['integer', 'exists:services,id'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $queued = 0;
        Service::whereIn('id', $validated['ids'])
            ->where('status', ServiceStatus::Active->value)
            ->each(function (Service $service) use ($validated, $request, &$queued): void {
                ChangeServiceStateJob::dispatch($service->id, 'suspend', $validated['reason']);
                activity('provisioning')
                    ->performedOn($service)
                    ->causedBy($request->user())
                    ->withProperties(['operation' => 'batch_suspend', 'reason' => $validated['reason']])
                    ->log('service.batch_suspend_requested');
                $queued++;
            });

        return back()->with('status', "Hromadné pozastavení odesláno: {$queued} služeb.");
    }

    public function batchUnsuspend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'exists:services,id'],
        ]);

        $queued = 0;
        Service::whereIn('id', $validated['ids'])
            ->where('status', ServiceStatus::Suspended->value)
            ->each(function (Service $service) use ($request, &$queued): void {
                ChangeServiceStateJob::dispatch($service->id, 'unsuspend');
                activity('provisioning')
                    ->performedOn($service)
                    ->causedBy($request->user())
                    ->withProperties(['operation' => 'batch_unsuspend'])
                    ->log('service.batch_unsuspend_requested');
                $queued++;
            });

        return back()->with('status', "Hromadná reaktivace odesláno: {$queued} služeb.");
    }
}
