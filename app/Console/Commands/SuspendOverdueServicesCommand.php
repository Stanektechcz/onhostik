<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Dispatches a real suspend job for active services linked to overdue
 * invoices that have exceeded the configured grace period
 * (billing.lifecycle.suspend_after, default 7 days).
 *
 * The status transition itself happens inside ChangeServiceStateJob, which
 * also calls the provisioning driver's suspend() — this command only finds
 * candidates and dispatches; it never flips Service.status directly, so the
 * real hosting account is actually suspended, not just the DB row.
 */
class SuspendOverdueServicesCommand extends Command
{
    protected $signature   = 'billing:suspend-overdue';
    protected $description = 'Dispatch suspend jobs for services whose invoice is overdue beyond the grace period';

    public function handle(): int
    {
        $graceDays = Config::integer('billing.lifecycle.suspend_after', 7);
        $cutoff    = now()->subDays($graceDays)->toDateString();

        $dispatched = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->whereNotNull('order_id')
            ->whereDate('due_date', '<', $cutoff)
            ->with('order.items')
            ->each(function (Invoice $invoice) use (&$dispatched): void {
                $order = $invoice->order;

                if ($order === null) {
                    return;
                }

                $orderItemIds = $order->items->pluck('id');

                $services = Service::query()
                    ->whereIn('order_item_id', $orderItemIds)
                    ->where('status', ServiceStatus::Active)
                    ->get();

                foreach ($services as $service) {
                    ChangeServiceStateJob::dispatch($service->id, 'suspend', 'overdue_invoice');
                    $dispatched++;
                }

                if ($services->isNotEmpty()) {
                    activity('service')
                        ->withProperties([
                            'invoice_id' => $invoice->id,
                            'order_id'   => $order->id,
                            'count'      => $services->count(),
                        ])
                        ->log('service.suspend_dispatched');
                }
            });

        $this->info("Dispatched suspend job for {$dispatched} service(s) for non-payment.");

        return self::SUCCESS;
    }
}
