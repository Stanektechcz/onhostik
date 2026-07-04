<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Provisioning\Models\Service;

/**
 * Marks a service for cancellation at the end of the current billing period.
 * The service keeps running until Service.next_due_date; no immediate suspension.
 */
final class CancelSubscriptionAction
{
    public function execute(Service $service, string $reason = ''): void
    {
        $service->update([
            'cancel_at_period_end' => true,
            'cancellation_reason'  => $reason !== '' ? $reason : null,
        ]);

        activity('billing')
            ->performedOn($service)
            ->withProperties(['cancellation_reason' => $reason])
            ->log('service.cancel_at_period_end_set');
    }
}
