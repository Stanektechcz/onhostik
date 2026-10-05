<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\VirtualMachine;
use Onhost\Domain\Services\RescueMode;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E4 — a customer orders a virtual server, runs it, snapshots it, reinstalls it, rescues it, opens its console and has it paused
 * and resumed, touching only the real HTTP routes.
 *
 * Order: cart (PUT /v1/cart) → quote → order with the card gateway (POST /v1/orders) → Comgate callback to the real webhook route →
 * the order settles, the outbox relays and the VPS saga runs against a STATEFUL Proxmox double (e2ePveCluster: the clone really
 * creates the next guest, a snapshot, a power action or a reinstall changes the cluster and the next read shows it) → the service
 * is ACTIVE with its address, its sizing, its firewall and its own tag on the guest.
 * Then, one route at a time: power (POST …/power), snapshots up to the plan's number (POST …/snapshot, …/actions), rollback and
 * snapshot delete (HIGH: a fresh step-up through POST /v1/auth/step-up, the preview and its confirmation), reinstall (the same
 * rules, a protected safety snapshot first), rescue mode on and — after its window — off again by itself, the console token with
 * its own API scope, and the customer's own pause and resume (the power state is remembered).
 *
 * Only the edges are doubles (hypervisor and card gateway via Http::fake, mail via Notification::fake); everything between is the
 * product. Asserts do not depend on row order (SQLite and PostgreSQL alike). A customer's answer must never name the hypervisor
 * or its node.
 */

const E2E_VPS_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExampleExampleExampleExampleExample jana@e2e';
const E2E_VPS_VENDORS = ['proxmox', 'pve.mgmt', 'prg1-n2', 'vncproxy', 'PVEVNC'];

beforeEach(function () {
    e2eSeedPlatform();
    pveLab();
    e2eComgateEnvironment();
    config()->set('onhost.console.relay_url', 'wss://relay.onhost.test');
    Http::preventStrayRequests();
});

/**
 * Sign up, order a Compute 2 (three snapshots in the plan), pay by card and let the saga run.
 *
 * @param  array<string,mixed>  $pve
 * @param  array<string,mixed>  $gate
 * @return array{0:User,1:Organization,2:string,3:Service,4:Order}
 */
function e2eVpsDeliver(object $test, array &$pve, array &$gate, string $email): array
{
    [$user, $org, $password] = e2eSignUp($test, $email, 'Servery e2e s.r.o.');
    $test->withHeaders(e2eHeaders('vps-cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'vps', 'plan_key' => 'compute-2', 'config' => ['ssh_keys' => [E2E_VPS_KEY], 'options' => ['ipv4' => true]]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk()->assertJsonCount(1, 'data.items');
    $quote = $test->withHeaders(e2eHeaders('vps-quote'))->postJson('/v1/cart/quote')->assertOk();
    $gate['trans_id'] = 'E2E-VPS-'.bin2hex(random_bytes(3));
    $placed = $test->withHeaders(e2eHeaders('vps-order'))->postJson('/v1/orders', [
        'quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'],
    ])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;
    expect($pve['clones'])->toBe([]); // nothing is built before the money
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'vps')->firstOrFail();

    return [$user, $org, $password, $service, $order->refresh()];
}

/** The cluster of the lab: two other customers' guests under the floor, and a backup group of a guest long gone. */
function e2eVpsCluster(array &$pve): void
{
    $pve = ['guests' => [
        1040 => ['name' => 'jiny-zakaznik-1', 'description' => '', 'tags' => '', 'status' => 'running', 'template' => 0, 'config' => ['scsi0' => 'local-zfs:vm-1040-disk-0,size=20G'], 'snapshots' => [], 'firewall_rules' => [], 'firewall_options' => []],
        1041 => ['name' => 'jiny-zakaznik-2', 'description' => '', 'tags' => '', 'status' => 'running', 'template' => 0, 'config' => ['scsi0' => 'local-zfs:vm-1041-disk-0,size=20G'], 'snapshots' => [], 'firewall_rules' => [], 'firewall_options' => []],
    ], 'backups' => [1042]];
    e2ePveCluster($pve);
}

