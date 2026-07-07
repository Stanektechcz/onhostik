<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\RenewalReminderNotification;
use Illuminate\Console\Command;

/**
 * Sends renewal reminder emails to customers whose services are due in
 * 30, 14, 7, or 1 day(s) and have an open renewal invoice.
 * Safe to re-run daily — fires once per threshold per service per cycle.
 */
class SendRenewalRemindersCommand extends Command
{
    protected $signature   = 'billing:send-renewal-reminders';
    protected $description = 'Send renewal reminder emails at 30d, 14d, 7d, and 1d before service expiry';

    /** @var array<int, string> */
    private const THRESHOLDS = [
        30 => 'renewal_reminder_30d_sent_at',
        14 => 'renewal_reminder_14d_sent_at',
        7  => 'renewal_reminder_7d_sent_at',
        1  => 'renewal_reminder_1d_sent_at',
    ];

    public function handle(): int
    {
        $sent = 0;

        foreach (self::THRESHOLDS as $days => $sentColumn) {
            $targetDate = now()->addDays($days)->toDateString();

            Service::query()
                ->where('status', ServiceStatus::Active)
                ->whereDate('next_due_date', $targetDate)
                ->whereNull($sentColumn)
                ->with(['customer.user'])
                ->each(function (Service $service) use ($days, $sentColumn, &$sent): void {
                    $invoice = Invoice::query()
                        ->where('renewal_service_id', $service->id)
                        ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
                        ->latest('id')
                        ->first();

                    if ($invoice === null) {
                        return;
                    }

                    $user = $service->customer?->user;

                    if ($user === null) {
                        return;
                    }

                    try {
                        $user->notify(new RenewalReminderNotification($service, $invoice, $days));
                        $service->update([$sentColumn => now()]);
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
        }

        $this->info("Sent {$sent} renewal reminder(s).");

        return self::SUCCESS;
    }
}
