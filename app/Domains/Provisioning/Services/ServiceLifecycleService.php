<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Facades\Config;

final class ServiceLifecycleService
{
    public function suspend(Service $service, string $reason = 'manual'): void
    {
        if ($service->status === ServiceStatus::Suspended) {
            return;
        }

        ChangeServiceStateJob::dispatch($service->id, 'suspend', $reason);
    }

    public function unsuspend(Service $service, string $reason = 'manual'): void
    {
        if ($service->status !== ServiceStatus::Suspended) {
            return;
        }

        ChangeServiceStateJob::dispatch($service->id, 'unsuspend', $reason);
    }

    public function terminate(Service $service, string $reason = 'manual'): void
    {
        if ($service->status === ServiceStatus::Terminated) {
            return;
        }

        ChangeServiceStateJob::dispatch($service->id, 'terminate', $reason);
    }

    /**
     * @return array{suspended: int, expiring_soon: int, overdue: int}
     */
    public function lifecycleStats(): array
    {
        $graceDays = Config::integer('billing.lifecycle.suspend_after', 7);

        return [
            'suspended'    => Service::where('status', ServiceStatus::Suspended)->count(),
            'expiring_soon'=> Service::where('status', ServiceStatus::Active)
                ->whereNotNull('next_due_date')
                ->whereDate('next_due_date', '<=', now()->addDays(14)->toDateString())
                ->count(),
            'overdue'      => Service::where('status', ServiceStatus::Active)
                ->whereNotNull('next_due_date')
                ->whereDate('next_due_date', '<', now()->subDays($graceDays)->toDateString())
                ->count(),
        ];
    }
}
