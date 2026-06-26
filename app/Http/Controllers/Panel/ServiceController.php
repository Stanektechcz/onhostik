<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Jobs\RunBackupJob;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupPolicy;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        return view('panel.services.show', [
            'service' => $service->load([
                'product',
                'domainRegistration',
                'provisioningTasks' => fn ($query) => $query->latest('id'),
            ]),
            'monitor'    => Monitor::query()->where('service_id', $service->id)->first(),
            'backupJobs' => BackupJob::query()->where('service_id', $service->id)->latest('id')->limit(5)->get(),
            'mockMode'   => (bool) config('provisioning.mock_mode', true),
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
}