/** One action on a server through the real route, driven to its end. @param array<string,mixed> $body */
function e2eVpsRun(object $test, string $serviceId, array $body, string $label, int $status = 202): Operation
{
    $answer = $test->withHeaders(e2eHeaders($label))->postJson("/v1/services/{$serviceId}/actions", $body)->assertStatus($status);

    return driveOperation(Operation::query()->findOrFail($answer->json('operation_id')));
}

/** The VM of a service as the cluster double holds it. @param array<string,mixed> $pve @return array<string,mixed> */
function e2eVpsGuest(array $pve, Service $service): array
{
    return $pve['guests'][(int) ProviderBinding::query()->where('service_id', $service->id)->value('remote_id')];
}

/** Every word of a customer-facing answer must be free of the hypervisor's names. */
function e2eVpsNoVendor(mixed $answer): void
{
    $text = strtolower(json_encode($answer));
    foreach (E2E_VPS_VENDORS as $vendor) {
        $at = strpos($text, strtolower($vendor));
        expect($at === false ? null : substr($text, max(0, $at - 120), 240))->toBeNull("a customer answer names {$vendor}");
    }
}

it('takes a customer from the cart to a running server: paid, cloned into a number nobody used, sized, addressed and firewalled', function () {
    LaravelNotification::fake();
    $pve = [];
    e2eVpsCluster($pve);
    $gate = [];
    e2eComgateFake($gate);

    [$user, $org, , $service, $order] = e2eVpsDeliver($this, $pve, $gate, 'vps.e2e@example.cz');

    expect($order->state)->toBe(OrderStateMachine::ACTIVE)
        ->and($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->hostname)->toEndWith('.cust.onhost.cz')->and($service->activated_at)->not->toBeNull();
    // the number: Proxmox would say 1040's neighbour 1042, whose backup group still holds a final archive — the VM got the next free one
    $binding = ProviderBinding::query()->where('service_id', $service->id)->sole();
    expect($binding->remote_id)->toBe('1043')->and($binding->remote_type)->toBe('qemu')
        ->and($pve['clones'])->toBe([1043])->and(array_keys($pve['guests']))->toEqualCanonicalizing([1040, 1041, 1043, 9001]);
    $guest = $pve['guests'][1043];
    expect($guest['status'])->toBe('running')->and($guest['tags'])->toContain(ProxmoxComputeProvider::serviceTag($service->id))
        ->and($guest['config']['cores'])->toBe(2)->and($guest['config']['memory'])->toBe(4096)->and($guest['config']['scsi0'])->toContain('size=80G')
        ->and($guest['config']['ipconfig0'])->toBe('ip=192.0.2.2/29,gw=192.0.2.1')->and((string) $guest['config']['sshkeys'])->toContain('ssh-ed25519')
        ->and($guest['firewall_rules'])->toHaveCount(3)->and($guest['firewall_options'])->toMatchArray(['enable' => 1, 'policy_in' => 'DROP'])
        ->and($guest['snapshots'])->toBe([]);
    // the other customers' machines were never touched
    expect($pve['guests'][1040]['status'])->toBe('running')->and($pve['guests'][1041]['config'])->toBe(['scsi0' => 'local-zfs:vm-1041-disk-0,size=20G']);

    $vm = VirtualMachine::query()->where('service_id', $service->id)->sole();
    $v4 = IpAddress::query()->find($vm->ipv4_address_id);
    expect($vm->vmid)->toBe(1043)->and($vm->state)->toBe('running')->and($vm->cores)->toBe(2)->and($vm->memory_mb)->toBe(4096)
        ->and($v4->address)->toBe('192.0.2.2')->and($v4->state)->toBe('allocated')->and($service->tags['access']['ipv4'])->toBe('192.0.2.2');
    expect(Operation::query()->where('service_id', $service->id)->where('kind', 'provision.vps')->sole()->state)->toBe(Operation::SUCCEEDED);

    // what the customer sees
    $list = $this->withHeaders(e2eHeaders('vps-list'))->getJson('/v1/services')->assertOk();
    expect(collect($list->json('data'))->pluck('id')->all())->toBe([$service->id]);
    $detail = $this->withHeaders(e2eHeaders('vps-detail'))->getJson("/v1/services/{$service->id}")->assertOk()->assertJsonPath('data.state', ServiceStateMachine::ACTIVE);
    $features = $this->withHeaders(e2eHeaders('vps-features'))->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    expect($features['features'])->toHaveKeys(['vm_rescue', 'vm_reinstall', 'snapshots'])->and($features['features']['vm_reinstall']['enabled'])->toBeTrue()->and($features['actions'])->not->toBeEmpty();
    $operations = $this->withHeaders(e2eHeaders('vps-ops'))->getJson("/v1/services/{$service->id}/operations")->assertOk();
    $order = $this->withHeaders(e2eHeaders('vps-order-read'))->getJson("/v1/orders/{$order->id}")->assertOk();
    foreach ([$list->json(), $detail->json(), $features, $operations->json(), $order->json()] as $answer) {
        e2eVpsNoVendor($answer);
    }

    // events: said once, to the right organization; nothing left unpublished
    for ($round = 0; $round < 4 && app(OutboxPublisher::class)->relayPending() > 0; $round++); // an event can raise another (the badge after the first service)
    $names = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($names)->toHaveKeys(['order.paid', 'service.activated', 'order.active'])
        ->and($names['order.paid'])->toBe(1)->and($names['service.activated'])->toBe(1)->and($names['order.active'])->toBe(1)
        ->and(OutboxMessage::query()->where('organization_id', $org->id)->whereNull('published_at')->pluck('name')->all())->toBe([]);
    expect($pve['unknown'])->toBe([]);
});

