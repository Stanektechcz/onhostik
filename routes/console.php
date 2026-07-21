<?php

declare(strict_types=1);

use App\Console\Commands\AcmeSslRenewCommand;
use App\Console\Commands\ComputeHealthScoresCommand;
use App\Console\Commands\HandleRenewalPaymentFailuresCommand;
use App\Console\Commands\ApplyLateFeesCommand;
use App\Console\Commands\CheckServiceQuotaBreachesCommand;
use App\Console\Commands\CheckKpiAlertsCommand;
use App\Console\Commands\SendMaintenanceRemindersCommand;
use App\Console\Commands\EscalateBreachedSlaTicketsCommand;
use App\Console\Commands\ApproveEligibleCommissionsCommand;
use App\Console\Commands\ProcessGdprErasureRequestsCommand;
use App\Console\Commands\RunScheduledBackupsCommand;
use App\Console\Commands\AutoPayInvoicesFromCreditCommand;
use App\Console\Commands\DetectChurnSignalsCommand;
use App\Console\Commands\CreateRenewalInvoicesCommand;
use App\Console\Commands\MarkOverdueInvoicesCommand;
use App\Console\Commands\ProcessDomainRenewalsCommand;
use App\Console\Commands\ProvisionPendingServicesCommand;
use App\Console\Commands\SyncRemoteServicesCommand;
use App\Console\Commands\RunMonitorChecksCommand;
use App\Console\Commands\SendDomainExpiringRemindersCommand;
use App\Console\Commands\SendPaymentOverdueRemindersCommand;
use App\Console\Commands\ExpireCreditCommand;
use App\Console\Commands\SendCreditExpiryRemindersCommand;
use App\Console\Commands\SendInvoiceDueRemindersCommand;
use App\Console\Commands\SendRenewalRemindersCommand;
use App\Console\Commands\SendServiceSuspensionWarningsCommand;
use App\Console\Commands\SendWeeklyDigestCommand;
use App\Console\Commands\SuspendOverdueServicesCommand;
use App\Console\Commands\SyncServiceUsageCommand;
use App\Console\Commands\TerminateOverdueServicesCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
*/

// Safety net: provision paid services still stuck in Pending (queue worker
// down / never ran). Synchronous, so it works even without a worker.
Schedule::command(ProvisionPendingServicesCommand::class)
    ->everyMinute()
    ->withoutOverlapping();

