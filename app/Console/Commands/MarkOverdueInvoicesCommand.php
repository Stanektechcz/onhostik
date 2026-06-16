<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Transitions unpaid invoices from `sent` → `overdue` once past their due_date.
 * Safe to re-run: only touches `sent` invoices.
 */
class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'billing:mark-overdue';
    protected $description = 'Mark all unpaid past-due invoices as overdue';

    public function handle(): int
    {
        $count = Invoice::query()
            ->where('status', InvoiceStatus::Sent)
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => InvoiceStatus::Overdue]);

        $this->info("Marked {$count} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
