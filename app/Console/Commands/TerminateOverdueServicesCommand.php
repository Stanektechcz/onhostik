<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Last-resort dunning step: terminates suspended services whose invoice
 * has been overdue beyond billing.lifecycle.terminate_after (default 30 days).
 *
 * Execution flow:
 *   D+0  invoice due
 *   D+7  suspend (billing:suspend-overdue)
 *   D+30 terminate (this command)
 *
 * Safety: only targets services already in 'suspended' status, so it can
 * never accidentally terminate active services that missed a billing cycle.
 */
class TerminateOverdueServicesCommand extends Command
{
    protected $signature   = 'billing:terminate-overdue';
    protected $description = 'Terminate suspended services whose invoice is overdue beyond the terminate threshold';

    public function handle(): int
    {
        $terminateDays = Config::integer('billing.lifecycle.terminate_after', 30);
        $cutoff        = now()->subDays($terminateDays)->toDateString();
        $dispatched    = 0;

        Service::query()
            ->where('status', ServiceStatus::Suspended)
            ->whereHas('orderItem.order.invoices', function ($q) use ($cutoff): void {
                $q->where('status', InvoiceStatus::Overdue)
                  ->whereDate('due_date', '<', $cutoff)
                  ->where(function ($q2): void {
                      $q2->whereNull('dunning_paused_until')
                         ->orWhere('dunning_paused_until', '<', now());
                  });
            })
            ->each(function (Service $service) use (&$dispatched): void {
                ChangeServiceStateJob::dispatch($service->id, 'terminate', 'overdue_invoice')
                    ->onQueue(config('provisioning.queues.default', 'provisioning'));
                $dispatched++;

                $this->warn("Terminating service #{$service->id} ({$service->label}) — overdue beyond threshold");
            });

        $this->info("Dispatched {$dispatched} terminate job(s).");

        return self::SUCCESS;
    }
}
