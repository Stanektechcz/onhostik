<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\QueueHealthAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Alerts admins when the queue backs up or accumulates failures (audit 500 #44).
 * Scheduled; thresholds in config/queue.php ('health'). Complements the per-job
 * failure alert by catching a stalled worker (jobs piling up, nothing draining).
 */
final class CheckQueueHealthCommand extends Command
{
    protected $signature = 'queue:health-check';

    protected $description = 'Alert admins if the queue is backing up or accumulating failures.';

    public function handle(): int
    {
        $pending    = (int) DB::table('jobs')->count();
        $failed     = (int) DB::table('failed_jobs')->count();
        $maxPending = (int) config('queue.health.max_pending', 500);
        $maxFailed  = (int) config('queue.health.max_failed', 25);

        if ($pending <= $maxPending && $failed <= $maxFailed) {
            $this->info("Queue healthy — {$pending} pending, {$failed} failed.");

            return self::SUCCESS;
        }

        $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new QueueHealthAlertNotification($pending, $failed, $maxPending, $maxFailed));
        }

        $this->warn("Queue alert sent — {$pending} pending (limit {$maxPending}), {$failed} failed (limit {$maxFailed}).");

        return self::SUCCESS;
    }
}
