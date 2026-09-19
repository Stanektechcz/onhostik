<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Provisioning\Reconciler;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\AvailabilityWatch;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Availability of a VPS and a game server (Brain card H14). Sites are probed over HTTP (WebToolsFeatureTest); a server
 * has no address of ours to probe, so the alarm hangs on the power state the reconciler reads from the panel anyway.
 * The synthetic failure here is a panel that starts answering "stopped" for a server nobody stopped: the alarm has to
 * name the service, reach its owner and nobody else, fire once, and stay quiet whenever the stop has an explanation.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    config(['onhost.provisioning.auto_repair' => false]);
});

function watchedVps(Organization $org, string $slaClass = 'standard', string $vmid = '1042'): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-watch.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud', 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 20]], 'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 20, 'ipv4' => 1], 'sla_class' => $slaClass, 'activated_at' => now(),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => $vmid, 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-watch'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}", 'adapter_version' => '1.0.0']);
    app(IpamService::class)->allocate(4, 'cz1', 'vps', $service->id, $org->id);

    return $service;
}

/** A Proxmox that answers with whatever `$status` holds right now; power commands flip it like the real thing. */
function watchedPveFake(string &$status): void
{
    Http::fake(function (Request $request) use (&$status) {
        if (! str_contains($request->url(), PVE)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/qemu/1042/status/current') => Http::response(['data' => ['status' => $status, 'uptime' => $status === 'running' ? 100 : 0]]),
            (bool) preg_match('~/qemu/\d+/config$~', $path) => Http::response(pveVmConfig()),
            (bool) preg_match('~/qemu/\d+/status/current$~', $path) => Http::response(['data' => ['status' => 'running', 'uptime' => 100]]), // every other VM runs
            (bool) preg_match('~/qemu/1042/status/(shutdown|stop|start)$~', $path, $m) => (function () use (&$status, $m) {
                $status = $m[1] === 'start' ? 'running' : 'stopped';

                return Http::response(['data' => 'UPID:prg1-n2:000A1B2F:0004E1F8:66F0AA14:qm'.$m[1].':1042:onhost@pve!cp:']);
            })(),
            (bool) preg_match('~/tasks/[^/]+/status$~', $path) => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
            default => Http::response(['data' => []]),
        };
    });
}

/** A game panel whose daemon reports `$state` (running | offline) and lists `$schedules`. */
function watchedGamePanelFake(string &$state, array $schedules = []): void
{
    Http::fake(function (Request $request) use (&$state, $schedules) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            $path === '/api/application/servers/77' => Http::response(['object' => 'server', 'attributes' => ['id' => 77, 'uuid' => 'u', 'identifier' => 'e4c1abcd', 'name' => 'mc-liga', 'suspended' => false, 'status' => null, 'user' => 9, 'node' => 2, 'allocation' => 11, 'nest' => 1, 'egg' => 3, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'feature_limits' => ['databases' => 2, 'allocations' => 2, 'backups' => 5], 'container' => ['installed' => 1, 'environment' => []]]]),
            $path === '/api/client/servers/e4c1abcd/resources' => Http::response(['object' => 'stats', 'attributes' => ['current_state' => $state, 'is_suspended' => false, 'resources' => ['memory_bytes' => 0, 'cpu_absolute' => 0, 'disk_bytes' => 0]]]),
            $path === '/api/client/servers/e4c1abcd' => Http::response(['object' => 'server', 'attributes' => ['identifier' => 'e4c1abcd', 'name' => 'mc-liga', 'is_installing' => false, 'is_suspended' => false, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'feature_limits' => ['databases' => 2, 'allocations' => 2, 'backups' => 5]]]),
            $path === '/api/client/servers/e4c1abcd/schedules' => Http::response(['object' => 'list', 'data' => $schedules]),
            default => Http::response(['object' => 'list', 'data' => []]),
        };
    });
}

function watchPass(Service $service): Service
{
    app(Reconciler::class)->reconcileService($service->fresh(), CommandContext::system('availability test'));
    app(OutboxPublisher::class)->relayPending();

    return $service->fresh();
}

