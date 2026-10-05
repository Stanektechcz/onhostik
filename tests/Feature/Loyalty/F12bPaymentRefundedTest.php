<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Loyalty\LoyaltyClawback;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Notifications\Lexicon;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * F12b: PaymentService::refund gives a payment back to its source without a credit note, so nothing was published and the
 * points the payment earned (payment.on_time — an order payment or a credit top-up, R6) stayed. It publishes
 * `payment.refunded` now; the customer is told, and loyalty takes the payment's points back once what is left of the payment
 * would not have earned them (refunded in full, or below the 100 CZK minimum). Once, never more than the payment earned,
 * never from another organization — and a credit note on the same payment does not take them a second time.
 */

beforeEach(fn () => $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]));

/** A settled credit top-up by bank transfer (refunds stay pending for a manual payout: nothing leaves the test) and its points. */
function f12bPaidTopup(Organization $org, int $minor): PaymentIntent
{
    $intent = PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => 'bank', 'provider_id' => 'vs-'.uniqid(), 'purpose' => 'topup', 'reference_type' => 'wallet', 'reference_id' => $org->id, 'amount_minor' => $minor, 'currency' => 'CZK', 'state' => 'SUCCEEDED', 'idempotency_key' => 'f12b-pi-'.uniqid(), 'paid_at' => now()]);
    app(OutboxPublisher::class)->publish(GenericEvent::of('payment.succeeded', 'payment', $intent->id, ['purpose' => 'topup', 'reference' => ['wallet', $org->id], 'amount' => ['minor' => $minor, 'currency' => 'CZK']], $org->id));
    app(OutboxPublisher::class)->relayPending();

    return $intent;
}

function f12bRefund(PaymentIntent $intent, int $minor, string $key): void
{
    app(PaymentService::class)->refund($intent->fresh(), Money::minor($minor, 'CZK'), 'Vrácení na žádost zákazníka', $key, CommandContext::system('test')->withScope($intent->organization_id));
    app(OutboxPublisher::class)->relayPending();
}

it('publishes payment.refunded, tells the customer and takes the payment\'s points back once', function () {
    [, $org] = $this->customerWithOrganization();
    $loyalty = app(LoyaltyService::class);
    $intent = f12bPaidTopup($org, 50000);
    expect($loyalty->points($org->id))->toBe(10);

    f12bRefund($intent, 50000, 'f12b-rf-1');

    $event = OutboxMessage::query()->where('name', 'payment.refunded')->where('aggregate_id', $intent->id)->sole();
    expect($event->organization_id)->toBe($org->id)->and(data_get($event->payload, 'amount.minor'))->toBe(50000)->and(data_get($event->payload, 'full'))->toBeTrue()
        ->and($loyalty->points($org->id))->toBe(0)
        ->and(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyClawback::PAYMENT_RULE)->where('reference', $intent->id)->sum('points'))->toBe(-10)
        ->and(Notification::query()->where('audience', 'customer')->where('event', 'payment.refunded')->where('organization_id', $org->id)->count())->toBe(1);

    // a redelivered event, and the same refund asked again, take nothing more
    app(OutboxPublisher::class)->publish(GenericEvent::of('payment.refunded', 'payment', $intent->id, (array) $event->payload, $org->id));
    app(OutboxPublisher::class)->relayPending();
    f12bRefund($intent, 50000, 'f12b-rf-1');
    expect(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyClawback::PAYMENT_RULE)->count())->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'payment.refunded')->count())->toBe(2); // the redelivery above; the repeated refund published nothing
});

it('keeps the points while what is left of the payment would still have earned them', function () {
    [, $org] = $this->customerWithOrganization();
    $loyalty = app(LoyaltyService::class);
    $intent = f12bPaidTopup($org, 50000);

    f12bRefund($intent, 30000, 'f12b-rf-part-1'); // 200 CZK left: still at least the 100 CZK minimum
    expect($loyalty->points($org->id))->toBe(10);

    f12bRefund($intent, 15000, 'f12b-rf-part-2'); // 50 CZK left: below the minimum, the payment would have earned nothing
    expect($loyalty->points($org->id))->toBe(0);
});

it('never takes points of another organization, nor more than the payment earned', function () {
    [, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $loyalty = app(LoyaltyService::class);
    $intent = f12bPaidTopup($org, 50000);
    $loyalty->award($other->id, 'mfa.enabled', 'org', 50, 'MFA', CommandContext::system('test'));
    $intent->forceFill(['refunded_minor' => 50000, 'state' => 'REFUNDED'])->save();

    // an event that names the other organization for this payment takes nothing from either
    app(OutboxPublisher::class)->publish(GenericEvent::of('payment.refunded', 'payment', $intent->id, ['amount' => ['minor' => 50000, 'currency' => 'CZK'], 'full' => true], $other->id));
    app(OutboxPublisher::class)->relayPending();
    expect($loyalty->points($org->id))->toBe(10)->and($loyalty->points($other->id))->toBe(50);

    // the payment's own organization: exactly what it earned, the other points of the organization stay
    $loyalty->award($org->id, 'mfa.enabled', 'org', 50, 'MFA', CommandContext::system('test'));
    expect(app(LoyaltyClawback::class)->onPaymentRefunded($org->id, $intent->id, CommandContext::system('test')))->toBe(10)
        ->and(app(LoyaltyClawback::class)->onPaymentRefunded($org->id, $intent->id, CommandContext::system('test')))->toBe(0)
        ->and($loyalty->points($org->id))->toBe(50);
});

it('tells an English organization in English', function () {
    [, $org] = $this->customerWithOrganization(['locale' => 'en'], ['locale' => 'en']);
    $intent = f12bPaidTopup($org, 50000);

    f12bRefund($intent, 50000, 'f12b-rf-en');

    $row = Notification::query()->where('event', 'payment.refunded')->where('organization_id', $org->id)->sole();
    expect($row->title)->toBe('Payment refunded')->and(Lexicon::untranslated((string) $row->title.' '.$row->body))->toBe([]);
});

it('refuses a refund key already spent on another payment instead of answering with that payment\'s refund', function () {
    [, $org] = $this->customerWithOrganization();
    $first = f12bPaidTopup($org, 50000);
    $second = f12bPaidTopup($org, 50000);
    f12bRefund($first, 50000, 'f12b-rf-shared');

    expect(fn () => f12bRefund($second, 50000, 'f12b-rf-shared'))->toThrow(DomainError::class, 'already used for another refund');
    expect($second->fresh()->refunded_minor)->toBe(0)->and(OutboxMessage::query()->where('name', 'payment.refunded')->count())->toBe(1);
});
