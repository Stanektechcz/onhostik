<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Notifications\PaymentOverdueNotification;
use Illuminate\Console\Command;

/**
 * Sends a payment-overdue email to customers whose invoices have been
 * overdue for at least 1, 3, or 7 days. Safe to re-run daily — each
 * milestone is tracked via reminder_Xd_sent_at to avoid duplicate sends.
 * Invoices with an active dunning_paused_until are skipped entirely.
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
            $sentAtColumn = "reminder_{$days}d_sent_at";

            Invoice::query()
                ->where('status', InvoiceStatus::Overdue)
                ->whereNull($sentAtColumn)
                ->whereDate('due_date', '<=', now()->subDays($days)->toDateString())
                ->where(function ($q): void {
                    $q->whereNull('dunning_paused_until')
                      ->orWhere('dunning_paused_until', '<', now());
                })
                ->with('customer.user')
                ->each(function (Invoice $invoice) use ($days, $sentAtColumn, &$sent): void {
                    $user = $invoice->customer?->user;

                    if ($user !== null) {
                        try {
                            $user->notify(new PaymentOverdueNotification($invoice, $days));
                            $sent++;
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }

                    // Mark milestone so it is never re-attempted at this threshold,
                    // even when the customer has no user account.
                    $invoice->update([$sentAtColumn => now()]);
                });
        }

        $this->info("Sent {$sent} overdue payment reminder(s).");

        return self::SUCCESS;
    }
}
