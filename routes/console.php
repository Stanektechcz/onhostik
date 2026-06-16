<?php

declare(strict_types=1);

use App\Console\Commands\CreateRenewalInvoicesCommand;
use App\Console\Commands\MarkOverdueInvoicesCommand;
use App\Console\Commands\SuspendOverdueServicesCommand;
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