it('starts, stops and reboots a server, and refuses what is no power action', function () {
    LaravelNotification::fake();
    $pve = [];
    e2eVpsCluster($pve);
    $gate = [];
    e2eComgateFake($gate);
    [, , , $service] = e2eVpsDeliver($this, $pve, $gate, 'vps.power@example.cz');

    $stop = $this->withHeaders(e2eHeaders('vps-stop'))->postJson("/v1/services/{$service->id}/power", ['power_action' => 'stop'])->assertStatus(202);
    expect(driveOperation(Operation::query()->findOrFail($stop->json('operation_id')))->state)->toBe(Operation::SUCCEEDED)
        ->and(e2eVpsGuest($pve, $service)['status'])->toBe('stopped');
    expect(e2eVpsRun($this, $service->id, ['action' => 'power', 'params' => ['power_action' => 'start']], 'vps-start')->state)->toBe(Operation::SUCCEEDED)
        ->and(e2eVpsGuest($pve, $service)['status'])->toBe('running');
    $before = count($pve['writes']);
    expect(e2eVpsRun($this, $service->id, ['action' => 'power', 'params' => ['power_action' => 'reboot']], 'vps-reboot')->state)->toBe(Operation::SUCCEEDED)
        ->and(e2eVpsGuest($pve, $service)['status'])->toBe('running')
        ->and(array_slice($pve['writes'], $before))->toContain('POST /nodes/prg1-n2/qemu/1043/status/reboot');

    $this->withHeaders(e2eHeaders('vps-bad-power'))->postJson("/v1/services/{$service->id}/power", ['power_action' => 'format-disk'])->assertStatus(422)->assertJsonPath('error', 'power_action_invalid');
    expect(e2eVpsGuest($pve, $service)['status'])->toBe('running');

    // another organization sees no such server, and its power buttons reach nothing
    $this->flushSession();
    app('auth')->forgetGuards();
    e2eSignUp($this, 'vps.stranger@example.cz', 'Cizi s.r.o.');
    $this->withHeaders(e2eHeaders('vps-stranger-read'))->getJson("/v1/services/{$service->id}")->assertStatus(403);
    $this->withHeaders(e2eHeaders('vps-stranger-stop'))->postJson("/v1/services/{$service->id}/power", ['power_action' => 'stop'])->assertStatus(403);
    expect(e2eVpsGuest($pve, $service)['status'])->toBe('running')->and($pve['unknown'])->toBe([]);
});

