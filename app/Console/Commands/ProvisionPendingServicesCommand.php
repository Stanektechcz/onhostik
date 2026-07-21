<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Actions\SyncOrderCompletionAction;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Provisioning\Actions\EnsureOrderProvisionedAction;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Jobs\CheckProxmoxTaskStatusJob;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Console\Command;
use Throwable;

/**
 * Safety net for provisioning: services that were paid for but are still
 * Pending (because the queue worker never picked up the ProvisionHosting-
 * ServiceJob, or died mid-run) get provisioned here — synchronously, so it
 * works even with no queue worker running. Scheduled every minute.
 *
 * Guards against racing / thrashing the normal queue worker:
 *  - only services Pending for at least 60 s (the worker gets first go),
 *  - skips any service that already has an in-flight provisioning task
 *    (pending / running / retrying) — the worker is on it,
 *  - the job itself is idempotent (no-op once external_id + Active).
 */
class ProvisionPendingServicesCommand extends Command
{
    protected $signature = 'services:provision-pending {--limit=25} {--all : Ignore the 60s grace window}';

    protected $description = 'Provision paid services still stuck in Pending (queue-worker fallback).';

    public function handle(): int
    {
        $grace = $this->option('all') ? now() : now()->subSeconds(60);

        // Phase 0: heal paid orders whose items never got a Service at all
        // (the InvoicePaid provisioning step was lost). Creates + provisions
        // synchronously via the shared action.
        $ensure = app(EnsureOrderProvisionedAction::class);
        $healed = 0;

        Order::where('status', OrderStatus::Processing->value)
            ->whereNotNull('paid_at')
            ->where('created_at', '<=', $grace)
            ->with('items')
            ->get()
            ->each(function (Order $order) use ($ensure, &$healed): void {
                $missing = $order->items->contains(
                    fn ($item): bool => Service::where('order_item_id', $item->id)->doesntExist()
                );

                if ($missing) {
                    $ensure->execute($order, sync: true);
                    $healed++;
                }
            });

        $services = Service::query()
            ->where('status', ServiceStatus::Pending->value)
            ->whereIn('provisioning_driver', [
                ProvisioningDriver::AAPanel->value,
                ProvisioningDriver::Proxmox->value,
                ProvisioningDriver::Pterodactyl->value,
            ])
            ->where('created_at', '<=', $grace)
            // Only paid orders — never provision before the money is in.
            ->whereHas('orderItem.order', fn ($q) => $q->whereNotNull('paid_at'))
            // Not already being worked on by the queue worker.
            ->whereDoesntHave('provisioningTasks', fn ($q) => $q->whereIn('status', [
                TaskStatus::Pending->value,
                TaskStatus::Running->value,
                TaskStatus::Retrying->value,
            ]))
            ->limit((int) $this->option('limit'))
            ->get();

        $ok = 0;
        $failed = 0;

        foreach ($services as $service) {
            try {
                // Synchronous — runs now, independent of any queue worker.
                ProvisionHostingServiceJob::dispatchSync($service->id);
                $ok++;
                $this->line("  provisioned service #{$service->id} ({$service->provisioning_driver?->value})");
            } catch (Throwable $e) {
                $failed++;
                report($e);
                $this->warn("  failed service #{$service->id}: {$e->getMessage()}");
            }
        }

        // Phase 2: complete stuck async tasks (Proxmox submits a task and relies
        // on CheckProxmoxTaskStatusJob to poll it — also queue-dependent).
        $stuckTasks = ProvisioningTask::query()
            ->where('status', TaskStatus::Running->value)
            ->where('created_at', '<=', $grace)
            ->whereHas('service', fn ($q) => $q->where('status', ServiceStatus::Pending->value))
            ->limit((int) $this->option('limit'))
            ->get()
            ->filter(fn (ProvisioningTask $t): bool => ($t->result['pending_task'] ?? false) === true);

        $polled = 0;

        foreach ($stuckTasks as $task) {
            try {
                CheckProxmoxTaskStatusJob::dispatchSync($task->service_id, $task->id);
                $polled++;
                $this->line("  polled async task #{$task->id} (service #{$task->service_id})");
            } catch (Throwable $e) {
                report($e);
                $this->warn("  failed polling task #{$task->id}: {$e->getMessage()}");
            }
        }

        // Phase 3: advance paid orders whose services are all live but which
        // never got completed (e.g. activation happened before the hook, or a
        // sync was missed). SyncOrderCompletionAction is idempotent.
        $sync = app(SyncOrderCompletionAction::class);
        $completed = 0;

        Order::where('status', OrderStatus::Processing->value)
            ->whereNotNull('paid_at')
            ->get()
            ->each(function (Order $order) use ($sync, &$completed): void {
                $before = $order->status;
                $sync->execute($order);
                if ($order->fresh()?->status !== $before) {
                    $completed++;
                }
            });

        $this->info("Fallback provisioning done: {$healed} healed, {$ok} provisioned, {$polled} async-polled, {$completed} orders completed, {$failed} failed.");

        return self::SUCCESS;
    }
}