it('raises one alarm that names the service when a VPS stops on its own, and says when it runs again', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'majitel@vps.test']);
    [, $other] = $this->customerWithOrganization(['email' => 'soused@vps.test']);
    $service = watchedVps($org);
    $neighbour = watchedVps($other, 'standard', '1043');
    $status = 'running';
    watchedPveFake($status);

    watchPass($service);
    expect(OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->exists())->toBeFalse();

    $status = 'stopped'; // the synthetic failure: the host lost the VM, nobody asked for a stop
    $service = watchPass($service);
    expect(OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->exists())->toBeFalse() // one reading is not an outage
        ->and(AvailabilityWatch::of($service))->toMatchArray(['alerted' => false, 'intent' => 'running'])
        ->and(AvailabilityWatch::of($service)['down_since'])->not->toBeNull();

    $this->travel(16)->minutes();
    watchPass($neighbour);
    watchPass($neighbour);
    $service = watchPass($service);
    $alarm = OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->sole();
    expect($alarm->aggregate_type)->toBe('service')->and($alarm->aggregate_id)->toBe($service->id)->and($alarm->organization_id)->toBe($org->id) // the alarm carries the service identifier
        ->and($alarm->payload)->toMatchArray(['family' => 'cloud', 'hostname' => 'vm-watch.cust.onhost.cz', 'status' => 'stopped', 'passes' => 2, 'notify' => true]);
    $note = Notification::query()->where('event', 'service.stopped_unexpectedly')->sole();
    expect($note->audience)->toBe('customer')->and($note->organization_id)->toBe($org->id)->and($note->ref_type)->toBe('service')->and($note->ref_id)->toBe($service->id)
        ->and($note->title)->toBe('Server vm-watch.cust.onhost.cz neběží')->and($note->severity)->toBe('hot');
    $mail = MailOutbox::query()->where('template_key', 'service-stopped')->sole();
    expect($mail->to)->toBe('majitel@vps.test')->and($mail->ref_id)->toBe($service->id)->and($mail->subject)->toContain('vm-watch.cust.onhost.cz');
    expect(Notification::query()->where('organization_id', $other->id)->where('event', 'service.stopped_unexpectedly')->exists())->toBeFalse(); // the neighbour's VM runs; they hear nothing
    expect(Notification::query()->where('audience', 'internal')->where('event', 'service.stopped_unexpectedly')->exists())->toBeFalse(); // a standard-class VPS does not page operations

    // what the customer sees on the service: the row and the summary say since when
    $this->actingAs($org->owner, 'sanctum');
    expect($this->getJson("/v1/services/{$service->id}")->assertOk()->json('data.summary.availability'))->toMatchArray(['alerted' => true, 'intent' => 'running', 'alerts' => true]);

    $this->travel(16)->minutes();
    watchPass($service); // still down: the episode was reported, it is not reported again
    expect(OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->count())->toBe(1);

    $status = 'running';
    $this->travel(16)->minutes();
    $service = watchPass($service);
    $up = OutboxMessage::query()->where('name', 'service.running_again')->sole();
    expect($up->aggregate_id)->toBe($service->id)->and($up->payload['minutes'])->toBeGreaterThanOrEqual(48)
        ->and(Notification::query()->where('event', 'service.running_again')->sole()->title)->toBe('Server vm-watch.cust.onhost.cz opět běží')
        ->and(MailOutbox::query()->where('template_key', 'service-running')->count())->toBe(1)
        ->and(AvailabilityWatch::of($service))->toMatchArray(['alerted' => false, 'down_since' => null, 'intent' => 'running']);
});

it('treats a stop the customer ordered here as the intent, not as an outage', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = watchedVps($org);
    $status = 'running';
    watchedPveFake($status);

    $stop = driveOperation(app(ServiceService::class)->requestAction($service, 'power', $this->contextFor($user, $org), 'watch-stop-1', ['power_action' => 'shutdown']));
    expect($stop->state)->toBe(Operation::SUCCEEDED)->and($status)->toBe('stopped')->and(AvailabilityWatch::of($service->fresh())['intent'])->toBe('stopped');
    foreach ([1, 2, 3] as $pass) {
        $this->travel(16)->minutes();
        watchPass($service);
    }
    expect(OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->exists())->toBeFalse();

    // started again through the platform: from now on a stop is news again
    $start = driveOperation(app(ServiceService::class)->requestAction($service->fresh(), 'power', $this->contextFor($user, $org), 'watch-start-1', ['power_action' => 'start']));
    expect($start->state)->toBe(Operation::SUCCEEDED)->and(AvailabilityWatch::of($service->fresh())['intent'])->toBe('running');
    $status = 'stopped';
    foreach ([1, 2] as $pass) {
        $this->travel(16)->minutes();
        watchPass($service);
    }
    expect(OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->count())->toBe(1);
});