it('keeps the snapshots the plan sells, and rolls back and deletes only behind a fresh step-up, a preview and a safety copy', function () {
    LaravelNotification::fake();
    $pve = [];
    e2eVpsCluster($pve);
    $gate = [];
    e2eComgateFake($gate);
    [, $org, $password, $service] = e2eVpsDeliver($this, $pve, $gate, 'vps.snap@example.cz');

    foreach (['s1', 's2', 's3'] as $name) {
        expect(e2eVpsRun($this, $service->id, ['action' => 'snapshot', 'params' => ['name' => $name, 'description' => "ruční {$name}"]], "vps-snap-{$name}")->state)->toBe(Operation::SUCCEEDED);
    }
    expect(array_keys(e2eVpsGuest($pve, $service)['snapshots']))->toBe(['s1', 's2', 's3']);

    // the plan sells three: a fourth is refused at the request, and nothing reaches the hypervisor
    $writes = count($pve['writes']);
    $this->withHeaders(e2eHeaders('vps-snap-s4'))->postJson("/v1/services/{$service->id}/snapshot", ['params' => ['name' => 's4']])
        ->assertStatus(422)->assertJsonPath('error', 'feature_limit_reached')->assertJsonPath('limit', 3)->assertJsonPath('used', 3);
    expect(array_slice($pve['writes'], $writes))->toBe([]);
    $listing = $this->withHeaders(e2eHeaders('vps-snaps'))->getJson("/v1/services/{$service->id}/resources/snapshots")->assertOk();
    expect(collect($listing->json('data'))->pluck('name')->all())->toEqualCanonicalizing(['s1', 's2', 's3'])->and(collect($listing->json('data'))->every(fn (array $s) => $s['counts'] === true))->toBeTrue();

    // a rollback is HIGH: no step-up, no rollback
    $body = ['action' => 'rollback_snapshot', 'params' => ['name' => 's2']];
    $this->withHeaders(e2eHeaders('vps-rb-nostep'))->postJson("/v1/services/{$service->id}/actions", $body)->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    expect(e2eVpsGuest($pve, $service))->not->toHaveKey('rolled_back_to');

    e2eStepUp($this, $password);
    $preview = $this->withHeaders(e2eHeaders('vps-rb-preview'))->getJson("/v1/services/{$service->id}/actions/rollback_snapshot/preview?params%5Bname%5D=s2")->assertOk();
    $what = $preview->json('data.what') ?? $preview->json('what');
    $fingerprint = (string) ($preview->json('data.fingerprint') ?? $preview->json('fingerprint'));
    expect(implode(' ', $what))->toContain('s2')->and(strlen($fingerprint))->toBe(64);
    // the confirmation of ANOTHER snapshot does not fit this one
    $this->withHeaders(e2eHeaders('vps-rb-stale'))->postJson("/v1/services/{$service->id}/actions", $body + ['confirm' => str_repeat('0', 64)])->assertStatus(409)->assertJsonPath('error', 'target_changed');
    expect(e2eVpsGuest($pve, $service))->not->toHaveKey('rolled_back_to');

    $rollback = e2eVpsRun($this, $service->id, $body + ['confirm' => $fingerprint], 'vps-rb');
    expect($rollback->state)->toBe(Operation::SUCCEEDED)->and(e2eVpsGuest($pve, $service)['rolled_back_to'])->toBe('s2')->and(e2eVpsGuest($pve, $service)['status'])->toBe('running');
    // the platform took its own protected snapshot first, and it does not eat the customer's three
    $safety = Backup::query()->where('service_id', $service->id)->where('kind', 'pre_rollback')->sole();
    expect($safety->protected)->toBeTrue()->and($safety->state)->toBe('completed')->and($safety->operation_id)->toBe($rollback->id)
        ->and(array_keys(e2eVpsGuest($pve, $service)['snapshots']))->toContain($safety->remote_id);
    $snapshotWrite = array_search('POST /nodes/prg1-n2/qemu/1043/snapshot', array_slice($pve['writes'], $writes), true);
    $rollbackWrite = array_search('POST /nodes/prg1-n2/qemu/1043/snapshot/s2/rollback', array_slice($pve['writes'], $writes), true);
    expect($snapshotWrite)->not->toBeFalse()->and($rollbackWrite)->toBeGreaterThan($snapshotWrite);
    $listing = $this->withHeaders(e2eHeaders('vps-snaps-after'))->getJson("/v1/services/{$service->id}/resources/snapshots")->assertOk();
    $counting = collect($listing->json('data'))->where('counts', true)->pluck('name')->all();
    expect($counting)->toEqualCanonicalizing(['s1', 's2', 's3']);
    $this->withHeaders(e2eHeaders('vps-snap-s4-again'))->postJson("/v1/services/{$service->id}/snapshot", ['params' => ['name' => 's4']])->assertStatus(422)->assertJsonPath('error', 'feature_limit_reached');

    // delete one of the customer's own: HIGH again, and the platform's safety snapshot is not the customer's to delete
    e2eStepUp($this, $password);
    $this->withHeaders(e2eHeaders('vps-del-safety'))->postJson("/v1/services/{$service->id}/actions", ['action' => 'snapshot.delete', 'params' => ['name' => $safety->remote_id]])->assertStatus(409)->assertJsonPath('error', 'backup_protected');
    expect(array_keys(e2eVpsGuest($pve, $service)['snapshots']))->toContain($safety->remote_id);
    expect(e2eVpsRun($this, $service->id, ['action' => 'snapshot.delete', 'params' => ['name' => 's1']], 'vps-del-s1')->state)->toBe(Operation::SUCCEEDED)
        ->and(array_keys(e2eVpsGuest($pve, $service)['snapshots']))->not->toContain('s1');
    expect(e2eVpsRun($this, $service->id, ['action' => 'snapshot', 'params' => ['name' => 's4']], 'vps-snap-s4-ok')->state)->toBe(Operation::SUCCEEDED)
        ->and(array_keys(e2eVpsGuest($pve, $service)['snapshots']))->toContain('s4');

    expect($pve['unknown'])->toBe([]);
});

