<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Notifications\MaintenanceWindowNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SendMaintenanceRemindersCommand extends Command
{
    protected $signature   = 'maintenance:send-reminders';
    protected $description = 'Send email notifications for maintenance windows starting within 24 hours.';

    public function handle(): int
    {
        $windows = MaintenanceWindow::query()
            ->where('is_active', true)
            // A window created with notifications switched off must stay quiet.
            // The old query ignored this flag entirely, so the admin's choice
            // did nothing.
            ->where('notify_customers', true)
            ->whereNull('customers_notified_at')
            ->where('starts_at', '<=', now()->addHours(24))
            ->where('starts_at', '>', now())
            ->get();

        if ($windows->isEmpty()) {
            $this->line('No upcoming maintenance windows to notify about.');

            return self::SUCCESS;
        }

        foreach ($windows as $window) {
            $recipients = $this->recipientsFor($window);

            foreach ($recipients as $user) {
                $user->notify(new MaintenanceWindowNotification($window));
            }

            $window->update(['customers_notified_at' => now()]);

            $scope = $window->server_id === null ? 'all customers' : "server #{$window->server_id}";
            $this->info("Notified {$recipients->count()} customers ({$scope}) about: {$window->title}");
        }

        return self::SUCCESS;
    }

    /**
     * Only the customers this window actually affects (audit I129).
     *
     * Previously every customer was mailed about every window, including
     * maintenance on a single server they had nothing on. Downtime notices
     * that mostly do not apply to you are the fastest way to teach people to
     * ignore downtime notices.
     *
     * @return Collection<int, User>
     */
    private function recipientsFor(MaintenanceWindow $window): Collection
    {
        $query = User::query()->whereHas('customer');

        if ($window->server_id !== null) {
            $query->whereHas('customer.services', function ($services) use ($window): void {
                // Terminated and failed services are not going to notice an
                // outage; suspended ones still belong to a paying customer
                // who may be about to settle up, so they stay in scope.
                $services->where('server_id', $window->server_id)
                    ->whereNotIn('status', [ServiceStatus::Terminated, ServiceStatus::Failed]);
            });
        }

        /** @var Collection<int, User> $users */
        $users = $query->get();

        return $users;
    }
}
