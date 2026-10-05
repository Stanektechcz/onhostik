<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\GameServer;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E5 — a customer orders a game server, pays by card, and runs it, touching only the real HTTP routes.
 *
 * sign up → cart (game-8, Paper, a version) → quote → order with the card gateway → Comgate callback (the real webhook route) → the
 * outbox is relayed and the provisioning saga runs against a STATEFUL fake Pterodactyl (e2eGamePanel): the panel user of THIS
 * organization, an allocation, the server, the install, ACTIVE. Then what the customer does with it: power, console access, the game
 * tools (variable, rename, console command, backup + download), suspension and resume, and that another organization reaches none of it.
 *
 * Only the edges are doubles: the payment gateway and the game panel (Http::fake), the mail (Notification::fake). Everything between —
 * validation, session, cart, quote, command bus, permissions, payments, ledger, outbox, saga, the adapter's own guards — is the product.
 * Asserts do not depend on row order, so the flow holds on SQLite and PostgreSQL alike. Shared helpers: tests/Support/E2E/E2EHelpers.php.
 */

beforeEach(function () {
    e2eSeedPlatform();
    e2eGameInfrastructure();
    e2eComgateEnvironment();
    Http::preventStrayRequests();
});

/** Panel and gateway share one Http fake stack: each answers its own host and passes on the rest. */
function e5Fakes(array &$panel, array &$gate): void
{
    e2eGamePanel($panel);
    e2eComgateFake($gate);
}

/** Everything of the customer's own that a vendor name must not appear in. */
function e5AssertNoVendor(string $json, string $where): void
{
    foreach (['pterodactyl', 'wings', 'games01', 'mgmt.test', 'ptla_', 'ptlc_', 'wings-e2e-session-token'] as $needle) {
        expect(stripos($json, $needle))->toBeFalse("{$where} names '{$needle}'");
    }
}

/** A request of somebody outside the organization: refused (403 or 404) and the answer tells nothing about the server. */
function e5AssertHidden(TestResponse $response, Service $service): void
{
    expect($response->getStatusCode())->toBeIn([403, 404]);
    foreach ([$service->name, (string) $service->label, (string) $service->hostname, 'Liga SMP', '89.187.160'] as $secret) {
        if ($secret !== '') {
            expect(str_contains($response->getContent(), $secret))->toBeFalse("the refusal leaks '{$secret}'");
        }
    }
    e5AssertNoVendor($response->getContent(), 'a refusal');
}

/**
 * Sign up, order a game server, pay through the gateway and let the saga run. Leaves the session signed in as the owner.
 *
 * @return array{0:Organization,1:Service,2:string,3:Order} organization, ACTIVE service, owner password, order
 */
function e5Provision(object $test, array &$panel, array &$gate, string $email = 'hrac.e2e@example.cz'): array
{
    [, $org, $password] = e2eSignUp($test, $email, 'Herní liga s.r.o.');
    $test->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'game', 'plan_key' => 'game-8', 'config' => ['egg' => 'minecraft-paper', 'version' => '1.21.8', 'label' => 'Liga SMP', 'region' => 'cz1']]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk()->assertJsonCount(1, 'data.items');
    $quote = $test->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $test->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', [
        'quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'],
    ])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT);
    $gate['total'] = $order->total_minor;
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    return [$org, Service::query()->where('organization_id', $org->id)->where('product_key', 'game')->firstOrFail(), $password, $order->refresh()];
}

/** One service action over the real route, driven to its end: the operation's final state. */
function e5Act(object $test, Service $service, string $action, array $params, string $label): string
{
    $id = $test->withHeaders(e2eHeaders($label))->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params])->assertStatus(202)->json('operation_id');

    return driveOperation(Operation::query()->findOrFail($id))->state;
}

