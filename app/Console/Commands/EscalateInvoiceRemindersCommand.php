<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Console\Command;

class EscalateInvoiceRemindersCommand extends Command
{
    protected $signature   = 'billing:escalate-reminders';
    protected $description = 'Send escalating reminders for overdue invoices (D+7, D+14, D+30)';

    private const STEPS = [7, 14, 30];

    public function handle(): int
    {
        $sent = 0;

        Invoice::with('customer.user')
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->get()
            ->each(function (Invoice $invoice) use (&$sent): void {
                $daysPastDue   = (int) now()->diffInDays($invoice->due_date, false) * -1;
                $currentCount  = $invoice->reminder_sent_count;
                $nextStep      = self::STEPS[$currentCount] ?? null;

                if ($nextStep === null || $daysPastDue < $nextStep) {
                    return;
                }

                $user = $invoice->customer?->user;
                if ($user === null) {
                    return;
                }

                $user->notify(new InvoiceReminderNotification($invoice, $currentCount + 1));

                $invoice->update([
                    'reminder_sent_count' => $currentCount + 1,
                    'last_reminder_at'    => now(),
                ]);

                $sent++;
            });

        $this->info("Sent {$sent} reminders.");

        return self::SUCCESS;
    }
}
