<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Suspends active services linked to overdue invoices that have exceeded
 * the configured grace period (billing.suspension_grace_days, default 7).
 *
 * Only suspends via a status update — actual hosting suspension jobs are
 * dispatched separately once the provisioning drivers support it.
 */
class SuspendOverdueServicesCommand extends Command
{
    protected $signature   = 'billing:suspend-overdue';
    protected $description = 'Suspend services whose invoice is overdue beyond the grace period';

    public function handle(): int
    {
        $graceDays = Config::integer('billing.lifecycle.suspend_after', 7);
        $cutoff    = now()->subDays($graceDays)->toDateString();

        $suspended = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->whereNotNull('order_id')
            ->whereDate('due_date', '<', $cutoff)
            ->with('order.items')
            ->each(function (Invoice $invoice) use (&$suspended): void {
                $order = $invoice->order;

                if ($order === null) {
                    return;
                }

                $orderItemIds = $order->items->pluck('id');

                $count = Service::query()
                    ->whereIn('order_item_id', $orderItemIds)
                    ->where('status', ServiceStatus::Active)
                    ->update(['status' => ServiceStatus::Suspended]);

                $suspended += $count;

                if ($count > 0) {
                    activity('service')
                        ->withProperties([
                            'invoice_id' => $invoice->id,
                            'order_id'   => $order->id,
                            'count'      => $count,
                        ])
                        ->log('service.suspended_overdue');
                }
            });

        $this->info("Suspended {$suspended} service(s) for non-payment.");

        return self::SUCCESS;
    }
}