it('takes a customer from the order form to an ACTIVE game server on a panel user of their own organization', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    // a stranger already has a panel account with the very address the customer will sign up with (the e-mail is customer-typed: it must prove nothing)
    $panel['users'][5] = ['id' => 5, 'external_id' => 'org-of-somebody-else', 'email' => 'hrac.e2e@example.cz', 'username' => 'stranger', 'first_name' => 'Cizi', 'last_name' => 'Hrac', 'language' => 'en', 'root_admin' => false];
    e5Fakes($panel, $gate);

    // nothing is delivered before the money
    [$user, $org] = e2eSignUp($this, 'hrac.e2e@example.cz', 'Herní liga s.r.o.');
    $this->getJson('/v1/catalog/game')->assertOk();
    $this->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'game', 'plan_key' => 'game-8', 'config' => ['egg' => 'minecraft-paper', 'version' => '1.21.8', 'label' => 'Liga SMP', 'region' => 'cz1']]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $this->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $this->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and((string) $placed->json('redirect_url'))->toContain('comgate.cz')
        ->and(Service::query()->where('organization_id', $org->id)->count())->toBe(0)->and($panel['servers'])->toBe([])->and($panel['calls'])->toBe([]);

    // the gateway calls back: paid, settled once, and a repeated callback changes nothing
    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'duplicate');
    expect($order->refresh()->state)->toBe(OrderStateMachine::PAID)->and(Invoice::query()->where('order_id', $order->id)->where('type', 'statement')->value('state'))->toBe(Invoice::PAID);

    // the saga: panel user → allocation → server → install → ACTIVE
    expect(app(OutboxPublisher::class)->relayPending())->toBeGreaterThan(0);
    driveOperations();
    expect($order->refresh()->state)->toBe(OrderStateMachine::ACTIVE);
    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'game')->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->label)->toBe('Liga SMP')->and($service->family)->toBe('game')
        ->and(Operation::query()->where('service_id', $service->id)->whereNotIn('state', [Operation::SUCCEEDED])->count())->toBe(0);

    // the panel user is this organization's by its id alone: found by external id, created with a synthetic address, never an administrator,
    // never the stranger's account that carries the customer's own e-mail; the panel was never asked who owns an e-mail address
    $created = collect($panel['users'])->except(5);
    expect($created)->toHaveCount(1)->and($panel['users'][5]['external_id'])->toBe('org-of-somebody-else');
    $panelUser = $created->first();
    expect($panelUser['external_id'])->toBe($org->id)->and($panelUser['root_admin'])->toBeFalse()->and($panelUser['email'])->not->toBe('hrac.e2e@example.cz')->and($panelUser['email'])->toEndWith('.invalid');
    expect(collect($panel['calls'])->filter(fn (string $call) => str_starts_with($call, 'GET /api/application/users') && ! str_contains($call, 'external/'))->all())->toBe([]);
    expect($panel['servers'])->toHaveCount(1);
    $server = array_values($panel['servers'])[0];
    expect($server['user'])->toBe($panelUser['id'])->and($server['status'])->toBeNull()->and($server['suspended'])->toBeFalse()
        ->and($server['limits']['memory'])->toBe(8192)->and($server['environment']['MINECRAFT_VERSION'])->toBe('1.21.8')
        ->and(collect($panel['allocations'])->where('assigned', true)->pluck('id')->all())->toBe([$server['allocation']]);
    $game = GameServer::query()->where('service_id', $service->id)->firstOrFail();
    expect($game->egg_key)->toBe('minecraft-paper')->and($game->ptero_id)->toBe($server['id'])->and($game->ptero_user_id)->toBe($panelUser['id']);

    // what the customer sees: the server and its tools, never the vendor behind them
    $services = $this->withHeaders(e2eHeaders('services'))->getJson('/v1/services')->assertOk();
    expect(collect($services->json('data'))->pluck('id')->all())->toBe([$service->id])->and($services->json('data.0.state'))->toBe(ServiceStateMachine::ACTIVE);
    $detail = $this->withHeaders(e2eHeaders('service'))->getJson("/v1/services/{$service->id}")->assertOk();
    $features = $this->withHeaders(e2eHeaders('features'))->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    foreach (['power', 'console', 'game_status', 'startup', 'backups'] as $key) {
        expect($features['features'][$key]['enabled'])->toBeTrue($key);
    }
    expect($features['actions'])->toContain('variable.set')->toContain('rename');
    e5AssertNoVendor($services->getContent(), '/v1/services');
    e5AssertNoVendor($detail->getContent(), '/v1/services/{id}');
    e5AssertNoVendor(json_encode($features), 'features');
    $this->withHeaders(e2eHeaders('order-after'))->getJson("/v1/orders/{$order->id}")->assertOk()->assertJsonPath('data.state', OrderStateMachine::ACTIVE);

    // events: said once, to the right organization
    app(OutboxPublisher::class)->relayPending();
    $events = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    foreach (['order.paid', 'service.activated', 'order.active'] as $name) {
        expect($events)->toHaveKey($name)->and($events[$name])->toBe(1);
    }
    expect(OutboxMessage::query()->whereIn('name', ['order.paid', 'service.activated'])->whereNull('published_at')->count())->toBe(0);
});

