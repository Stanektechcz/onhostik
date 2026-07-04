<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use InvalidArgumentException;

/**
 * Resumes a service that was paused via PauseSubscriptionAction.
 * Clears all pause fields and restores status to Active.
 */
final class ResumeSubscriptionAction
{
    public function execute(Service $service): void
    {
        if (!$service->isPaused()) {
            throw new InvalidArgumentException(
                "Service [{$service->id}] is not paused — cannot resume."
            );
        }

        $service->update([
            'status'            => ServiceStatus::Active,
            'suspended_at'      => null,
            'suspension_reason' => null,
            'paused_at'         => null,
            'paused_until'      => null,
        ]);

        activity('billing')
            ->performedOn($service)
            ->log('service.resumed');
    }
}