it('reports a crashed game server, tells operations when an SLA class is at stake, and respects the mail switch', function () {
    [$user, $org] = $this->customerWithOrganization(['email' => 'liga@hry.test']);
    $service = featureGameService($org);
    $service->forceFill(['sla_class' => 'premium'])->save();
    $state = 'offline'; // the daemon gave up restarting it
    watchedGamePanelFake($state);

    // the customer does not want the mail; the episode is still recorded and shown
    $this->actingAs($user, 'sanctum');
    $this->withHeader('Idempotency-Key', 'watch-policy-1')->putJson("/v1/services/{$service->id}/policy", ['availability_alerts' => false])->assertSuccessful();
    expect(AvailabilityWatch::of($service->fresh())['alerts'])->toBeFalse();

    watchPass($service);
    $this->travel(16)->minutes();
    $service = watchPass($service);
    $alarm = OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->sole();
    expect($alarm->aggregate_id)->toBe($service->id)->and($alarm->payload)->toMatchArray(['family' => 'game', 'label' => 'mc-liga', 'notify' => false, 'sla_class' => 'premium']);
    expect(Notification::query()->where('audience', 'customer')->where('event', 'service.stopped_unexpectedly')->sole()->title)->toBe('Herní server mc-liga neběží');
    expect(MailOutbox::query()->where('template_key', 'service-stopped')->exists())->toBeFalse();
    $internal = Notification::query()->where('audience', 'internal')->where('event', 'service.stopped_unexpectedly')->sole();
    expect($internal->ref_id)->toBe($service->id)->and($internal->title)->toContain('mc-liga')->toContain('premium');
});

it('stays quiet when the stop has an explanation: a schedule, a panel under maintenance, an operation in flight, a suspension', function () {
    [, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    $state = 'offline';
    watchedGamePanelFake($state, [['object' => 'server_schedule', 'attributes' => ['id' => 5, 'name' => 'noc', 'is_active' => true, 'cron' => ['minute' => '0', 'hour' => '1', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'],
        'relationships' => ['tasks' => ['data' => [['object' => 'schedule_task', 'attributes' => ['id' => 1, 'sequence_id' => 1, 'action' => 'power', 'payload' => 'stop']]]]]]]]);
    watchPass($game);
    $this->travel(16)->minutes();
    $game = watchPass($game);
    expect(OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->exists())->toBeFalse()
        ->and(AvailabilityWatch::of($game))->toMatchArray(['alerted' => false, 'explained' => 'schedule']); // the customer's own nightly stop

    $vps = watchedVps($org);
    $status = 'stopped';
    watchedPveFake($status);
    $watch = app(AvailabilityWatch::class);

    // an operation in flight (a restore, a reinstall) stops the server on purpose
    $operation = Operation::query()->create(['service_id' => $vps->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class, 'state' => Operation::RUNNING, 'step' => 0, 'steps_total' => 2, 'actor_type' => 'system', 'idempotency_key' => 'watch-inflight', 'correlation_id' => 'c-watch', 'desired' => ['action' => 'restore', 'service_id' => $vps->id]]);
    foreach ([1, 2, 3] as $pass) {
        expect($watch->observe($vps->fresh(), 'stopped'))->toBeNull();
    }
    $operation->forceFill(['state' => Operation::SUCCEEDED])->save();

    // a suspended service is stopped by us; a transitional state is not a verdict
    $vps->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();
    expect($watch->observe($vps->fresh(), 'stopped'))->toBeNull();
    $vps->forceFill(['state' => ServiceStateMachine::ACTIVE])->save();
    expect($watch->observe($vps->fresh(), 'starting'))->toBeNull()->and($watch->observe($vps->fresh(), 'unknown'))->toBeNull();

    // a panel under a maintenance lock is only observed: what it answers is no ground for an alarm
    app(ProviderInstanceService::class)->setState(ProviderInstance::query()->where('key', 'proxmox-cz1')->firstOrFail(), 'maintenance', CommandContext::system('test'), 'kernel update', now()->addHour());
    foreach ([1, 2, 3] as $pass) {
        $this->travel(16)->minutes();
        watchPass($vps);
    }
    expect(OutboxMessage::query()->where('name', 'service.stopped_unexpectedly')->exists())->toBeFalse()
        ->and(AvailabilityWatch::of($vps->fresh())['down_since'])->toBeNull();
});