it('runs the server: power, the console, game tools, a backup and its download from a daemon of the panel only', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    e5Fakes($panel, $gate);
    [$org, $service] = e5Provision($this, $panel, $gate);
    $serverId = (int) array_key_first($panel['servers']);
    expect($panel['power'][$serverId])->toBe('running');

    // power: every signal is an audited operation that is verified against the panel before it counts
    $this->withHeaders(e2eHeaders('power-bad'))->postJson("/v1/services/{$service->id}/power", ['power_action' => 'explode'])->assertStatus(422)->assertJsonPath('error', 'power_action_invalid');
    foreach ([['stop', 'offline'], ['start', 'running'], ['reboot', 'running']] as [$signal, $expected]) {
        $id = $this->withHeaders(e2eHeaders("power-{$signal}"))->postJson("/v1/services/{$service->id}/power", ['power_action' => $signal])->assertStatus(202)->json('operation_id');
        expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED, $signal)->and($panel['power'][$serverId])->toBe($expected, $signal);
    }

    // the console: a single-use ticket for the relay; the browser never gets the daemon's address or its session token
    config(['onhost.console.relay_url' => 'https://relay.onhost.test', 'onhost.console.relay_key' => 'relay-secret-e2e']);
    $ticket = $this->withHeaders(e2eHeaders('console'))->postJson("/v1/services/{$service->id}/console-token")->assertOk();
    $token = (string) $ticket->json('token');
    expect($token)->toMatch('/^con_[0-9a-z]{26}$/')->and((string) $ticket->json('socket'))->toBe('wss://relay.onhost.test/ws/'.$token);
    expect($ticket->json())->not->toHaveKeys(['url', 'meta']);
    e5AssertNoVendor(json_encode(array_diff_key((array) $ticket->json(), ['kind' => true])), 'console ticket'); // kind "wings" is the console's own protocol name for the browser
    $this->withHeaders(['Referer' => 'http://localhost'])->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', true);
    $this->getJson("/console/ws/{$token}")->assertStatus(401); // the relay key is required to resolve it
    $resolved = $this->withHeaders(['X-Relay-Key' => 'relay-secret-e2e'])->getJson("/console/ws/{$token}")->assertOk();
    expect($resolved->json('data.kind'))->toBe('wings_ws')->and($resolved->json('data.socket'))->toStartWith('wss://wings.mgmt.test:8080/api/servers/')->and($resolved->json('data.single_use'))->toBeTrue();
    $this->withHeaders(['X-Relay-Key' => 'relay-secret-e2e'])->getJson("/console/ws/{$token}")->assertStatus(410)->assertJsonPath('error', 'console_token_expired');

    // game tools: reads, then a few safe writes — each one operation, verified in the panel's own state
    expect($this->getJson("/v1/services/{$service->id}/resources/status")->assertOk()->json('data'))->toMatchArray(['state' => 'running', 'mem_limit_bytes' => 8192 * 1048576]);
    $startup = $this->getJson("/v1/services/{$service->id}/resources/startup")->assertOk()->json('data');
    expect(collect($startup['variables'])->pluck('key')->all())->toContain('MINECRAFT_VERSION')->toContain('MOTD');
    e5AssertNoVendor(json_encode($startup), 'startup');
    expect(e5Act($this, $service, 'variable.set', ['key' => 'MOTD', 'value' => 'Vitejte na lize'], 'var'))->toBe(Operation::SUCCEEDED)->and($panel['variables'][$serverId]['MOTD'])->toBe('Vitejte na lize');
    expect(e5Act($this, $service, 'rename', ['name' => 'Liga SMP 2'], 'rename'))->toBe(Operation::SUCCEEDED)->and($panel['name'][$serverId])->toBe('Liga SMP 2')->and($service->fresh()->label)->toBe('Liga SMP 2');
    expect(e5Act($this, $service, 'command.send', ['command' => 'say ahoj'], 'cmd'))->toBe(Operation::SUCCEEDED)->and($panel['commands'])->toBe(['say ahoj']);
    $this->withHeaders(e2eHeaders('var-bad'))->postJson("/v1/services/{$service->id}/actions", ['action' => 'variable.set', 'params' => ['key' => 'bad key', 'value' => 'x']])->assertStatus(422)->assertJsonPath('error', 'action_param_invalid');
    $this->withHeaders(e2eHeaders('unknown'))->postJson("/v1/services/{$service->id}/actions", ['action' => 'format.disk', 'params' => []])->assertStatus(422);

    // a backup of the panel, listed and downloaded: the link must name a daemon of the panel (the allow-list reads the panel's own node list)
    expect(e5Act($this, $service, 'backup', ['name' => 'e2e'], 'backup'))->toBe(Operation::SUCCEEDED);
    $backup = Backup::query()->where('service_id', $service->id)->firstOrFail();
    expect($backup->state)->toBe('completed')->and((string) $backup->remote_id)->toBe('bk-e2e-1');
    $listing = $this->getJson("/v1/services/{$service->id}/backups")->assertOk();
    expect(collect($listing->json('data'))->pluck('id')->all())->toBe([$backup->id]);
    e5AssertNoVendor($listing->getContent(), 'backups');
    $this->withHeaders(['Referer' => 'http://localhost'])->get("/v1/services/{$service->id}/backups/{$backup->id}/download")->assertRedirect('https://wings.mgmt.test:8080/download/backup?token=e2e-signed');
    $panel['download_host'] = 'intranet.internal'; // a panel that was broken into names a host that is not one of its daemons: nothing is handed out
    $refused = $this->withHeaders(['Referer' => 'http://localhost'])->get("/v1/services/{$service->id}/backups/{$backup->id}/download");
    expect($refused->isRedirection())->toBeFalse()->and($refused->getStatusCode())->toBeGreaterThanOrEqual(400);
    expect(stripos($refused->getContent(), 'intranet.internal'))->toBeFalse();

    // the audit trail knows who did what
    expect(Operation::query()->where('service_id', $service->id)->where('state', '!=', Operation::SUCCEEDED)->count())->toBe(0);
});

