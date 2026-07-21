<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Shared\Support\LogContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Expires unpaid proforma orders past the validity window (audit P195).
 *
 * Without this an order whose proforma is never paid sits in `Pending`
 * forever — it clutters the pipeline, keeps showing "awaiting payment" in the
 * customer's list, and skews every "open orders" metric. The proforma validity
 * window already exists for invoices; this applies the same deadline to the
 * order and moves it to the terminal `Expired` state.
 *
 * Deliberately conservative: only orders that are still `Pending` AND whose
 * every invoice is unpaid are touched. An order that reached `Processing`
 * (payment landed, provisioning running) is never expired out from under a
 * paying customer.
 */
class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature   = 'billing:expire-unpaid-orders {--dry-run : List what would expire without changing anything}';
    protected $description = 'Move unpaid proforma orders past their validity window to the Expired state.';

    public function handle(): int
    {
        $days     = (int) config('billing.proforma_validity_days', 10);
        $deadline = now()->subDays($days);
        $dryRun   = (bool) $this->option('dry-run');

        $orders = Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->whereNull('paid_at')
            ->where('created_at', '<', $deadline)
            // Never expire an order that has a paid invoice — belt and braces
            // on top of the paid_at check, since a manual payment could set one
            // without touching the order row.
            ->whereDoesntHave('invoices', function ($q): void {
                $q->where('status', InvoiceStatus::Paid->value);
            })
            ->get();

        if ($orders->isEmpty()) {
            $this->line('No unpaid orders past the validity window.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($orders as $order) {
            if ($dryRun) {
                $this->line("Would expire order #{$order->id} (created {$order->created_at->format('Y-m-d')}).");

                continue;
            }

            LogContext::with(
                ['order_id' => $order->id, 'customer_id' => $order->customer_id],
                function () use ($order): void {
                    DB::transaction(function () use ($order): void {
                        $order->update(['status' => OrderStatus::Expired]);

                        // Cancel the still-open proformas so they stop appearing
                        // as payable — an expired order must not remain payable.
                        $order->invoices()
                            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
                            ->update(['status' => InvoiceStatus::Cancelled->value]);
                    });

                    activity('billing')
                        ->performedOn($order)
                        ->withProperties(['reason' => 'proforma_validity_expired'])
                        ->log('order.expired');
                },
            );

            $count++;
        }

        $this->info($dryRun ? "{$orders->count()} orders would expire." : "Expired {$count} unpaid orders.");

        return self::SUCCESS;
    }
}
