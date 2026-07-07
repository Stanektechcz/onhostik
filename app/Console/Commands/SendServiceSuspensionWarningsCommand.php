<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\ServiceSuspensionWarningNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Sends a pre-suspension warning to customers whose active services are
 * linked to overdue invoices that will trigger suspension in a few days.
 *
 * The warning threshold is billing.lifecycle.suspension_warning_after (default 3 days)
 * and the actual suspension kicks in at billing.lifecycle.suspend_after (default 7 days).
 * The days-until-suspension count is derived from the difference between both thresholds.
 *
 * Safe to re-run daily — tracked via suspension_warning_sent_at on the invoice.
 */
class SendServiceSuspensionWarningsCommand extends Command
{
    protected $signature   = 'billing:send-suspension-warnings';
    protected $description = 'Send pre-suspension warnings for services whose overdue invoice is approaching the suspend threshold';

    public function handle(): int
    {
        $warnAfterDays   = Config::integer('billing.lifecycle.suspension_warning_after', 3);
        $suspendAfterDays = Config::integer('billing.lifecycle.suspend_after', 7);
        $daysUntil       = max(1, $suspendAfterDays - $warnAfterDays);

        $cutoff = now()->subDays($warnAfterDays)->toDateString();
        $sent   = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->whereNotNull('order_id')
            ->whereNull('suspension_warning_sent_at')
            ->whereDate('due_date', '<', $cutoff)
            ->where(function ($q): void {
                $q->whereNull('dunning_paused_until')
                  ->orWhere('dunning_paused_until', '<', now());
            })
            ->with('order.items')
            ->each(function (Invoice $invoice) use ($daysUntil, &$sent): void {
                $order = $invoice->order;

                if ($order === null) {
                    return;
                }

                $orderItemIds = $order->items->pluck('id');

                $services = Service::query()
                    ->whereIn('order_item_id', $orderItemIds)
                    ->where('status', ServiceStatus::Active)
                    ->with('customer.user')
                    ->get();

                if ($services->isEmpty()) {
                    return;
                }

                foreach ($services as $service) {
                    $user = $service->customer?->user;

                    if ($user === null) {
                        continue;
                    }

                    try {
                        $user->notify(new ServiceSuspensionWarningNotification($service, $invoice, $daysUntil));
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $invoice->update(['suspension_warning_sent_at' => now()]);
            });

        $this->info("Sent {$sent} suspension warning notification(s).");

        return self::SUCCESS;
    }
}