it('reinstalls a server only behind a step-up, a preview and a safety snapshot, and keeps a changed password out of every record', function () {
    LaravelNotification::fake();
    $pve = [];
    e2eVpsCluster($pve);
    $gate = [];
    e2eComgateFake($gate);
    [, , $password, $service] = e2eVpsDeliver($this, $pve, $gate, 'vps.reinstall@example.cz');
    $body = ['action' => 'reinstall', 'params' => ['confirm' => true, 'image' => 'debian-13']];
    $writes = count($pve['writes']);

    // HIGH: no fresh step-up, no reinstall — and nothing reaches the hypervisor
    $this->withHeaders(e2eHeaders('vps-ri-nostep'))->postJson("/v1/services/{$service->id}/actions", $body)->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    e2eStepUp($this, $password);
    $this->withHeaders(e2eHeaders('vps-ri-noconfirm'))->postJson("/v1/services/{$service->id}/actions", ['action' => 'reinstall', 'params' => ['image' => 'debian-13']])->assertStatus(422)->assertJsonPath('field', 'confirm');
    $this->withHeaders(e2eHeaders('vps-ri-image'))->postJson("/v1/services/{$service->id}/actions", ['action' => 'reinstall', 'params' => ['confirm' => true, 'image' => 'windows-11']])
        ->assertStatus(422)->assertJsonPath('error', 'action_param_invalid')->assertJsonPath('allowed', ['debian-13']);
    expect(array_slice($pve['writes'], $writes))->toBe([]);

    // the preview says what will be lost and what the way back is; the confirmation belongs to this very system
    $preview = $this->withHeaders(e2eHeaders('vps-ri-preview'))->getJson("/v1/services/{$service->id}/actions/reinstall/preview?params%5Bimage%5D=debian-13")->assertOk();
    $answer = $preview->json('data') ?? $preview->json();
    expect(implode(' ', $answer['what']))->toContain('debian-13')->toContain('Systémový disk')->and($answer['recovery']['kind'])->toBe('safety_copy')->and(strlen((string) $answer['fingerprint']))->toBe(64);
    e2eVpsNoVendor($answer);
    $this->withHeaders(e2eHeaders('vps-ri-stale'))->postJson("/v1/services/{$service->id}/actions", $body + ['confirm' => str_repeat('a', 64)])->assertStatus(409)->assertJsonPath('error', 'target_changed');
    expect(array_slice($pve['writes'], $writes))->toBe([]);

    e2eStepUp($this, $password);
    $run = e2eVpsRun($this, $service->id, $body + ['confirm' => $answer['fingerprint']], 'vps-ri');
    expect($run->state)->toBe(Operation::SUCCEEDED, (string) data_get($run->error, 'message', ''));
    $vm = '/nodes/prg1-n2/qemu/1043';
    expect(array_slice($pve['writes'], $writes))->toBe(["POST {$vm}/snapshot", "POST {$vm}/status/stop", "POST {$vm}/config", "PUT {$vm}/resize", "POST {$vm}/status/start"]);
    $guest = e2eVpsGuest($pve, $service);
    expect($guest['status'])->toBe('running')->and($guest['config']['scsi0'])->toBe('local-zfs:vm-1043-disk-1,size=80G') // a fresh system disk, grown to the plan
        ->and($guest['config']['unused0'])->toBe('local-zfs:vm-1043-disk-0') // the old one is detached, not destroyed
        ->and($guest['config']['import'])->toBe('local-zfs:0,import-from=local-zfs:base-9001-disk-0');
    $copy = Backup::query()->where('service_id', $service->id)->where('kind', 'pre_reinstall')->sole();
    expect($copy->protected)->toBeTrue()->and($copy->state)->toBe('completed')->and($copy->operation_id)->toBe($run->id)->and(array_keys($guest['snapshots']))->toContain($copy->remote_id);
    expect(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::ACTIVE);

    // the product generates no password on a reinstall (cloud-init keeps the ssh keys): a NEW access is the customer's own act, and the
    // password they give is forgotten by the run as soon as it ends — never in an answer, an operation row or the listing
    $secret = 'Nove-Heslo-Serveru-4711-xyz';
    e2eStepUp($this, $password);
    $reset = e2eVpsRun($this, $service->id, ['action' => 'access.reset', 'params' => ['password' => $secret]], 'vps-access');
    expect($reset->state)->toBe(Operation::SUCCEEDED, (string) data_get($reset->error, 'message', ''))->and((string) (e2eVpsGuest($pve, $service)['config']['cipassword'] ?? ''))->not->toBe('');
    $listing = $this->withHeaders(e2eHeaders('vps-ops-after'))->getJson("/v1/services/{$service->id}/operations")->assertOk();
    expect($listing->getContent())->not->toContain($secret);
    $stored = json_encode(Operation::query()->where('service_id', $service->id)->get()->map(fn (Operation $o) => [$o->desired, $o->context, $o->result, $o->error])->all());
    expect($stored)->not->toContain($secret);
    expect($pve['unknown'])->toBe([]);
});

