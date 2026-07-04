<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorAlert;
use App\Models\User;
use App\Notifications\MonitorThresholdAlertNotification;
use Illuminate\Console\Command;

/**
 * Evaluates per-monitor alert thresholds and fires notifications to admin users.
 * Safe to run alongside monitoring:run-checks (different concerns).
 */
class EvaluateMonitorAlertsCommand extends Command
{
    protected $signature   = 'monitoring:evaluate-alerts';
    protected $description = 'Check monitor thresholds and open/resolve alerts; notify admins on breach';

    public function handle(): int
    {
        $triggered = 0;
        $resolved  = 0;

        Monitor::query()
            ->where('is_active', true)
            ->with(['alerts' => fn ($q) => $q->whereNull('resolved_at')])
            ->each(function (Monitor $monitor) use (&$triggered, &$resolved): void {
                $triggered += $this->evaluateResponseTime($monitor);
                $triggered += $this->evaluateUptime($monitor);
                $triggered += $this->evaluateSslExpiry($monitor);

                $resolved += $this->resolveResponseTime($monitor);
                $resolved += $this->resolveUptime($monitor);
                $resolved += $this->resolveSslExpiry($monitor);
            });

        $this->info("Monitor alerts evaluated. Triggered: {$triggered}, resolved: {$resolved}.");

        return self::SUCCESS;
    }

    private function evaluateResponseTime(Monitor $monitor): int
    {
        if ($monitor->response_time_threshold_ms === null) {
            return 0;
        }

        $latestCheck = $monitor->checks()->latest('checked_at')->first();
        if ($latestCheck === null || $latestCheck->response_ms === null) {
            return 0;
        }

        if ($latestCheck->response_ms <= $monitor->response_time_threshold_ms) {
            return 0;
        }

        if ($monitor->hasOpenAlert('response_time')) {
            return 0;
        }

        $alert = MonitorAlert::create([
            'monitor_id'      => $monitor->id,
            'type'            => 'response_time',
            'threshold_value' => $monitor->response_time_threshold_ms,
            'current_value'   => $latestCheck->response_ms,
            'triggered_at'    => now(),
        ]);

        $this->notifyAdmins($alert);

        return 1;
    }

    private function evaluateUptime(Monitor $monitor): int
    {
        if ($monitor->uptime_threshold_percent === null || $monitor->uptime_percent === null) {
            return 0;
        }

        if ((float) $monitor->uptime_percent >= (float) $monitor->uptime_threshold_percent) {
            return 0;
        }

        if ($monitor->hasOpenAlert('uptime')) {
            return 0;
        }

        $alert = MonitorAlert::create([
            'monitor_id'      => $monitor->id,
            'type'            => 'uptime',
            'threshold_value' => $monitor->uptime_threshold_percent,
            'current_value'   => $monitor->uptime_percent,
            'triggered_at'    => now(),
        ]);

        $this->notifyAdmins($alert);

        return 1;
    }

    private function evaluateSslExpiry(Monitor $monitor): int
    {
        if ($monitor->ssl_expires_at === null) {
            return 0;
        }

        $daysLeft = (int) now()->diffInDays($monitor->ssl_expires_at, false);
        $warnDays = $monitor->ssl_warn_days ?? 30;

        if ($daysLeft > $warnDays) {
            return 0;
        }

        if ($monitor->hasOpenAlert('ssl_expiry')) {
            return 0;
        }

        $alert = MonitorAlert::create([
            'monitor_id'      => $monitor->id,
            'type'            => 'ssl_expiry',
            'threshold_value' => $warnDays,
            'current_value'   => max(0, $daysLeft),
            'triggered_at'    => now(),
        ]);

        $this->notifyAdmins($alert);

        return 1;
    }

    private function resolveResponseTime(Monitor $monitor): int
    {
        if ($monitor->response_time_threshold_ms === null) {
            return 0;
        }

        $latestCheck = $monitor->checks()->latest('checked_at')->first();
        if ($latestCheck === null || $latestCheck->response_ms === null) {
            return 0;
        }

        if ($latestCheck->response_ms > $monitor->response_time_threshold_ms) {
            return 0;
        }

        return MonitorAlert::query()
            ->where('monitor_id', $monitor->id)
            ->where('type', 'response_time')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    private function resolveUptime(Monitor $monitor): int
    {
        if ($monitor->uptime_threshold_percent === null || $monitor->uptime_percent === null) {
            return 0;
        }

        if ((float) $monitor->uptime_percent < (float) $monitor->uptime_threshold_percent) {
            return 0;
        }

        return MonitorAlert::query()
            ->where('monitor_id', $monitor->id)
            ->where('type', 'uptime')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    private function resolveSslExpiry(Monitor $monitor): int
    {
        if ($monitor->ssl_expires_at === null) {
            return 0;
        }

        $daysLeft = (int) now()->diffInDays($monitor->ssl_expires_at, false);
        $warnDays = $monitor->ssl_warn_days ?? 30;

        if ($daysLeft <= $warnDays) {
            return 0;
        }

        return MonitorAlert::query()
            ->where('monitor_id', $monitor->id)
            ->where('type', 'ssl_expiry')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    private function notifyAdmins(MonitorAlert $alert): void
    {
        $alert->update(['notified_at' => now()]);

        User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->each(function (User $admin) use ($alert): void {
                try {
                    $admin->notify(new MonitorThresholdAlertNotification($alert));
                } catch (\Throwable) {}
            });
    }
}
