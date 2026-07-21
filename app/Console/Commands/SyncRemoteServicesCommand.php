<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\ServiceSyncState;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceRemoteSyncService;
use App\Models\User;
use App\Notifications\ServiceSyncDriftNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Reconciles services against their backend panels and reports drift.
 *
 * Read-only: it never creates, modifies or deletes anything in the panel.
 * The point is to catch the case where a customer was billed and the local
 * service says Active, but the site was never actually created in aaPanel.
 *
 * Scheduled hourly. Drift is logged as activity and, when new, notified to
 * admins so it does not sit unnoticed.
 */
class SyncRemoteServicesCommand extends Command
{
    protected $signature = 'services:sync-remote
                            {--service= : Reconcile a single service id}
                            {--all : Include Pending/Terminated services too}
                            {--limit=200 : Maximum services per run}';

    protected $description = 'Reconcile services against their backend panel (read-only) and report drift';

    public function handle(ServiceRemoteSyncService $sync): int
    {
        $services = $this->targets();

        if ($services->isEmpty()) {
            $this->info('No services to reconcile.');

            return self::SUCCESS;
        }

        $drifted = [];
        $counts  = [];

        foreach ($services as $service) {
            $before = $service->sync_state;
            $result = $sync->sync($service);
            $state  = $result['state'];

            $counts[$state->value] = ($counts[$state->value] ?? 0) + 1;

            if ($state->needsAttention()) {
                $this->warn("#{$service->id} {$service->label}: {$state->label()} — {$result['message']}");

                // Only alert on newly-detected drift, so a standing problem
                // does not re-notify every hour.
                if ($before !== $state) {
                    $drifted[] = $service;
                }
            } elseif ($this->output->isVerbose()) {
                $this->line("#{$service->id} {$service->label}: {$state->label()}");
            }
        }

        foreach ($counts as $state => $count) {
            $this->line(sprintf('%-16s %d', $state, $count));
        }

        if ($drifted !== []) {
            $admins = User::role('admin')->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ServiceSyncDriftNotification($drifted));
            }

            $this->error(count($drifted) . ' service(s) newly drifted — admins notified.');
        }

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Service> */
    private function targets(): \Illuminate\Support\Collection
    {
        $query = Service::query()->with('server');

        if ($id = $this->option('service')) {
            return $query->whereKey((int) $id)->get();
        }

        if (! $this->option('all')) {
            // Live services are the ones where drift actually costs money.
            $query->whereIn('status', [ServiceStatus::Active->value, ServiceStatus::Suspended->value]);
        }

        return $query->orderBy('last_synced_at')->limit((int) $this->option('limit'))->get();
    }
}
