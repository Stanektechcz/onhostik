<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Customer\Models\Customer;
use App\Models\User;
use App\Notifications\CustomerRiskAlertNotification;
use Illuminate\Console\Command;

class AlertCustomerRisksCommand extends Command
{
    protected $signature   = 'alerts:customer-risk {--threshold=30 : Health score threshold}';
    protected $description = 'Alert admins when customer health score drops below threshold';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $alerted   = 0;

        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            $this->info('No admin users found.');
            return self::SUCCESS;
        }

        Customer::where('health_score', '<=', $threshold)
            ->where(function ($q): void {
                $q->whereNull('risk_alert_sent_at')
                  ->orWhere('risk_alert_sent_at', '<', now()->subDays(7));
            })
            ->get()
            ->each(function (Customer $customer) use ($admins, &$alerted): void {
                foreach ($admins as $admin) {
                    $admin->notify(new CustomerRiskAlertNotification($customer));
                }
                $customer->update(['risk_alert_sent_at' => now()]);
                $alerted++;
            });

        $this->info("Alerted for {$alerted} at-risk customers.");

        return self::SUCCESS;
    }
}
