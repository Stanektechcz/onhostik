<?php

declare(strict_types=1);

use App\Console\Commands\ApproveEligibleCommissionsCommand;
use App\Console\Commands\CreateRenewalInvoicesCommand;
use App\Console\Commands\MarkOverdueInvoicesCommand;
use App\Console\Commands\ProcessDomainRenewalsCommand;
use App\Console\Commands\RunMonitorChecksCommand;
use App\Console\Commands\SendDomainExpiringRemindersCommand;
use App\Console\Commands\SendPaymentOverdueRemindersCommand;
use App\Console\Commands\SendRenewalRemindersCommand;
use App\Console\Commands\SuspendOverdueServicesCommand;
use App\Console\Commands\SyncServiceUsageCommand;
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

// Suspend services whose invoice is overdue beyond the grace period
// (billing.suspension_grace_days, default 7 days).
Schedule::command(SuspendOverdueServicesCommand::class)
    ->dailyAt('01:15')
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
