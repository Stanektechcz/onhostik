<?php

declare(strict_types=1);

use App\Console\Commands\MarkOverdueInvoicesCommand;
use App\Console\Commands\SuspendOverdueServicesCommand;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
*/

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

// TODO: billing:create-renewals — requires IssueRenewalInvoiceAction
// (Phase 4: create standalone renewal proforma per active service
//  N days before next_due_date without touching the original order flow).
