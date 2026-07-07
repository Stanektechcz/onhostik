<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Notifications\InvoiceDueSoonNotification;
use Illuminate\Console\Command;

/**
 * Sends a "due soon" reminder email 1 day before the invoice due date.
 * Complements SendPaymentOverdueRemindersCommand which handles post-due escalation.
 * Safe to re-run daily — fires once per invoice (tracked via reminder_before_1d_sent_at).
 */
class SendInvoiceDueRemindersCommand extends Command
{
    protected $signature   = 'billing:send-due-reminders';
    protected $description = 'Send "invoice due tomorrow" reminder emails 1 day before the due date';

    public function handle(): int
    {
        $sent       = 0;
        $targetDate = now()->addDay()->toDateString();

        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->whereNull('reminder_before_1d_sent_at')
            ->whereDate('due_date', $targetDate)
            ->with('customer.user')
            ->each(function (Invoice $invoice) use (&$sent): void {
                $user = $invoice->customer?->user;

                if ($user === null) {
                    return;
                }

                try {
                    $user->notify(new InvoiceDueSoonNotification($invoice, 1));
                    $invoice->update(['reminder_before_1d_sent_at' => now()]);
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        $this->info("Sent {$sent} invoice due-soon reminder(s).");

        return self::SUCCESS;
    }
}