it('boots a rescue image only behind a step-up, from a list the node offers, and puts the server back by itself when the window ends', function () {
    LaravelNotification::fake();
    $pve = [];
    e2eVpsCluster($pve);
    $gate = [];
    e2eComgateFake($gate);
    [, $org, $password, $service] = e2eVpsDeliver($this, $pve, $gate, 'vps.rescue@example.cz');
    $start = fn (string $image, array $extra = []) => ['action' => 'rescue.start', 'params' => ['image' => $image] + $extra];

    $this->withHeaders(e2eHeaders('vps-rescue-nostep'))->postJson("/v1/services/{$service->id}/actions", $start('local:iso/systemrescue-11.iso'))->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    expect(e2eVpsGuest($pve, $service)['config'])->not->toHaveKey('ide2');

    // an image of the customer's own choosing is not an image: the run refuses it and the server is not touched
    e2eStepUp($this, $password);
    $refused = e2eVpsRun($this, $service->id, $start('local:iso/../../etc/passwd'), 'vps-rescue-bad');
    expect($refused->state)->toBe(Operation::FAILED)->and(e2eVpsGuest($pve, $service)['config'])->not->toHaveKey('ide2')->and(RescueMode::session($service->fresh()))->toBeNull();

    e2eStepUp($this, $password);
    $on = e2eVpsRun($this, $service->id, $start('local:iso/systemrescue-11.iso', ['hours' => 2]), 'vps-rescue-on');
    expect($on->state)->toBe(Operation::SUCCEEDED, (string) data_get($on->error, 'message', ''));
    $guest = e2eVpsGuest($pve, $service);
    expect($guest['config']['ide2'])->toBe('local:iso/systemrescue-11.iso,media=cdrom')->and($guest['config']['boot'])->toBe('order=ide2;scsi0;net0')->and($guest['status'])->toBe('running')
        ->and($pve['writes'])->toContain('POST /nodes/prg1-n2/qemu/1043/status/reboot');
    $features = $this->withHeaders(e2eHeaders('vps-rescue-features'))->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data.features.vm_rescue');
    expect($features['enabled'])->toBeTrue()->and($features['options']['session']['iso'])->toBe('local:iso/systemrescue-11.iso')->and($features['options']['session']['previous']['boot'])->toBe('order=scsi0;net0');
    e2eVpsNoVendor($features);

    // the window ends: nobody asks, the server boots from its own disk again, exactly the order it had
    $this->travel(3)->hours();
    Artisan::call('onhost:services:rescue-expire');
    expect(Artisan::output())->toContain('put back: 1');
    $guest = e2eVpsGuest($pve, $service);
    expect($guest['config']['boot'])->toBe('order=scsi0;net0')->and($guest['config']['ide2'])->toBe('none,media=cdrom')->and(RescueMode::session($service->fresh()))->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    $names = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($names)->toHaveKeys(['service.rescue.started', 'service.rescue.ended'])->and($pve['unknown'])->toBe([]);
});