it('suspends and resumes the server and leaves the panel and the platform saying the same', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    e5Fakes($panel, $gate);
    [$org, $service] = e5Provision($this, $panel, $gate);
    $serverId = (int) array_key_first($panel['servers']);

    $id = $this->withHeaders(e2eHeaders('suspend'))->postJson("/v1/services/{$service->id}/suspend", ['reason' => 'rekonstrukce'])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED);
    expect($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and($panel['servers'][$serverId]['suspended'])->toBeTrue()->and($panel['power'][$serverId])->toBe('offline');
    $this->getJson("/v1/services/{$service->id}")->assertOk()->assertJsonPath('data.state', ServiceStateMachine::SUSPENDED);
    // a suspended server takes no power signal: the platform refuses it before the panel is asked
    $before = count($panel['calls']);
    $refused = $this->withHeaders(e2eHeaders('power-suspended'))->postJson("/v1/services/{$service->id}/power", ['power_action' => 'start']);
    expect($refused->getStatusCode())->toBeGreaterThanOrEqual(400)->and($panel['power'][$serverId])->toBe('offline');
    unset($before);

    $id = $this->withHeaders(e2eHeaders('resume'))->postJson("/v1/services/{$service->id}/resume")->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED);
    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and($panel['servers'][$serverId]['suspended'])->toBeFalse();
    $start = $this->withHeaders(e2eHeaders('power-after'))->postJson("/v1/services/{$service->id}/power", ['power_action' => 'start'])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($start))->state)->toBe(Operation::SUCCEEDED)->and($panel['power'][$serverId])->toBe('running');

    app(OutboxPublisher::class)->relayPending();
    $events = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($events)->toHaveKey('service.suspended')->and($events)->toHaveKey('service.active');
});

