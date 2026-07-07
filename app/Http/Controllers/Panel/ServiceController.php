<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Jobs\RunBackupJob;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupPolicy;
use App\Domains\Billing\Actions\CancelSubscriptionAction;
use App\Domains\Billing\Actions\PauseSubscriptionAction;
use App\Domains\Billing\Actions\ResumeSubscriptionAction;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Models\ServiceCancellation;
use App\Models\ServicePlanChange;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        $sCounts = \Illuminate\Support\Facades\DB::table('services')
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('panel.services.index', [
            'services'       => $customer->services()->with('product')->latest('id')->paginate(15),
            'countActive'    => (int) ($sCounts[ServiceStatus::Active->value] ?? 0),
            'countSuspended' => (int) ($sCounts[ServiceStatus::Suspended->value] ?? 0),
            'countTotal'     => (int) $sCounts->sum(),
        ]);
    }

    public function show(Service $service): View
    {
        $this->authorize('view', $service);

        $renewalInvoice = \App\Domains\Billing\Models\Invoice::query()
            ->where('renewal_service_id', $service->id)
            ->whereIn('status', [
                \App\Domains\Billing\Enums\InvoiceStatus::Sent,
                \App\Domains\Billing\Enums\InvoiceStatus::Overdue,
            ])
            ->latest('id')
            ->first();

        return view('panel.services.show', [
            'service' => $service->load([
                'product',
                'domainRegistration',
                'provisioningTasks' => fn ($query) => $query->latest('id'),
            ]),
            'monitor'        => $monitor = Monitor::query()->where('service_id', $service->id)->first(),
            'incidents'      => $monitor?->incidents()->orderByDesc('started_at')->limit(10)->get() ?? collect(),
            'sslDays'        => $monitor?->ssl_expires_at?->diffInDays(now()),
            'backupJobs'     => BackupJob::query()->where('service_id', $service->id)->latest('id')->limit(5)->get(),
            'backupPolicy'   => BackupPolicy::query()->where('service_id', $service->id)->first(),
            'renewalInvoice' => $renewalInvoice,
            'mockMode'       => (bool) config('provisioning.mock_mode', true),
        ]);
    }

    /** Manual mock backup — queued, idempotent at the job level. */
    public function requestBackup(Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        abort_unless((bool) config('provisioning.mock_mode', true), 403, 'Mock backups only.');

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['backup' => __('panel.services.backup_inactive')]);
        }

        // Throttle double-clicks: one pending/running backup per service.
        $alreadyQueued = BackupJob::query()
            ->where('service_id', $service->id)
            ->whereIn('status', [BackupJobStatus::Pending->value, BackupJobStatus::Running->value])
            ->exists();

        if (!$alreadyQueued) {
            $job = BackupJob::create([
                'backup_policy_id' => $service->id ? BackupPolicy::query()
                    ->where('service_id', $service->id)->value('id') : null,
                'service_id' => $service->id,
                'type'       => 'manual',
                'status'     => BackupJobStatus::Pending,
            ]);

            RunBackupJob::dispatch($job->id);

            activity('backup')
                ->performedOn($service)
                ->withProperties(['backup_job_id' => $job->id, 'type' => 'manual'])
                ->log('backup.requested');
        }

        return back()->with('status', __('panel.services.backup_requested'));
    }

    /** Show available upgrade/downgrade plans for a service. */
    public function changePlan(Request $request, Service $service): View
    {
        $this->authorize('view', $service);

        abort_if($service->product === null, 404, 'Product not attached.');

        $customer = $request->user()->customer;
        $currency = $customer !== null ? ($customer->preferred_currency ?? Currency::default()) : Currency::default();

        // Current plan is the one that created this service (via orderItem → pricingPlan)
        $currentPlanId = $service->orderItem?->pricing_plan_id;

        $availablePlans = PricingPlan::query()
            ->where('product_id', $service->product_id)
            ->where('is_active', true)
            ->where('id', '!=', $currentPlanId)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (PricingPlan $p) => $p->supportsCurrency($currency))
            ->values();

        return view('panel.services.change-plan', compact('service', 'availablePlans', 'currency', 'currentPlanId'));
    }

    /** Returns a JSON billing preview for switching to a different plan. */
    public function changePlanPreview(Request $request, Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:pricing_plans,id'],
        ]);

        $customer = $request->user()->customer;
        $currency = $customer !== null ? ($customer->preferred_currency ?? Currency::default()) : Currency::default();

        $newPlan = PricingPlan::findOrFail($validated['plan_id']);
        abort_unless($newPlan->product_id === $service->product_id, 422, 'Plan belongs to a different product.');

        $currentPlanId = $service->orderItem?->pricing_plan_id;
        $currentPlan   = $currentPlanId ? PricingPlan::find($currentPlanId) : null;

        $newMonths = max(1, $newPlan->billing_cycle->months());
        $curMonths = $currentPlan ? max(1, $currentPlan->billing_cycle->months()) : 1;

        $newDailyRate = $newPlan->supportsCurrency($currency)
            ? $newPlan->priceFor($currency)->getAmount()->toFloat() / ($newMonths * 30)
            : 0.0;

        $curDailyRate = ($currentPlan && $currentPlan->supportsCurrency($currency))
            ? $currentPlan->priceFor($currency)->getAmount()->toFloat() / ($curMonths * 30)
            : 0.0;

        $daysRemaining  = $service->next_due_date ? max(0, (int) now()->diffInDays($service->next_due_date, false)) : 0;
        $proratedAmount = max(0.0, round(($newDailyRate - $curDailyRate) * $daysRemaining, 2));

        $newPrice = $newPlan->supportsCurrency($currency)
            ? $newPlan->priceFor($currency)->getAmount()->toFloat()
            : 0.0;
        $curPrice = ($currentPlan && $currentPlan->supportsCurrency($currency))
            ? $currentPlan->priceFor($currency)->getAmount()->toFloat()
            : 0.0;

        return response()->json([
            'current_plan_price' => round($curPrice, 2),
            'new_plan_price'     => round($newPrice, 2),
            'prorated_days'      => $daysRemaining,
            'prorated_amount'    => $proratedAmount,
            'currency'           => $currency->value,
            'is_upgrade'         => $newPrice > $curPrice,
        ]);
    }

    /** Record the plan-change intent; creates a new order for the target plan. */
    public function applyChangePlan(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:pricing_plans,id'],
        ]);

        $plan = PricingPlan::findOrFail($validated['plan_id']);

        abort_unless($plan->product_id === $service->product_id, 422, 'Plan does not belong to the same product.');

        // Log the intent (actual provisioning driver change happens post-payment)
        $fromPlanId = $service->orderItem?->pricing_plan_id;

        ServicePlanChange::create([
            'service_id'         => $service->id,
            'from_plan_id'       => $fromPlanId,
            'to_plan_id'         => $plan->id,
            'changed_by_user_id' => $request->user()?->id,
            'reason'             => 'customer_request',
            'changed_at'         => now(),
        ]);

        activity('billing')
            ->performedOn($service)
            ->withProperties([
                'from_plan_id' => $fromPlanId,
                'to_plan_id'   => $plan->id,
                'plan_name'    => $plan->name,
            ])
            ->log('service.plan_change_requested');

        return redirect()
            ->route('front.order', $plan)
            ->with('service_change_id', $service->id)
            ->with('info', __('panel.services.plan_change_redirect', ['plan' => $plan->name]));
    }

    /** Customer requests service cancellation — opens a support ticket for admin review. */
    public function requestCancellation(Request $request, Service $service, TicketService $tickets): RedirectResponse
    {
        $this->authorize('view', $service);

        if (! in_array($service->status, [ServiceStatus::Active, ServiceStatus::Suspended], true)) {
            return back()->withErrors(['cancel' => __('panel.services.cancel_not_allowed')]);
        }

        $user     = $request->user();
        $customer = $user?->customer;

        abort_if($user === null || $customer === null, 403);

        $validated = $request->validate([
            'cancellation_reason'   => ['nullable', 'string', 'in:' . implode(',', array_keys(ServiceCancellation::REASONS))],
            'cancellation_feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $subject = __('panel.services.cancel_ticket_subject', ['label' => $service->label]);
        $body    = __('panel.services.cancel_ticket_body', [
            'label'    => $service->label,
            'product'  => $service->product->name ?: '—',
            'due_date' => $service->next_due_date?->format('d.m.Y') ?? '—',
        ]);

        $ticket = $tickets->open($customer, $user, $subject, $body, TicketPriority::Normal, 'billing');

        if (isset($validated['cancellation_reason'])) {
            ServiceCancellation::create([
                'service_id' => $service->id,
                'user_id'    => $user->id,
                'reason'     => $validated['cancellation_reason'],
                'feedback'   => $validated['cancellation_feedback'] ?? null,
            ]);
        }

        activity('panel')
            ->performedOn($service)
            ->causedBy($user)
            ->withProperties(['ticket_id' => $ticket->id])
            ->log('service.cancellation_requested');

        return back()->with('status', __('panel.services.cancel_requested', ['ticket' => $ticket->id]));
    }

    /** Customer pauses their active service for 1–90 days. */
    public function pause(Request $request, Service $service, PauseSubscriptionAction $action): RedirectResponse
    {
        $this->authorize('view', $service);

        $validated = $request->validate([
            'paused_days' => ['required', 'integer', 'min:1', 'max:90'],
            'reason'      => ['nullable', 'string', 'max:500'],
        ]);

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['pause' => __('panel.services.pause_not_active')]);
        }

        try {
            $action->execute($service, (int) $validated['paused_days'], $validated['reason'] ?? '');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['pause' => $e->getMessage()]);
        }

        $until = now()->addDays((int) $validated['paused_days'])->format('d.m.Y');

        return back()->with('status', __('panel.services.paused', [
            'days'  => $validated['paused_days'],
            'until' => $until,
        ]));
    }

    /** Customer resumes a paused service. */
    public function resume(Request $request, Service $service, ResumeSubscriptionAction $action): RedirectResponse
    {
        $this->authorize('view', $service);

        if (!$service->isPaused()) {
            return back()->withErrors(['resume' => __('panel.services.not_paused')]);
        }

        try {
            $action->execute($service);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['resume' => $e->getMessage()]);
        }

        return back()->with('status', __('panel.services.resumed'));
    }

    /** Customer marks service for cancellation at end of billing period. */
    public function cancelAtPeriodEnd(Request $request, Service $service, CancelSubscriptionAction $action): RedirectResponse
    {
        $this->authorize('view', $service);

        if (!in_array($service->status, [ServiceStatus::Active, ServiceStatus::Suspended], true)) {
            return back()->withErrors(['cancel' => __('panel.services.cancel_not_allowed')]);
        }

        if ($service->isCancelledAtPeriodEnd()) {
            return back()->with('status', __('panel.services.already_cancel_scheduled'));
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $action->execute($service, $validated['reason'] ?? '');

        $date = $service->next_due_date?->format('d.m.Y') ?? '—';

        return back()->with('status', __('panel.services.cancel_at_period_end_set', ['date' => $date]));
    }

    /** Customer note — freetext annotation visible only to the customer. */
    public function updateNote(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $validated = $request->validate([
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->update(['customer_note' => $validated['customer_note'] ?? null]);

        activity('service')
            ->performedOn($service)
            ->causedBy($request->user())
            ->log('service.customer_note_updated');

        return back()->with('status', 'Poznámka uložena.');
    }

    /** Customer renames their service (changes the label). */
    public function rename(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $validated = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $service->update(['label' => $validated['label']]);

        activity('service')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['label' => $validated['label']])
            ->log('service.renamed');

        return back()->with('status', 'Název služby byl změněn.');
    }

    /** Toggle auto-renewal on/off for the service. */
    public function toggleAutoRenew(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $newValue = ! $service->auto_renew;
        $service->update(['auto_renew' => $newValue]);

        activity('service')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['auto_renew' => $newValue])
            ->log('service.auto_renew_toggled');

        return back()->with('status', $newValue
            ? 'Automatická obnova zapnuta.'
            : 'Automatická obnova vypnuta.');
    }

    /** Mock WordPress one-click install — records a task, no real install. */
    public function installWordpress(Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        abort_unless((bool) config('provisioning.mock_mode', true), 403, 'Mock installer only.');

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['wordpress' => __('panel.services.wp_inactive')]);
        }

        $existing = $service->provisioningTasks()
            ->where('operation', 'install_wordpress')
            ->where('status', TaskStatus::Success->value)
            ->exists();

        if (!$existing) {
            $service->provisioningTasks()->create([
                'operation'    => 'install_wordpress',
                'status'       => TaskStatus::Success,
                'attempts'     => 1,
                'max_attempts' => 1,
                'payload'      => ['mock' => true],
                'result'       => ['mock' => true, 'app' => 'wordpress', 'admin_url' => 'https://' . ($service->label ?? 'web') . '/wp-admin'],
                'started_at'   => now(),
                'finished_at'  => now(),
            ]);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties(['operation' => 'install_wordpress', 'mock' => true])
                ->log('provisioning.wordpress_mock_installed');
        }

        return back()->with('status', __('panel.services.wp_installed'));
    }

    /** Update the backup schedule configuration for a service. */
    public function updateBackupSchedule(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $validated = $request->validate([
            'frequency'        => ['required', 'in:daily,weekly,monthly'],
            'scheduled_hour'   => ['required', 'integer', 'min:0', 'max:23'],
            'scheduled_weekday'=> ['nullable', 'integer', 'min:0', 'max:6'],
            'retention_days'   => ['required', 'integer', 'min:1', 'max:365'],
            'notify_on_failure'=> ['nullable', 'boolean'],
            'is_active'        => ['nullable', 'boolean'],
        ]);

        $policy = BackupPolicy::query()
            ->firstOrNew(['service_id' => $service->id]);

        $policy->fill([
            'service_id'       => $service->id,
            'frequency'        => $validated['frequency'],
            'scheduled_hour'   => (int) $validated['scheduled_hour'],
            'scheduled_weekday'=> isset($validated['scheduled_weekday'])
                ? (int) $validated['scheduled_weekday']
                : null,
            'retention_days'   => (int) $validated['retention_days'],
            'notify_on_failure'=> (bool) ($validated['notify_on_failure'] ?? true),
            'is_active'        => (bool) ($validated['is_active'] ?? true),
        ])->save();

        activity('backup')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties([
                'frequency'      => $policy->frequency,
                'scheduled_hour' => $policy->scheduled_hour,
                'retention_days' => $policy->retention_days,
            ])
            ->log('backup.schedule_updated');

        return back()->with('status', 'Plán zálohování byl uložen.');
    }
}
