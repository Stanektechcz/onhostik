<?php

declare(strict_types=1);

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionExpiryNotices;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * N18 (variant C): a service that is not to renew is announced 14/7/1 days before its paid period ends — a transactional
 * notice through the outbox, once per window, to the subscription's own organization only. Renewing subscriptions and
 * domains get nothing from this pass.
 */

function n18ExpirySubscription(Organization $org, string $label, int $days, bool $autoRenew, bool $cancel = false): Subscription
{
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'label' => $label, 'hostname' => "{$label}.example.test", 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard', 'activated_at' => now()]);

    return Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 10000, 'state' => 'active', 'auto_renew' => $autoRenew, 'cancel_at_period_end' => $cancel, 'current_period_start' => now()->subMonth()->addDays($days), 'current_period_end' => now()->addDays($days)->subHour(), 'next_renewal_at' => now()->addDays($days)->subHour()]);
}

it('tells only the owning organization, once per window, that a non-renewing service ends', function () {
    [, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $ending = n18ExpirySubscription($org, 'ending', 7, false);
    n18ExpirySubscription($org, 'renewing', 7, true); // renews: nothing to announce
    n18ExpirySubscription($org, 'later', 30, false); // outside the horizon
    $cancelled = n18ExpirySubscription($other, 'cancelled', 1, true, true);

    expect(app(SubscriptionExpiryNotices::class)->run())->toBe(['checked' => 2, 'notified' => 2]);
    expect(app(SubscriptionExpiryNotices::class)->run())->toBe(['checked' => 2, 'notified' => 0]); // same window: no repeat

    $event = OutboxMessage::query()->where('name', 'subscription.expiring')->where('aggregate_id', $ending->id)->sole();
    expect($event->organization_id)->toBe($org->id)->and($event->payload['days'])->toBe(7)->and($event->payload['window'])->toBe(7)
        ->and($event->payload['reason'])->toBe('auto_renew_off');
    $urgent = OutboxMessage::query()->where('name', 'subscription.expiring')->where('aggregate_id', $cancelled->id)->sole();
    expect($urgent->organization_id)->toBe($other->id)->and($urgent->payload['window'])->toBe(1)->and($urgent->payload['reason'])->toBe('cancelled');

    app(OutboxPublisher::class)->relayPending();
    // tenant isolation: each organization sees its own service only
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'subscription.expiring')->count())->toBe(1)
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'like', '%cancelled%')->exists())->toBeFalse()
        ->and(Notification::query()->where('organization_id', $other->id)->where('event', 'subscription.expiring')->where('title', 'like', '%cancelled%')->exists())->toBeTrue();
    $mail = MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'service-expiring')->sole();
    expect($mail->vars['sluzba'])->toBe('ending')->and($mail->vars['dni'])->toBe('7');
    expect(MailOutbox::query()->where('organization_id', $other->id)->where('template_key', 'service-expiring')->sole()->vars['sluzba'])->toBe('cancelled');

    // the next, more urgent window sends one more notice
    $this->travel(6)->days();
    expect(app(SubscriptionExpiryNotices::class)->run()['notified'])->toBe(1);
    expect(OutboxMessage::query()->where('name', 'subscription.expiring')->where('aggregate_id', $ending->id)->count())->toBe(2);
});
