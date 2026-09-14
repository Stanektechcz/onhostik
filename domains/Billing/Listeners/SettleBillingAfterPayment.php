<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Listeners;

use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Billing\RatingService;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * Money arrived (`onhost.invoice.paid`, `onhost.wallet.topup.completed`): close the
 * matching dunning cases, charge deferred usage and retry past-due renewals.
 */
final class SettleBillingAfterPayment
{
    public function __construct(private readonly DunningService $dunning, private readonly RatingService $rating, private readonly SubscriptionService $subscriptions) {}

    public function handle(OutboxMessage $message): void
    {
        $organizationId = $message->organization_id;
        if ($organizationId === null) {
            return;
        }
        $context = CommandContext::system("settle after {$message->name}")->withScope($organizationId);
        if ($message->name === 'invoice.paid') {
            $this->dunning->resolve($organizationId, $message->aggregate_id, null, $context);

            return;
        }
        $this->rating->chargeDeferred($organizationId, $context);
        $this->subscriptions->retryPastDue($organizationId, $context);
    }
}
