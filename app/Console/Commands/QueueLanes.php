<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Platform\QueueLaneHeartbeat;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;

/**
 * The queue lanes this installation expects to run (TASK-0045): every lane whose worker has ever looped here, with its
 * last loop. A lane retired on purpose (its unit disabled for good) is forgotten here so the doctor stops failing it; a
 * lane that runs again is remembered again by its first loop.
 */
final class QueueLanes extends Command
{
    protected $signature = 'onhost:queue:lanes {--forget= : stop expecting this lane (its worker was retired on purpose)}';

    protected $description = 'Queue lanes the doctor expects to run, with their last worker loop; --forget=<lane> for a lane retired on purpose';

    public function handle(QueueLaneHeartbeat $heartbeat, AuditRecorder $audit): int
    {
        $forget = trim((string) ($this->option('forget') ?? ''));
        if ($forget !== '') {
            if (! $heartbeat->forget($forget)) {
                $this->error("No lane {$forget} is expected here. Known: ".(implode(', ', $heartbeat->lanes()) ?: 'none'));

                return self::FAILURE;
            }
            $audit->record(CommandContext::system('cli:queue:lanes'), 'platform.queue.lane.forget', 'succeeded', ['lane' => $forget]); // an alarm switched off is on record
            $this->info("{$forget} is no longer expected; its first loop brings it back.");

            return self::SUCCESS;
        }
        $rows = array_map(fn (string $lane) => [$lane, $heartbeat->lastSeenAt($lane)?->toIso8601String() ?? '—'], $heartbeat->lanes());
        $rows === [] ? $this->info('No worker has looped on this installation yet.') : $this->table(['lane', 'last loop'], $rows);

        return self::SUCCESS;
    }
}
