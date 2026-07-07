<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceResourceService;
use App\Models\User;
use App\Notifications\ServiceQuotaBreachNotification;
use Illuminate\Console\Command;

class CheckServiceQuotaBreachesCommand extends Command
{
    protected $signature   = 'monitoring:check-quota-breaches';
    protected $description = 'Notify admins when active services exceed their resource quota threshold.';

    public function handle(ServiceResourceService $resourceService): int
    {
        $cooldownHours = (int) config('provisioning.quota_breach_cooldown_hours', 24);

        $services = Service::query()
            ->whereIn('status', [ServiceStatus::Active->value])
            ->whereNotNull('last_resource_check_at')
            ->where(function ($q) use ($cooldownHours): void {
                $q->whereNull('quota_breach_alerted_at')
                  ->orWhere('quota_breach_alerted_at', '<=', now()->subHours($cooldownHours));
            })
            ->with('customer.user')
            ->get();

        $alerted = 0;

        foreach ($services as $service) {
            $alerts = $resourceService->checkAlerts($service);

            if (empty($alerts)) {
                continue;
            }

            $notification = new ServiceQuotaBreachNotification($service, $alerts);

            User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->each(fn (User $admin) => $admin->notify($notification));

            $service->update(['quota_breach_alerted_at' => now()]);
            $alerted++;

            $this->line("Alerted: {$service->label} (" . implode(', ', array_keys($alerts)) . ')');
        }

        if ($alerted === 0) {
            $this->line('No quota breaches to report.');
        } else {
            $this->info("Sent quota breach alerts for {$alerted} service(s).");
        }

        return self::SUCCESS;
    }
}