it('issues a console token to the owner, and to an API token only when it carries the console scope', function () {
    LaravelNotification::fake();
    $pve = [];
    e2eVpsCluster($pve);
    $gate = [];
    e2eComgateFake($gate);
    [, $org, $password, $service] = e2eVpsDeliver($this, $pve, $gate, 'vps.console@example.cz');

    // the owner, in the browser session: a one-time token of ONhost's own and the relay to open it on — never the hypervisor's ticket
    $console = $this->withHeaders(e2eHeaders('vps-console'))->postJson("/v1/services/{$service->id}/console-token")->assertOk();
    $access = $console->json('data') ?? $console->json();
    expect($access['kind'])->toBe('novnc')->and($access['token'])->toStartWith('con_')->and($access['socket'])->toBe('wss://relay.onhost.test/ws/'.$access['token'])->and($access['password'])->toBe('one-time-vnc-secret')
        ->and($pve['vnc_tickets'])->toHaveCount(1);
    expect($console->getContent())->not->toContain($pve['vnc_tickets'][0])->not->toContain('vncwebsocket');
    e2eVpsNoVendor($access);

    // two API tokens made through the real route (HIGH: a fresh step-up): a reader and a reader with the console scope
    $tokens = [];
    foreach ([['reader', ['services:read']], ['console', ['services:read', 'services:console']]] as [$name, $scopes]) {
        e2eStepUp($this, $password);
        $made = $this->withHeaders(e2eHeaders('vps-token-'.$name))->postJson('/v1/tokens', ['name' => $name, 'scopes' => $scopes])->assertCreated();
        $tokens[$name] = (string) ($made->json('data.token') ?? $made->json('token'));
        expect($tokens[$name])->not->toBe('');
    }

    // from here on a bearer, not the browser session
    $this->flushHeaders();
    $this->flushSession();
    $bearer = fn (string $name, string $label) => $this->withToken($tokens[$name])->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'e2e-'.$label.'-'.bin2hex(random_bytes(4))]);
    app('auth')->forgetGuards();
    $bearer('reader', 'read')->getJson("/v1/services/{$service->id}")->assertOk(); // the reader still reads
    app('auth')->forgetGuards();
    $bearer('reader', 'c1')->postJson("/v1/services/{$service->id}/console-token")->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:console scope.');
    app('auth')->forgetGuards();
    $bearer('reader', 'c2')->getJson("/v1/services/{$service->id}/console-token")->assertForbidden()->assertJsonPath('message', 'The API token lacks the services:console scope.');
    expect($pve['vnc_tickets'])->toHaveCount(1); // the refused ones never reached the hypervisor
    app('auth')->forgetGuards();
    $granted = $bearer('console', 'c3')->postJson("/v1/services/{$service->id}/console-token")->assertOk();
    $grantedToken = (string) ($granted->json('data.token') ?? $granted->json('token'));
    expect($grantedToken)->toStartWith('con_')->and($grantedToken)->not->toBe($access['token'])->and($granted->json('password') ?? $granted->json('data.password'))->toBe('one-time-vnc-secret')->and($pve['vnc_tickets'])->toHaveCount(2)->and($pve['unknown'])->toBe([]);
});

