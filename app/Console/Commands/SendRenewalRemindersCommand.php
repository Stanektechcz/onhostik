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
 * 14, 7, or 3 days and have an open renewal invoice.
 * Safe to re-run daily — fires once per threshold per service.
 */
class SendRenewalRemindersCommand extends Command
{
    protected $signature   = 'billing:send-renewal-reminders';
    protected $description = 'Send renewal reminder emails at 14d, 7d, and 3d before service expiry';

    public function handle(): int
    {
        $thresholds = [14, 7, 3];
        $sent       = 0;

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            Service::query()
                ->where('status', ServiceStatus::Active)
                ->whereDate('next_due_date', $targetDate)
                ->with(['customer.user'])
                ->each(function (Service $service) use ($days, &$sent): void {
                    // Find the open renewal invoice for this service
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
