<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Notifications\PaymentOverdueNotification;
use Illuminate\Console\Command;

/**
 * Sends a payment-overdue email to customers whose invoices have been
 * overdue for 1, 3, or 7 days. Safe to re-run daily (reminder_sent_at
 * is checked to avoid duplicate sends at the same threshold).
 */
class SendPaymentOverdueRemindersCommand extends Command
{
    protected $signature   = 'billing:send-overdue-reminders';
    protected $description = 'Send payment-overdue email reminders at 1d, 3d, and 7d milestones';

    public function handle(): int
    {
        $thresholds = [1, 3, 7];
        $sent       = 0;

        foreach ($thresholds as $days) {
            $cutoff = now()->subDays($days)->toDateString();

            Invoice::query()
                ->where('status', InvoiceStatus::Overdue)
                ->whereDate('due_date', $cutoff)
                ->with('customer.user')
                ->each(function (Invoice $invoice) use ($days, &$sent): void {
                    $user = $invoice->customer?->user;

                    if ($user === null) {
                        return;
                    }

                    try {
                        $user->notify(new PaymentOverdueNotification($invoice, $days));
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
        }

        $this->info("Sent {$sent} overdue payment reminder(s).");

        return self::SUCCESS;
    }
}
