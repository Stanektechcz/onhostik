<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Penpot\PenpotDockerProvider;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';
require_once __DIR__.'/../../Support/Penpot/PenpotDoubles.php';

/*
 * H8 (TASK-0123) — a web hosting customer orders Penpot, pays by card and uses it, touching only the real HTTP routes.
 *
 * sign up → cart (penpot / penpot-team, priced by staff) → quote → order with the card gateway → Comgate callback → the outbox is
 * relayed and the Penpot saga runs against a node DOUBLE (tests/Support/Penpot/PenpotDoubles.php: SSH + SFTP of a Docker/Caddy node)
 * → ACTIVE. Then the card in the panel, the owner's password behind a step-up, a backup, and another organization that reaches none
 * of it. Suspension, cancellation with the final archive and the purge: tests/Feature/Penpot/PenpotLifecycleTest.php. Manual
 * counterpart: docs/manual-tests/11-penpot.md.
 */

beforeEach(function () {
    e2eSeedPlatform();
    e2eComgateEnvironment();
    Http::preventStrayRequests();
});

afterEach(function () {
    PenpotDockerProvider::$shellFactory = null;
    PenpotDockerProvider::$transportFactory = null;
});

it('takes a web hosting customer from the order form to a running Penpot of their own', function () {
    LaravelNotification::fake();
    $node = penpotLab(); // the owner applied 2026-10-penpot, staff priced it and put it on sale; one Penpot node in cz1
    $gate = [];
    e2eComgateFake($gate);
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]); // the breach check of a new password: no match

    [$user, $org, $password] = e2eSignUp($this, 'studio.e2e@example.cz', 'Studio e2e s.r.o.');
    $this->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'penpot', 'plan_key' => 'penpot-team', 'config' => ['label' => 'Návrhy', 'region' => 'cz1']]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk()->assertJsonCount(1, 'data.items');
    $quote = $this->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $this->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and($order->total_minor)->toBeGreaterThan(0)
        ->and(Service::query()->where('organization_id', $org->id)->count())->toBe(0)->and($node->commands)->toBe([]); // nothing before the money
    $gate['total'] = $order->total_minor;

    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending(1000);
    driveOperations();

    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'penpot')->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->label)->toBe('Návrhy')->and($node->matching('create-profile --email \'studio.e2e@example.cz\''))->toHaveCount(1);

    // the card: where to open it and with which e-mail; no key, no node, no vendor of the infrastructure
    $card = $this->withHeaders(e2eHeaders('card'))->getJson("/v1/services/{$service->id}/penpot")->assertOk();
    expect($card->json('data.open_url'))->toBe('https://'.$service->hostname)->and($card->json('data.owner_email'))->toBe('studio.e2e@example.cz');
    foreach (['penpot-cz1', '198.51.100.20', 'caddy', 'docker', 'secret_key', 'db_password', '/srv/onhost-penpot'] as $needle) {
        expect(stripos((string) $card->getContent(), $needle))->toBeFalse("the card names '{$needle}'");
    }

    // the owner's password: refused without a fresh step-up, set with one, and the node is the only place it reaches
    $this->withHeaders(e2eHeaders('pw-1'))->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => 'Studio-Penpot-2026'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    $this->travel(61)->seconds(); // the saga moved the clock: sign in again, as the customer would, then confirm with the password
    $this->withHeaders(e2eHeaders('login'))->postJson('/v1/auth/login', ['email' => 'studio.e2e@example.cz', 'password' => $password])->assertOk();
    e2eStepUp($this, $password);
    $set = $this->withHeaders(e2eHeaders('pw-2'))->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => 'Studio-Penpot-2026'])->assertStatus(202);
    expect(driveOperation(Operation::query()->findOrFail($set->json('operation_id')))->state)->toBe(Operation::SUCCEEDED)
        ->and($node->matching("update-profile --email 'studio.e2e@example.cz' < "))->toHaveCount(1)
        ->and($node->matching('Studio-Penpot-2026'))->toBe([]); // the password went to the node in a 0600 file on stdin, never on a command line

    // a backup through the ordinary service action
    $backup = $this->withHeaders(e2eHeaders('backup'))->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup', 'params' => ['kind' => 'manual']])->assertStatus(202);
    expect(driveOperation(Operation::query()->findOrFail($backup->json('operation_id')))->state)->toBe(Operation::SUCCEEDED);

    // another organization reaches none of it
    [$stranger, $other] = $this->customerWithOrganization(['email' => 'cizi.e2e@example.cz'], ['name' => 'Cizí s.r.o.']);
    $this->app['auth']->forgetGuards();
    $this->defaultCookies = []; // the owner's browser session stays behind: the stranger comes with a token of their own
    $this->unencryptedCookies = [];
    $this->withCredentials = false;
    $this->flushHeaders();
    $this->actingAs($stranger, 'sanctum');
    $this->withHeaders(['X-Organization' => $other->id])->getJson("/v1/services/{$service->id}/penpot")->assertNotFound();
    $this->withHeaders(['X-Organization' => $other->id, 'Idempotency-Key' => 'e2e-stranger-pw'])->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => implode('-', ['Cizi', 'Heslo', '2026', 'x'])])->assertNotFound();
});
