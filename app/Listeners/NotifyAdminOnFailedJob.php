<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Communication\Services\CriticalAlertDispatcher;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Mail\Message;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an email to every admin user when a queued job fails.
 * Rate-limited to one email per unique job+exception pair per hour
 * via the cache, to avoid alert storms on flapping jobs.
 */
class NotifyAdminOnFailedJob
{
    public function handle(JobFailed $event): void
    {
        $jobName  = class_basename($event->job->resolveName());
        $errorMsg = mb_substr($event->exception->getMessage(), 0, 500);

        $cacheKey = 'failed_job_alert:' . md5($jobName . $errorMsg);

        if (cache()->has($cacheKey)) {
            return; // already alerted for this failure within the hour
        }

        cache()->put($cacheKey, true, now()->addHour());

        /*
         | Slack first (audit I126). A provisioning job dying at 02:00 is the
         | textbook case for an alert that does not wait for someone to open
         | their inbox — and it is dispatched before the mail loop so a broken
         | mailer cannot swallow it.
         */
        app(CriticalAlertDispatcher::class)->send(
            "Selhání jobu: {$jobName}",
            $errorMsg,
            ['Queue' => $event->job->getQueue(), 'Job' => $jobName],
        );

        $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();

        foreach ($admins as $admin) {
            try {
                Mail::raw(
                    "Selhání queued jobu na OnHost.cz\n\n" .
                    "Job: {$jobName}\n" .
                    "Queue: {$event->job->getQueue()}\n" .
                    "Chyba: {$errorMsg}\n\n" .
                    "Zobrazit v Horizon dashboard: " . url('/horizon') . "\n",
                    function (Message $msg) use ($admin, $jobName): void {
                        $msg->to($admin->email)
                            ->subject("[OnHost] Selhání jobu: {$jobName}");
                    }
                );
            } catch (\Throwable $e) {
                Log::error('NotifyAdminOnFailedJob: failed to send alert', [
                    'admin' => $admin->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