// Read-only reconciliation against the backend panels: catches a service the
// customer paid for that never actually got created (or that was changed
// outside the system). Never writes to the panel.
Schedule::command(SyncRemoteServicesCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Issue renewal proformas before the overdue/suspend jobs run, so a service
// renewing today never gets flagged overdue for yesterday's old due date.
Schedule::command(CreateRenewalInvoicesCommand::class)
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground();

// D39: settle those renewals for customers who set up auto-pay. Runs after the
// invoices exist; everyone without a saved method is untouched and pays by hand.
Schedule::command(\App\Console\Commands\AutoChargeRenewalsCommand::class)
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

// Mark unpaid past-due invoices as overdue (runs first so status is correct
// before the suspension job evaluates the grace period).
Schedule::command(MarkOverdueInvoicesCommand::class)
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

// Try to settle open invoices from customers' credit balance.
// Runs after mark-overdue so overdue invoices are also candidates.
Schedule::command(AutoPayInvoicesFromCreditCommand::class)
    ->dailyAt('01:05')
    ->withoutOverlapping()
    ->runInBackground();

// Suspend services whose invoice is overdue beyond the grace period
// (billing.suspension_grace_days, default 7 days).
Schedule::command(SuspendOverdueServicesCommand::class)
    ->dailyAt('01:15')
    ->withoutOverlapping()
    ->runInBackground();

// Detect churn signals: no-login, no-payment. Logs to activity log for CRM review.
Schedule::command(DetectChurnSignalsCommand::class)
    ->weeklyOn(1, '06:00') // Monday at 06:00
    ->withoutOverlapping()
    ->runInBackground();

// Auto-approve partner commissions that have passed their hold period.
// Runs after dunning jobs so refund status is already set correctly.
Schedule::command(ApproveEligibleCommissionsCommand::class)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Sync live usage stats (disk/bandwidth) from aaPanel for active services.
// Runs every 15 minutes; mock mode returns simulated data.
Schedule::command(SyncServiceUsageCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Monitor all active HTTP/SSL endpoints every 5 minutes.
// In PROVISIONING_MOCK_MODE=true no real network calls are made.
Schedule::command(RunMonitorChecksCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Send "invoice due tomorrow" reminder 1 day before due date.
Schedule::command(SendInvoiceDueRemindersCommand::class)
    ->dailyAt('06:45')
    ->withoutOverlapping()
    ->runInBackground();

// Send payment-overdue reminder emails at 1d, 3d, and 7d milestones.
Schedule::command(SendPaymentOverdueRemindersCommand::class)
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

// Warn customers that their service will be suspended in N days (before the
// billing:suspend-overdue job fires). Tracked via suspension_warning_sent_at.
Schedule::command(SendServiceSuspensionWarningsCommand::class)
    ->dailyAt('07:05')
    ->withoutOverlapping()
    ->runInBackground();

// Send renewal reminder emails at 14d, 7d, and 3d before service expiry.
Schedule::command(SendRenewalRemindersCommand::class)
    ->dailyAt('07:15')
    ->withoutOverlapping()
    ->runInBackground();

// Send domain-expiring reminders at 30d, 14d, and 7d.
Schedule::command(SendDomainExpiringRemindersCommand::class)
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->runInBackground();

// Auto-renew domains expiring within 7 days (auto_renew=true only).
Schedule::command(ProcessDomainRenewalsCommand::class)
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Terminate suspended services whose invoice is overdue beyond 30 days.
// Runs after suspend job, at a safe hour (minimal traffic).
Schedule::command(TerminateOverdueServicesCommand::class)
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->runInBackground();

// Dispatch backup jobs for all active BackupPolicy rows that are due.
// Individual RunBackupJob handles provider routing; mock mode is safe by default.
Schedule::command(RunScheduledBackupsCommand::class)
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();

// GDPR Art. 17 — anonymise accounts that requested deletion > 30 days ago.
Schedule::command(ProcessGdprErasureRequestsCommand::class)
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// SSL/ACME — renew certificates expiring within 14 days.
Schedule::command(AcmeSslRenewCommand::class)
    ->cron('0 4 * * 1,4') // Monday + Thursday at 04:15
    ->withoutOverlapping()
    ->runInBackground();

// Escalate SLA-breached support tickets and notify admins.
// Runs hourly so breach detection lag is at most 60 minutes.
Schedule::command(EscalateBreachedSlaTicketsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Send credit expiry reminders at 30d and 7d before expires_at.
Schedule::command(SendCreditExpiryRemindersCommand::class)
    ->dailyAt('07:45')
    ->withoutOverlapping()
    ->runInBackground();

// Write Expiry deduction ledger rows for deposits whose expires_at has passed.
Schedule::command(ExpireCreditCommand::class)
    ->dailyAt('01:45')
    ->withoutOverlapping()
    ->runInBackground();

// Evaluate KPI thresholds daily and notify admins on breach.
Schedule::command(CheckKpiAlertsCommand::class)
    ->dailyAt('08:30')
    ->withoutOverlapping()
    ->runInBackground();

// Send weekly account summary to customers on Monday morning.
Schedule::command(SendWeeklyDigestCommand::class)
    ->weeklyOn(1, '08:00') // Monday at 08:00
    ->withoutOverlapping()
    ->runInBackground();


// Send 24-hour reminders for upcoming maintenance windows.
Schedule::command(SendMaintenanceRemindersCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Apply flat late fee to overdue invoices after the configured grace period.
Schedule::command(ApplyLateFeesCommand::class)
    ->dailyAt('07:10')
    ->withoutOverlapping()
    ->runInBackground();

// P195: expire unpaid proforma orders past their validity window so they stop
// sitting in Pending forever and skewing the open-orders metrics.
Schedule::command(\App\Console\Commands\ExpireUnpaidOrdersCommand::class)
    ->dailyAt('06:40')
    ->withoutOverlapping()
    ->runInBackground();

// E52: warn while there is still time to rack another server, rather than
// discovering the fleet is full when a paid order lands on it.
Schedule::command(\App\Console\Commands\CheckFleetCapacityCommand::class)
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->runInBackground();

// Recompute customer health scores nightly for CRM dashboards.
Schedule::command(ComputeHealthScoresCommand::class)
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

// Send targeted renewal-failure notifications after the configured grace period.
Schedule::command(HandleRenewalPaymentFailuresCommand::class)
    ->dailyAt('07:20')
    ->withoutOverlapping()
    ->runInBackground();

// Alert admins when services breach resource quota thresholds (after SyncServiceUsage).
Schedule::command(CheckServiceQuotaBreachesCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
