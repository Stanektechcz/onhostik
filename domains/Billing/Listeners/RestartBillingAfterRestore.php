<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Listeners;

use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * A cancellation was taken back (`onhost.service.deletion.cancelled`): the terminate saga had set the subscription
 * CANCELLED and nothing set it back, so an undone cancellation ran unbilled for good (TASK-0025). Its billing starts again
 * here. Carried sites ride on their parent's plan and add-ons are ordered again, so neither is touched. Behind the
 * default-off rule `services.reinstate`.
 */
final class RestartBillingAfterRestore
{
    public function __construct(private readonly ServiceReinstatement $reinstatement, private readonly SubscriptionService $subscriptions) {}

    public function handle(OutboxMessage $message): void
    {
        if ($message->aggregate_type !== 'service' || ! $this->reinstatement->enabled()) {
            return;
        }
        $service = Service::query()->find((string) $message->aggregate_id);
        if ($service === null || $service->family === 'addon' || data_get($service->tags, 'billing') === 'included' || $service->terminate_at !== null) {
            return; // gone, not billed on its own, or scheduled for deletion again since
        }
        if (Withdrawal::query()->where('service_id', $service->id)->exists()) {
            return; // the consumer withdrew and was refunded: staff who bring it back restart its billing deliberately, it never charges them by itself
        }
        try {
            $this->subscriptions->restartAfterRestore($service, CommandContext::system('cancellation taken back')->withScope($service->organization_id), ! $this->reinstatement->refundedSinceCancellation($service));
        } catch (DomainError $e) {
            report($e); // the event is not delivered again to every listener for this; the audit command lists the subscription
        }
    }
}
