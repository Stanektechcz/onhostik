<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use InvalidArgumentException;

/**
 * Pauses an active service for a given number of days (1–90).
 * Sets status → Suspended and records paused_at / paused_until so the
 * service can be resumed cleanly via ResumeSubscriptionAction.
 */
final class PauseSubscriptionAction
{
    public function execute(Service $service, int $days, string $reason = ''): void
    {
        if ($service->status !== ServiceStatus::Active) {
            throw new InvalidArgumentException(
                "Service [{$service->id}] is not active — cannot pause (status: {$service->status->value})."
            );
        }

        if ($days < 1 || $days > 90) {
            throw new InvalidArgumentException('Pause duration must be between 1 and 90 days.');
        }

        $pausedUntil = now()->addDays($days)->toDateString();

        $service->update([
            'status'            => ServiceStatus::Suspended,
            'suspended_at'      => now(),
            'suspension_reason' => $reason !== '' ? $reason : 'Pozastaveno zákazníkem',
            'paused_at'         => now(),
            'paused_until'      => $pausedUntil,
        ]);

        activity('billing')
            ->performedOn($service)
            ->withProperties([
                'days'         => $days,
                'paused_until' => $pausedUntil,
            ])
            ->log('service.paused');
    }
}
