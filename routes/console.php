<?php

declare(strict_types=1);

use App\Console\Commands\AcmeSslRenewCommand;
use App\Console\Commands\EscalateBreachedSlaTicketsCommand;
use App\Console\Commands\ApproveEligibleCommissionsCommand;
use App\Console\Commands\ProcessGdprErasureRequestsCommand;
use App\Console\Commands\RunScheduledBackupsCommand;
use App\Console\Commands\AutoPayInvoicesFromCreditCommand;
use App\Console\Commands\DetectChurnSignalsCommand;
use App\Console\Commands\CreateRenewalInvoicesCommand;
use App\Console\Commands\MarkOverdueInvoicesCommand;
use App\Console\Commands\ProcessDomainRenewalsCommand;
use App\Console\Commands\RunMonitorChecksCommand;
use App\Console\Commands\SendDomainExpiringRemindersCommand;
use App\Console\Commands\SendPaymentOverdueRemindersCommand;
use App\Console\Commands\SendRenewalRemindersCommand;
use App\Console\Commands\SuspendOverdueServicesCommand;
use App\Console\Commands\SyncServiceUsageCommand;
use App\Console\Commands\TerminateOverdueServicesCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
*/

// Issue renewal proformas before the overdue/suspend jobs run, so a service
// renewing today never gets flagged overdue for yesterday's old due date.
Schedule::command(CreateRenewalInvoicesCommand::class)
    ->dailyAt('00:30')
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

// Send payment-overdue reminder emails at 1d, 3d, and 7d milestones.
Schedule::command(SendPaymentOverdueRemindersCommand::class)
    ->dailyAt('07:00')
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
