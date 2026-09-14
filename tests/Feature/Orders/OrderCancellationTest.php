<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Platform\Audit\AuditEvent;

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('voids the proforma and the pending bank payment when a customer cancels an unpaid order', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'profi']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'cancel-test:q1', $ctx)['order'];
    $proforma = Invoice::query()->findOrFail($order->invoice_id);
    $intent = PaymentIntent::query()->findOrFail($order->payment_intent_id);
    expect($proforma->type)->toBe('proforma')->and($proforma->state)->toBe(Invoice::ISSUED)->and($intent->provider)->toBe('bank');

    $this->actingAs($owner, 'sanctum');
    $this->postJson("/v1/orders/{$order->id}/transition", ['to' => 'cancelled', 'reason' => 'změna plánu'])->assertOk()->assertJsonPath('data.state', 'CANCELLED');

    expect($proforma->refresh()->state)->toBe(Invoice::CANCELLED)->and($proforma->cancelled_at)->not->toBeNull()->and($proforma->note)->toContain($order->number)
        ->and($intent->refresh()->state)->toBe('CANCELED')
        ->and(AuditEvent::query()->where('action', 'invoice.cancel')->where('resource_id', $proforma->id)->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'payment.intent.cancel')->where('resource_id', $intent->id)->exists())->toBeTrue();

    // nothing is left to pay: the panel's billing seam has no open document and the list shows the void
    $seam = json_decode(substr($this->get('/surfaces/onhost-panel.js')->assertOk()->getContent(), strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
    expect($seam['billing']['document'])->toBeNull()->and($seam['kpis']['unpaid'])->toEqual(0);
    expect($this->getJson('/v1/invoices')->assertOk()->json('data.0.state'))->toBe(Invoice::CANCELLED);

    // a cancelled order is no longer "unpaid", so the customer guard refuses a second cancellation
    $this->postJson("/v1/orders/{$order->id}/transition", ['to' => 'cancelled'])->assertForbidden();
    expect(Invoice::query()->where('state', Invoice::CANCELLED)->count())->toBe(1);
});