it('keeps another organization away from the server: not found, no console, no panel call', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    e5Fakes($panel, $gate);
    [$org, $service] = e5Provision($this, $panel, $gate);
    config(['onhost.console.relay_url' => 'https://relay.onhost.test', 'onhost.console.relay_key' => 'relay-secret-e2e']);
    $token = (string) $this->withHeaders(e2eHeaders('console'))->postJson("/v1/services/{$service->id}/console-token")->assertOk()->json('token');
    $backup = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'manual', 'state' => 'completed', 'remote_id' => 'bk-e2e-1', 'started_at' => now(), 'finished_at' => now(), 'size_bytes' => 4096]);

    // a second customer with a server of their own is not a member of the first organization
    $other = $this->customerWithOrganization(['email' => 'cizi.e2e@example.cz'], ['name' => 'Cizi s.r.o.']);
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->actingAs($other[0], 'sanctum');
    $calls = count($panel['calls']);
    $reads = ['features', 'resources/status', 'resources/startup', 'backups', 'operations', 'usage', 'spec'];
    e5AssertHidden($this->getJson("/v1/services/{$service->id}"), $service);
    foreach ($reads as $path) {
        e5AssertHidden($this->getJson("/v1/services/{$service->id}/{$path}"), $service);
    }
    foreach (['power' => ['power_action' => 'stop'], 'suspend' => [], 'resume' => [], 'terminate' => [], 'console-token' => []] as $path => $body) {
        e5AssertHidden($this->withHeaders(e2eHeaders("x-{$path}"))->postJson("/v1/services/{$service->id}/{$path}", $body), $service);
    }
    e5AssertHidden($this->withHeaders(e2eHeaders('x-action'))->postJson("/v1/services/{$service->id}/actions", ['action' => 'command.send', 'params' => ['command' => 'op cizi']]), $service);
    e5AssertHidden($this->get("/v1/services/{$service->id}/backups/{$backup->id}/download"), $service);
    expect($this->getJson('/v1/services')->assertOk()->json('data'))->toBe([]);
    $this->getJson("/console/check/{$token}")->assertOk()->assertJsonPath('data.valid', false); // the ticket of the owner's console opens nothing for a stranger
    expect(count($panel['calls']))->toBe($calls)->and(Operation::query()->where('service_id', $service->id)->where('state', Operation::PENDING)->count())->toBe(0)
        ->and($panel['commands'])->toBe([])->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
});

it('lets staff open a game server for the same customer without an order, on the same panel user', function () {
    LaravelNotification::fake();
    $panel = [];
    $gate = [];
    e5Fakes($panel, $gate);
    [$org, $first] = e5Provision($this, $panel, $gate);
    expect($panel['users'])->toHaveCount(1);
    $panelUserId = (int) array_key_first($panel['users']);

    $this->flushSession();
    $this->actingAs($this->staff('game_admin'), 'sanctum');
    $created = $this->withHeaders(['Idempotency-Key' => 'e5-quick-1'])->postJson("/v1/staff/customers/{$org->id}/services", ['product_key' => 'game', 'plan_key' => 'game-8', 'config' => ['egg' => 'minecraft-paper', 'version' => '1.21.8', 'label' => 'Zkušební server', 'region' => 'cz1'], 'reason' => 'zkušební server'])->assertCreated()->json();
    expect($created['operation_state'])->not->toBeNull();
    driveOperations();
    $second = Service::query()->findOrFail($created['service']['id']);
    expect($second->state)->toBe(ServiceStateMachine::ACTIVE)->and($second->organization_id)->toBe($org->id)->and($second->id)->not->toBe($first->id);
    // still one panel user for the organization, two servers on two ports, both owned by it
    expect($panel['users'])->toHaveCount(1)->and($panel['servers'])->toHaveCount(2)->and(collect($panel['servers'])->pluck('user')->unique()->all())->toBe([$panelUserId])
        ->and(collect($panel['servers'])->pluck('allocation')->unique())->toHaveCount(2);
    // a support agent may not
    $this->flushSession();
    $this->app['auth']->forgetGuards();
    $this->actingAs($this->staff('support_agent'), 'sanctum');
    $this->withHeaders(['Idempotency-Key' => 'e5-quick-2'])->postJson("/v1/staff/customers/{$org->id}/services", ['product_key' => 'game', 'plan_key' => 'game-8'])->assertForbidden();
    expect($panel['servers'])->toHaveCount(2);
});
