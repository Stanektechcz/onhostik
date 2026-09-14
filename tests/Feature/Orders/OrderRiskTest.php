<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderRiskService;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Order intake pre-check (audit §5f-8): a brand-new account with a disposable mailbox placing its third order in
 * minutes is paid and documented like any order, but its fulfilment waits for staff; a release starts it, a reject
 * cancels it and frees the credit. Ordinary customers never notice the check.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

function riskQuote($org)
{
    return app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
}

function riskConsents(): array
{
    return ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
}

it('scores the signals, holds a suspicious paid order before provisioning and lets staff release or reject it', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'company', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $checkout = app(CheckoutService::class);

    // the first order of a new account with a disposable mailbox: new_account (25) + disposable_email (50) = 75 → held
    $assessment = app(OrderRiskService::class)->assess(riskQuote($org), $org, $owner, $ctx);
    expect($assessment['score'])->toBe(75)->and($assessment['reasons'])->toBe(['new_account', 'disposable_email'])->and($assessment['hold'])->toBeTrue();
    expect(app(OrderRiskService::class)->assess(riskQuote($org), $org, $owner, $ctx, 'auto')['hold'])->toBeFalse(); // the platform's own upgrades are never scored

    $held = $checkout->placeOrder(riskQuote($org), $org, $owner, riskConsents(), ['mode' => 'wallet'], 'risk-1', $ctx)['order']->refresh();
    expect($held->state)->toBe(OrderStateMachine::PAID)->and($held->meta['review']['state'])->toBe('pending')->and($held->wallet_hold_id)->not->toBeNull()->and($held->invoice_id)->not->toBeNull();
    expect(OutboxMessage::query()->where('name', 'order.paid')->where('aggregate_id', $held->id)->exists())->toBeFalse()->and(OutboxMessage::query()->where('name', 'order.review.required')->where('aggregate_id', $held->id)->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', "Objednávka {$held->number} čeká na kontrolu%")->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', "Objednávku {$held->number} ještě kontrolujeme")->exists())->toBeTrue();
    expect($held->items()->where('state', 'pending')->count())->toBe(1); // nothing provisioned

    // the customer sees "checking" without the score; staff see the score and the reasons and the pending queue
    $this->actingAs($owner, 'sanctum');
    expect($this->getJson("/v1/orders/{$held->id}")->assertOk()->json('data.review.state'))->toBe('pending');
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $queue = $this->getJson('/v1/staff/orders?review=pending')->assertOk()->json('data');
    expect($queue)->toHaveCount(1)->and($queue[0]['review']['score'])->toBe(75)->and($queue[0]['review']['reasons'])->toContain('disposable_email')->and($queue[0]['organization'])->toBe('Test s.r.o.');
    $this->postJson("/v1/staff/orders/{$held->id}/review", ['decision' => 'maybe'])->assertStatus(422);

    // release: the fulfilment starts exactly as a payment would have started it
    $this->withHeader('Idempotency-Key', 'rv-1')->postJson("/v1/staff/orders/{$held->id}/review", ['decision' => 'release', 'reason' => 'verified by phone'])->assertOk()->assertJsonPath('review.state', 'released');
    $this->flushHeaders();
    expect(OutboxMessage::query()->where('name', 'order.paid')->where('aggregate_id', $held->id)->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect($held->fresh()->state)->toBeIn([OrderStateMachine::PROVISIONING, OrderStateMachine::ACTIVE, OrderStateMachine::PARTIALLY_ACTIVE, OrderStateMachine::FAILED]);
    $this->postJson("/v1/staff/orders/{$held->id}/review", ['decision' => 'release'])->assertStatus(409)->assertJsonPath('error', 'order_not_under_review');

    // a paid history takes "new account" away and the release taught the check (disposable_email 50 → 45; §5g-4): 45 < 60, not held; a VAT id from another country brings it back (60): held again
    $second = $checkout->placeOrder(riskQuote($org), $org, $owner, riskConsents(), ['mode' => 'wallet'], 'risk-2', $ctx)['order']->refresh();
    expect($second->meta['risk']['score'])->toBe(45)->and($second->meta)->not->toHaveKey('review');
    $org->forceFill(['vat_id' => 'DE811907980'])->save();
    $second = $checkout->placeOrder(riskQuote($org->fresh()), $org->fresh(), $owner, riskConsents(), ['mode' => 'wallet'], 'risk-2b', $ctx)['order']->refresh();
    expect($second->meta['risk']['reasons'])->toBe(['disposable_email', 'vat_country_mismatch'])->and($second->meta['review']['state'])->toBe('pending');

    // reject: the order is cancelled and the credit hold freed; the customer hears why
    $this->withHeader('Idempotency-Key', 'rv-2')->postJson("/v1/staff/orders/{$second->id}/review", ['decision' => 'reject', 'reason' => 'stolen card reported'])->assertOk()->assertJsonPath('state', OrderStateMachine::CANCELLED);
    $this->flushHeaders();
    expect(WalletHold::query()->find($second->wallet_hold_id)?->isActive())->toBeFalse();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', "Objednávku {$second->number} jsme nemohli přijmout")->value('body'))->toBe('stolen card reported');

    // an established customer with a paid history and an ordinary mailbox is not touched
    [$owner2, $org2] = $this->customerWithOrganization(['email' => 'jan@firma.cz'], ['billing_email' => 'jan@firma.cz']);
    $ctx2 = $this->contextFor($owner2, $org2);
    app(WalletService::class)->topup($org2, Money::decimal('5000', 'CZK'), 'card', 'seed-2', $ctx2, bankProvider: 'comgate');
    Order::query()->create(['number' => 'OH-2026-0001', 'organization_id' => $org2->id, 'state' => OrderStateMachine::ACTIVE, 'currency' => 'CZK', 'subtotal_minor' => 100, 'discount_minor' => 0, 'tax_minor' => 21, 'total_minor' => 121, 'payment_mode' => 'wallet', 'source' => 'web', 'commit_months' => 1, 'idempotency_key' => 'old', 'placed_at' => now()->subMonths(3), 'paid_at' => now()->subMonths(3)]);
    $clean = $checkout->placeOrder(riskQuote($org2), $org2, $owner2, riskConsents(), ['mode' => 'wallet'], 'risk-3', $ctx2)['order']->refresh();
    expect($clean->meta)->not->toHaveKey('review')->and($clean->meta['risk']['score'])->toBe(0)->and(OutboxMessage::query()->where('name', 'order.paid')->where('aggregate_id', $clean->id)->exists())->toBeTrue();
});
