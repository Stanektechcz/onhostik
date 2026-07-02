<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Notifications\DomainExpiringNotification;
use Illuminate\Console\Command;

/**
 * Sends domain-expiring reminder emails at 30d, 14d, and 7d before expiry.
 * Safe to re-run daily — fires once per threshold per domain.
 */
class SendDomainExpiringRemindersCommand extends Command
{
    protected $signature   = 'domains:send-expiring-reminders';
    protected $description = 'Send domain-expiring reminder emails at 30d, 14d, and 7d thresholds';

    public function handle(): int
    {
        $thresholds = [30, 14, 7];
        $sent       = 0;

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            DomainRegistration::query()
                ->whereDate('expires_at', $targetDate)
                ->with(['service.customer.user'])
                ->each(function (DomainRegistration $domain) use ($days, &$sent): void {
                    $user = $domain->service?->customer?->user;

                    if ($user === null) {
                        return;
                    }

                    try {
                        $user->notify(new DomainExpiringNotification($domain, $days));
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
        }

        $this->info("Sent {$sent} domain-expiring reminder(s).");

        return self::SUCCESS;
    }
}
