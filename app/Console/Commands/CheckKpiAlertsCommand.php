<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Models\KpiAlert;
use App\Models\User;
use App\Notifications\KpiAlertTriggeredNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Evaluates each active KpiAlert threshold against the live metric value.
 * Transitions triggered/resolved state and notifies admins on new breaches.
 *
 * Idempotent: safe to run multiple times; only fires notification on the
 * first breach transition (triggered_at goes from null → now).
 */
final class CheckKpiAlertsCommand extends Command
{
    protected $signature   = 'admin:check-kpi-alerts';
    protected $description = 'Evaluate KPI alert thresholds and notify admins on breach';

    public function handle(): int
    {
        $now       = Carbon::now();
        $triggered = 0;
        $resolved  = 0;

        KpiAlert::query()
            ->where('is_active', true)
            ->get()
            ->each(function (KpiAlert $alert) use ($now, &$triggered, &$resolved): void {
                $value = $this->computeMetric($alert->metric, $now);

                $wasTriggered = $alert->isTriggered();
                $nowBreaches  = $alert->breaches($value);

                $alert->last_value      = $value;
                $alert->last_checked_at = $now;

                if ($nowBreaches && ! $wasTriggered) {
                    $alert->triggered_at = $now;
                    $alert->save();
                    $this->notifyAdmins($alert);
                    $triggered++;
                } elseif (! $nowBreaches && $wasTriggered) {
                    $alert->triggered_at = null;
                    $alert->save();
                    $resolved++;
                } else {
                    $alert->save();
                }
            });

        $this->info("KPI alerts: {$triggered} newly triggered, {$resolved} resolved.");

        return self::SUCCESS;
    }

    private function computeMetric(string $metric, Carbon $now): float
    {
        return match ($metric) {
            'overdue_invoices_count' => (float) Invoice::query()
                ->where('status', InvoiceStatus::Overdue)
                ->count(),

            'failed_backups_24h' => (float) BackupJob::query()
                ->where('status', BackupJobStatus::Failed->value)
                ->where('created_at', '>=', $now->copy()->subDay())
                ->count(),

            'suspended_services_count' => (float) Service::query()
                ->where('status', ServiceStatus::Suspended)
                ->count(),

            'open_tickets_count' => (float) SupportTicket::query()
                ->whereIn('status', [TicketStatus::Open->value, TicketStatus::Answered->value])
                ->count(),

            // amount stored as minor units (1 CZK = 100 haléřů)
            'monthly_revenue_czk' => Payment::query()
                ->where('created_at', '>=', $now->copy()->startOfMonth())
                ->where('currency', 'CZK')
                ->sum('amount') / 100.0,

            default => 0.0,
        };
    }

    private function notifyAdmins(KpiAlert $alert): void
    {
        User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->get()
            ->each(function (User $admin) use ($alert): void {
                try {
                    $admin->notify(new KpiAlertTriggeredNotification($alert));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}
