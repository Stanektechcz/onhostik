<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationRunner;
use Onhost\Domain\Provisioning\OperationService;

/**
 * Queued driver of the OperationRunner. One job per operation tick; the job
 * re-schedules itself while the operation is PENDING/WAITING (on real queues) and
 * relies on `onhost:provisioning:tick` as the safety net. WithoutOverlapping on the
 * service id guarantees two actions never touch the same VM concurrently.
 */
final class RunOperation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly string $operationId) {}

    public function middleware(): array
    {
        $operation = Operation::query()->find($this->operationId);
        $key = $operation?->service_id ?? $operation?->domain_id ?? $this->operationId;

        return [(new WithoutOverlapping('onhost:op:'.$key))->releaseAfter(15)->expireAfter(180)];
    }

    public function handle(OperationRunner $runner, OperationService $operations): void
    {
        $operation = Operation::query()->find($this->operationId);
        if ($operation === null) {
            return;
        }
        $state = $runner->tick($operation, 60);
        $operation->refresh();
        if (in_array($state, [Operation::PENDING, Operation::WAITING], true) && config('queue.default') !== 'sync') {
            $delay = $operation->next_run_at ? max(1, (int) now()->diffInSeconds($operation->next_run_at, false)) : 5;
            $operations->dispatch($operation, min($delay, 600));
        }
    }
}