it('pauses a server on its owner\'s word and brings it back as it was: running comes back running, switched off stays off', function () {
    LaravelNotification::fake();
    $pve = [];
    e2eVpsCluster($pve);
    $gate = [];
    e2eComgateFake($gate);
    [, $org, , $service] = e2eVpsDeliver($this, $pve, $gate, 'vps.pause@example.cz');

    // a running server: the pause stops it and locks it against a start, the resume starts it again
    $suspend = e2eVpsRun($this, $service->id, ['action' => 'suspend', 'reason' => 'rekonstrukce infrastruktury'], 'vps-suspend');
    expect($suspend->state)->toBe(Operation::SUCCEEDED, (string) data_get($suspend->error, 'message', ''))->and(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::SUSPENDED);
    $guest = e2eVpsGuest($pve, $service);
    expect($guest['status'])->toBe('stopped')->and($guest['config']['onboot'])->toBe(0)->and($guest['config']['protection'])->toBe(1);
    // a paused server takes no power action: said on the spot, the hypervisor is not asked
    $writes = count($pve['writes']);
    $this->withHeaders(e2eHeaders('vps-paused-start'))->postJson("/v1/services/{$service->id}/power", ['power_action' => 'start'])->assertStatus(409);
    expect(array_slice($pve['writes'], $writes))->toBe([]);

    $resume = e2eVpsRun($this, $service->id, ['action' => 'resume'], 'vps-resume');
    expect($resume->state)->toBe(Operation::SUCCEEDED, (string) data_get($resume->error, 'message', ''))->and(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::ACTIVE);
    $guest = e2eVpsGuest($pve, $service);
    expect($guest['status'])->toBe('running')->and($guest['config']['onboot'])->toBe(1)->and($guest['config']['protection'])->toBe(0);

    // a server its owner had switched off: paused, resumed — and still off
    expect(e2eVpsRun($this, $service->id, ['action' => 'power', 'params' => ['power_action' => 'stop']], 'vps-off')->state)->toBe(Operation::SUCCEEDED);
    expect(e2eVpsRun($this, $service->id, ['action' => 'suspend', 'reason' => 'ještě jedna pauza'], 'vps-suspend-2')->state)->toBe(Operation::SUCCEEDED)->and(e2eVpsGuest($pve, $service)['status'])->toBe('stopped');
    $resumeOff = e2eVpsRun($this, $service->id, ['action' => 'resume'], 'vps-resume-2');
    expect($resumeOff->state)->toBe(Operation::SUCCEEDED, (string) data_get($resumeOff->error, 'message', ''))->and(Service::query()->findOrFail($service->id)->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(e2eVpsGuest($pve, $service)['status'])->toBe('stopped')->and(e2eVpsGuest($pve, $service)['config']['protection'])->toBe(0);

    // the hypervisor's number is the same through all of it; the other customers' machines were never touched
    expect(ProviderBinding::query()->where('service_id', $service->id)->value('remote_id'))->toBe('1043')->and($pve['clones'])->toBe([1043])
        ->and($pve['guests'][1040]['status'])->toBe('running')->and($pve['guests'][1041]['status'])->toBe('running');
    for ($round = 0; $round < 4 && app(OutboxPublisher::class)->relayPending() > 0; $round++);
    $names = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($names)->toHaveKeys(['service.suspended', 'service.active'])->and($names['service.suspended'])->toBe(2)->and($names['service.active'])->toBe(2)->and($pve['unknown'])->toBe([]);
});
