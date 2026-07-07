<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Notifications\MaintenanceWindowNotification;
use Illuminate\Console\Command;

class SendMaintenanceRemindersCommand extends Command
{
    protected $signature   = 'maintenance:send-reminders';
    protected $description = 'Send email notifications for maintenance windows starting within 24 hours.';

    public function handle(): int
    {
        $windows = MaintenanceWindow::query()
            ->where('is_active', true)
            ->whereNull('customers_notified_at')
            ->where('starts_at', '<=', now()->addHours(24))
            ->where('starts_at', '>', now())
            ->get();

        if ($windows->isEmpty()) {
            $this->line('No upcoming maintenance windows to notify about.');
            return self::SUCCESS;
        }

        // All users with a customer profile (i.e., customers)
        $customers = User::query()
            ->whereHas('customer')
            ->get();

        foreach ($windows as $window) {
            $notified = 0;

            foreach ($customers as $user) {
                $user->notify(new MaintenanceWindowNotification($window));
                $notified++;
            }

            $window->update(['customers_notified_at' => now()]);

            $this->info("Notified {$notified} customers about maintenance: {$window->title}");
        }

        return self::SUCCESS;
    }
}
