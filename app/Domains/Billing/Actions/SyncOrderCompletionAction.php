<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Advances a paid order to Active once all of its services are live.
 *
 * Order lifecycle: Pending → (payment) Processing → (provisioning) Active.
 * Provisioning runs per-service and asynchronously, so nothing ever moved
 * the order past Processing — it stayed "Zpracovává se" forever even after
 * every service went live. This action closes that gap: called after each
 * service activation, it flips the order to Active once every service tied
 * to the order's items is Active.
 *
 * Idempotent and safe under concurrency: it only ever transitions
 * Processing → Active, and does so under a row lock.
 */
final class SyncOrderCompletionAction
{
    public function execute(Order $order): void
    {
        // Only a paid, in-provisioning order can complete.
        if ($order->status !== OrderStatus::Processing) {
            return;
        }

        $itemIds = $order->items()->pluck('id');

        if ($itemIds->isEmpty()) {
            return;
        }

        $services = Service::whereIn('order_item_id', $itemIds)->get();

        // Every item must have its service, and every service must be Active.
        if ($services->count() < $itemIds->count()) {
            return;
        }

        if ($services->contains(static fn (Service $s): bool => $s->status !== ServiceStatus::Active)) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Processing) {
                return; // replay / concurrent job already advanced it
            }

            $locked->update(['status' => OrderStatus::Active]);

            activity('order')
                ->performedOn($locked)
                ->withProperties(['transition' => 'activated'])
                ->log('order.activated');
        });
    }
}
