<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\Website;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E1 — a stranger becomes a customer with a running web hosting, touching only the real HTTP routes.
 *
 * sign up (POST /v1/auth/register) → verify e-mail (POST /v1/auth/verify-email, token from the mail) → browse the catalogue →
 * cart (PUT /v1/cart) → quote → order with the card gateway (POST /v1/orders) → Comgate callback to the real webhook route →
 * the order settles → the outbox is relayed and the provisioning saga runs against a STATEFUL fake ISPConfig → the service is
 * active and visible in the client panel API with its features; the invoice exists and is paid.
 *
 * Only the edges are doubles: the payment gateway and the hosting panel (Http::fake), the mail (Notification::fake). Everything
 * between — validation, session, cart, quote, command bus, payments, ledger, outbox, notification router, saga — is the product.
 * Shared helpers live in tests/Support/E2E/E2EHelpers.php (e2e* functions) for the other E-flows. Asserts do not depend on row
 * order, so the flow holds on SQLite and PostgreSQL alike.
 */

beforeEach(function () {
    e2eSeedPlatform();
    e2eWebInfrastructure();
    e2eComgateEnvironment();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
});

it('takes a new customer from the sign-up form to an active web hosting, paid through the card gateway', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    e2ePanelAndGateway($panel, $gate);

    // 1. sign up — an account, an organization of its own and a signed-in session in one answer
    $register = $this->withHeaders(e2eHeaders('register'))->postJson('/v1/auth/register', [
        'name' => 'Jana Nováková', 'email' => 'jana.e2e@example.cz', 'password' => 'Correct-Horse-Battery-9-Staple', 'organization' => 'Skladomat s.r.o.',
        'type' => 'company', 'ico' => '12345678', 'country' => 'CZ', 'terms' => true,
    ])->assertCreated()->assertJsonPath('data.user.email', 'jana.e2e@example.cz')->assertJsonPath('data.organization.role', 'owner');
    $user = User::query()->where('email', 'jana.e2e@example.cz')->firstOrFail();
    $org = Organization::query()->where('owner_user_id', $user->id)->firstOrFail();
    expect($user->email_verified_at)->toBeNull();

    // 2. verify the e-mail with the token that went out by mail — and only once
    $token = e2eVerificationToken($user);
    $this->withHeaders(e2eHeaders('verify'))->postJson('/v1/auth/verify-email', ['token' => $token])->assertOk()->assertJsonPath('data.verified', true);
    $this->withHeaders(e2eHeaders('verify-again'))->postJson('/v1/auth/verify-email', ['token' => $token])->assertUnprocessable()->assertJsonPath('error', 'verify_token_invalid');
    expect($user->refresh()->email_verified_at)->not->toBeNull();
    $this->withHeaders(e2eHeaders('me'))->getJson('/v1/me')->assertOk()->assertJsonPath('data.user.id', $user->id);

    // 3. browse the catalogue
    $catalog = $this->getJson('/v1/catalog')->assertOk();
    expect(collect($catalog->json('data'))->pluck('key')->all())->toContain('web-hosting');
    $product = $this->getJson('/v1/catalog/web-hosting')->assertOk();
    expect(collect($product->json('data.plans'))->pluck('key')->all())->toContain('start');

    // 4. cart → quote
    $this->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'skladomat-e2e.cz']]], 'commit_months' => 1, 'currency' => 'CZK'])
        ->assertOk()->assertJsonCount(1, 'data.items');
    $quote = $this->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $total = (int) $quote->json('data.total');
    expect($total)->toBeGreaterThan(0)->and($quote->json('data.required_documents'))->toContain('terms');

    // 5. checkout: an order that waits for the card payment
    $placed = $this->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', [
        'quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'],
    ])->assertCreated();
    expect($placed->json('state'))->toBe(OrderStateMachine::PENDING_PAYMENT)->and((string) $placed->json('redirect_url'))->toContain('comgate.cz');
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;
    expect($order->total_minor)->toBe($total)->and(Service::query()->where('organization_id', $org->id)->count())->toBe(0); // nothing is delivered before the money

    // 6. the gateway calls back: the payment is verified against the gateway, credited, and the order settles
    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    expect($order->refresh()->state)->toBe(OrderStateMachine::PAID)
        ->and(Invoice::query()->where('order_id', $order->id)->where('type', 'statement')->value('state'))->toBe(Invoice::PAID);
    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'duplicate'); // a repeated callback changes nothing

    // 7. the outbox is relayed and the provisioning saga runs against the panel
    expect(app(OutboxPublisher::class)->relayPending())->toBeGreaterThan(0);
    driveOperations();

    $order->refresh();
    expect($order->state)->toBe(OrderStateMachine::ACTIVE);
    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'web-hosting')->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->hostname)->toBe('skladomat-e2e.cz');
    $website = Website::query()->where('service_id', $service->id)->firstOrFail();
    expect($website->domain)->toBe('skladomat-e2e.cz')->and($panel['sites'])->toHaveCount(1)->and($panel['clients'])->toHaveCount(1)
        ->and($website->remote_site_id)->toBe(array_key_first($panel['sites']))
        ->and(array_values($panel['sites'])[0]['domain'])->toBe('skladomat-e2e.cz')->and(array_values($panel['sites'])[0]['ssl_letsencrypt'])->toBe('y');

    // 8. what the customer sees in the client panel API
    $services = $this->withHeaders(e2eHeaders('services'))->getJson('/v1/services')->assertOk();
    expect(collect($services->json('data'))->pluck('id')->all())->toBe([$service->id])->and($services->json('data.0.state'))->toBe(ServiceStateMachine::ACTIVE);
    $detail = $this->withHeaders(e2eHeaders('service'))->getJson("/v1/services/{$service->id}")->assertOk();
    expect($detail->json('data.state'))->toBe(ServiceStateMachine::ACTIVE);
    $features = $this->withHeaders(e2eHeaders('features'))->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    expect($features['features'])->toHaveKeys(['databases', 'logs', 'monitoring'])->and($features['actions'])->not->toBeEmpty()
        ->and(json_encode($features))->not->toContain('ispconfig')->not->toContain('ISPConfig');
    $this->withHeaders(e2eHeaders('order-after'))->getJson("/v1/orders/{$order->id}")->assertOk()->assertJsonPath('data.state', OrderStateMachine::ACTIVE);
    $invoices = $this->withHeaders(e2eHeaders('invoices'))->getJson('/v1/invoices')->assertOk();
    $types = collect($invoices->json('data'))->groupBy('type')->map->count()->all();
    expect($types)->toHaveKeys(['statement', 'receipt'])->and(collect($invoices->json('data'))->every(fn (array $row) => $row['state'] === Invoice::PAID))->toBeTrue();

    // 9. events and notifications: said once, to the right organization
    app(OutboxPublisher::class)->relayPending();
    $events = OutboxMessage::query()->where('organization_id', $org->id)->orWhere('name', 'identity.registered')->pluck('name')->countBy()->all();
    foreach (['identity.registered', 'order.paid', 'service.activated', 'order.active'] as $name) {
        expect($events)->toHaveKey($name);
    }
    expect($events['order.paid'])->toBe(1)->and($events['service.activated'])->toBe(1)->and($events['order.active'])->toBe(1);
    expect(OutboxMessage::query()->whereIn('name', ['order.paid', 'service.activated'])->whereNull('published_at')->count())->toBe(0);
    expect(Operation::query()->where('service_id', $service->id)->whereNotIn('state', [Operation::SUCCEEDED])->count())->toBe(0);
    expect(Notification::query()->where('organization_id', $org->id)->count())->toBeGreaterThan(0);
});

/** The panel and the gateway share one Http fake stack: each answers its own host and passes on the rest. */
function e2ePanelAndGateway(array &$panel, array &$gate): void
{
    e2eIspPanel($panel);
    e2eComgateFake($gate);
}
