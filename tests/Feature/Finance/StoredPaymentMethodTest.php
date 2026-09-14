<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentMethod;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\AutoTopup;
use Onhost\Domain\WalletLedger\Models\AutoTopupSetting;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Stored payment methods (audit §5f-1): a card top-up the customer chose to keep becomes a stored method once the
 * payment settles (the gateway's recurring token, never the card), the automatic top-up charges it without the
 * customer, and the customer sees and removes it in the panel.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'gw-secret-test';
    config()->set('onhost.payments.comgate.merchant', '123456');
});

afterEach(function () {
    unset($_ENV['COMGATE_MERCHANT'], $_ENV['COMGATE_SECRET']);
});

it('keeps the card of a top-up on request, charges it for the renewal guard and lets the customer remove it', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    $calls = [];
    Http::fake(function (Request $request) use (&$calls) {
        if (! str_contains($request->url(), 'comgate.cz')) {
            return null;
        }
        $calls[] = $request->data();
        if (str_ends_with($request->url(), '/payment')) {
            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => isset($request['initRecurringId']) ? 'REC-'.count($calls) : 'INIT-'.count($calls), 'redirect' => isset($request['initRecurringId']) ? null : 'https://payments.comgate.cz/client/instructions/index?id=INIT']);
        }

        return Http::response(['code' => 0, 'message' => 'OK', 'status' => 'PAID', 'price' => 200000, 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2', 'cardNumber' => '4242xxxxxxxx1234'], 200);
    });
    $this->actingAs($owner, 'sanctum');
    expect($this->getJson('/v1/payment-methods')->assertOk()->json('data'))->toBe([]);
    expect(app(AutoTopup::class)->supported())->toBeTrue();

    // a card top-up with "keep the card": the gateway is asked for a recurring-capable payment
    $init = $this->withHeader('Idempotency-Key', 'topup-save-1')->postJson('/v1/payments/init', ['amount' => 2000, 'method' => 'card', 'save_method' => true])->assertCreated()->json();
    expect($init['redirect_url'])->toContain('comgate.cz')->and($calls[0]['initRecurring'])->toBeTrue()->and($calls[0]['method'])->toBe('CARD_CZ_CSOB_2');
    $this->flushHeaders();
    $intent = PaymentIntent::query()->findOrFail($init['payment_intent_id']);
    expect($intent->return_urls['save_method'])->toBeTrue()->and($intent->provider_id)->toBe('INIT-1');

    // the payment settles (status re-read from the gateway): the card becomes the stored method, the customer is told, the token stays encrypted and hidden
    $intent->forceFill(['raw' => ['cardNumber' => '4242xxxxxxxx1234']])->save();
    app(PaymentService::class)->settle($intent, CommandContext::system('test'), 'card');
    $method = PaymentMethod::query()->where('organization_id', $org->id)->firstOrFail();
    expect($method)->toMatchArray(['provider' => 'comgate', 'kind' => 'card', 'last4' => '1234', 'is_default' => true, 'state' => 'active'])->and($method->provider_token)->toBe('INIT-1');
    expect((string) DB::table('payment_methods')->where('id', $method->id)->value('provider_token'))->not->toContain('INIT-1');
    $listed = $this->getJson('/v1/payment-methods')->assertOk()->json();
    expect($listed['data'][0])->toMatchArray(['id' => $method->id, 'last4' => '1234'])->and(json_encode($listed))->not->toContain('INIT-1');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Karta % uložena%')->exists())->toBeTrue();

    // the automatic top-up picks the stored card up and, when the credit runs short before a renewal, charges it without the customer
    $set = $this->putJson('/v1/wallet/auto-topup', ['enabled' => true, 'amount' => 2000, 'threshold' => 500, 'monthly_limit' => 8000])->assertOk()->json();
    expect($set['payment_method_id'])->toBe($method->id)->and($set['supported'])->toBeTrue();
    $service = featureWebService($org, 'aapanel');
    $standard = app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK', 'year');
    Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $standard['version']->id, 'price_id' => $standard['price']->id, 'currency' => 'CZK', 'period' => 'year', 'amount_minor' => 189000,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(360), 'current_period_end' => now()->addDays(5), 'next_renewal_at' => now()->addDays(5), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    expect(app(WalletForecast::class)->renewalGuard())->toMatchArray(['checked' => 1, 'underfunded' => 1, 'topped_up' => 1, 'notified' => 1]);
    $recurring = collect($calls)->first(fn ($c) => isset($c['initRecurringId']));
    expect($recurring['initRecurringId'])->toBe('INIT-1')->and($recurring['prepareOnly'])->toBeTrue()->and($recurring['price'])->toBeGreaterThanOrEqual(200000);
    $charge = PaymentIntent::query()->where('organization_id', $org->id)->where('method', 'stored')->firstOrFail();
    expect($charge->provider_id)->toStartWith('REC-')->and($charge->redirect_url)->toBeNull()->and($charge->state)->not->toBe('FAILED');
    expect(AutoTopupSetting::query()->where('organization_id', $org->id)->value('consecutive_failures'))->toBe(0);
    expect(DB::table('provider_calls')->where('instance_key', 'comgate')->pluck('request')->implode(' '))->not->toContain('gw-secret-test');

    // removing the card: the automatic top-up keeps its policy but has nothing to charge
    $this->withHeader('Idempotency-Key', 'pm-remove')->deleteJson("/v1/payment-methods/{$method->id}")->assertOk()->assertJsonPath('removed', true)->assertJsonPath('auto_topup.payment_method_id', null);
    $this->flushHeaders();
    expect($this->getJson('/v1/payment-methods')->assertOk()->json('data'))->toBe([])->and($method->fresh()->state)->toBe('removed');
    $this->deleteJson("/v1/payment-methods/{$method->id}")->assertNotFound();
});
