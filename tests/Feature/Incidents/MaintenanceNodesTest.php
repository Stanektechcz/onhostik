<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Incidents\InfrastructureStatus;
use Onhost\Domain\Incidents\MaintenanceAudience;
use Onhost\Domain\Incidents\MaintenanceService;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * W2-6: a planned maintenance window names the nodes (or service families) it touches; only the owners of the services on them are told —
 * when it is approved and a day before it starts, once — and see it in their API. While it is open the health check of an affected
 * service says so, without hiding a real bad finding. The public infrastructure page shows nodes under aliases with the time of their
 * last measurement (old = "neověřeno") and nothing of any customer.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Date::setTestNow('2026-10-12 09:00:00');
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
});

afterEach(fn () => Date::setTestNow());

function mtNode(string $name, ?string $sampledAt = 'now', string $state = 'active'): Node
{
    return Node::query()->create(['provider_instance_id' => 'pvi_mt', 'name' => $name, 'region_code' => 'cz1', 'role' => 'web', 'state' => $state, 'capacity' => ['sites' => 500],
        'usage' => $sampledAt === null ? [] : ['disk_used_gb' => 10, 'sampled_at' => $sampledAt === 'now' ? now()->toIso8601String() : $sampledAt], 'last_seen_at' => now()]);
}

function mtService(Organization $org, Node $node, string $host): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting Start', 'hostname' => $host, 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'node_id' => $node->id, 'entitlements' => [], 'desired_spec' => ['executor' => 'ispconfig', 'domain' => $host], 'sla_class' => 'standard', 'activated_at' => now()->subMonth()]);
}

/** A window planned and approved by two different people, through the staff API (permission, step-up and four eyes included). */
function mtWindow(object $test, array $extra = [], int $startsInHours = 72): string
{
    $staff = Closure::bind(fn () => $this->staff('sre'), $test, $test::class);
    $owner = $staff();
    $test->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $id = $test->postJson('/v1/staff/maintenance', $extra + ['title' => 'Výměna disků', 'rollback' => 'Vrátit staré disky', 'impact' => 'Krátké výpadky',
        'starts_at' => now()->addHours($startsInHours)->toIso8601String(), 'ends_at' => now()->addHours($startsInHours + 2)->toIso8601String()])->assertCreated()->json('id');
    app('auth')->forgetGuards();
    $approver = $staff();
    $test->actingAs($approver, 'sanctum');
    app(StepUpService::class)->grant($approver, 'totp', null, '127.0.0.1');
    $test->postJson("/v1/staff/maintenance/{$id}/approve")->assertOk()->assertJsonPath('state', 'approved');
    app('auth')->forgetGuards();

    return $id;
}

it('tells only the owners of services on the named nodes, in their own language', function () {
    [$ownerA, $orgA] = $this->customerWithOrganization();
    [$ownerB, $orgB] = $this->customerWithOrganization();
    [, $orgC] = $this->customerWithOrganization();
    $orgB->forceFill(['locale' => 'en'])->save();
    $affected = mtNode('web-a');
    $other = mtNode('web-b');
    $sameNodeButB = mtService($orgB, $affected, 'beta.cz');
    mtService($orgA, $affected, 'alfa.cz');
    mtService($orgC, $other, 'gama.cz');

    $id = mtWindow($this, ['affected_nodes' => [$affected->id], 'text_cs' => 'Měníme disky, web bude chvíli bez odpovědi.', 'text_en' => 'We are replacing disks; the site will briefly not answer.']);
    app(OutboxPublisher::class)->relayPending();

    $told = Notification::query()->where('event', 'maintenance.scheduled')->pluck('body', 'organization_id')->all();
    expect(array_keys($told))->toEqualCanonicalizing([$orgA->id, $orgB->id])
        ->and($told[$orgA->id])->toContain('Měníme disky')
        ->and($told[$orgB->id])->toContain('We are replacing disks');
    expect(MailOutbox::query()->where('template_key', 'maintenance')->pluck('to')->all())->toContain($ownerA->email, $ownerB->email)->toHaveCount(2);
    expect(Maintenance::query()->find($id)->affected_nodes)->toBe([$affected->id])
        ->and($sameNodeButB->organization_id)->toBe($orgB->id);
});

it('names families as well, ignores add-ons and refuses unknown nodes and families', function () {
    [, $org] = $this->customerWithOrganization();
    $node = mtNode('web-a');
    $parent = mtService($org, $node, 'alfa.cz');
    Service::query()->create(['organization_id' => $org->id, 'product_key' => 'oversight', 'family' => 'addon', 'name' => 'Dohled', 'state' => ServiceStateMachine::ACTIVE, 'node_id' => $node->id, 'entitlements' => [], 'tags' => ['parent_service_id' => $parent->id]]);

    $owner = $this->staff('sre');
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $base = ['title' => 'Údržba', 'rollback' => 'zpět', 'starts_at' => now()->addHours(72)->toIso8601String(), 'ends_at' => now()->addHours(74)->toIso8601String()];
    $this->postJson('/v1/staff/maintenance', $base + ['affected_nodes' => ['node_neexistuje']])->assertUnprocessable()->assertJsonPath('error', 'maintenance_nodes_invalid');
    $this->postJson('/v1/staff/maintenance', $base + ['affected_families' => ['hokus']])->assertUnprocessable()->assertJsonPath('error', 'maintenance_families_invalid');
    $this->postJson('/v1/staff/maintenance', $base)->assertUnprocessable(); // nothing named at all
    $window = Maintenance::query()->find($this->postJson('/v1/staff/maintenance', $base + ['affected_families' => ['web']])->assertCreated()->json('id'));

    expect(MaintenanceAudience::services($window)->pluck('id')->all())->toBe([$parent->id]); // not the add-on that copies the parent's node
});

it('sends the reminder a day before the start once, and not for a window announced inside its last day', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    $node = mtNode('web-a');
    mtService($orgA, $node, 'alfa.cz');
    mtService($orgB, mtNode('web-b'), 'beta.cz');
    $id = mtWindow($this, ['affected_nodes' => [$node->id]]);
    app(OutboxPublisher::class)->relayPending();
    $svc = app(MaintenanceService::class);

    expect($svc->tick()['reminded'])->toBe(0); // 72 h ahead: too early
    Date::setTestNow(now()->addHours(49)); // 23 h before the start
    expect($svc->tick()['reminded'])->toBe(1);
    app(OutboxPublisher::class)->relayPending();
    $reminders = Notification::query()->where('event', 'maintenance.reminder')->get();
    expect($reminders)->toHaveCount(1)->and($reminders->first()->organization_id)->toBe($orgA->id)->and($reminders->first()->title)->toContain('Připomínka');

    Date::setTestNow(now()->addMinutes(10));
    expect($svc->tick()['reminded'])->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'maintenance.reminder')->count())->toBe(1)
        ->and(Maintenance::query()->find($id)->reminded_at)->not->toBeNull();

    // an emergency window approved 3 hours ahead was announced inside its last day: marked, not reminded
    Date::setTestNow('2026-11-01 09:00:00');
    $emergency = mtWindow($this, ['affected_nodes' => [$node->id], 'emergency' => true, 'title' => 'Havárie'], startsInHours: 3);
    app(OutboxPublisher::class)->relayPending();
    $before = Notification::query()->where('event', 'maintenance.reminder')->count();
    expect($svc->tick()['reminded'])->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'maintenance.reminder')->count())->toBe($before)->and(Maintenance::query()->find($emergency)->reminded_at)->not->toBeNull();
});

it('lists a window for the organizations it touches, naming only their own services', function () {
    [$ownerA, $orgA] = $this->customerWithOrganization();
    [$ownerB, $orgB] = $this->customerWithOrganization();
    $node = mtNode('web-a');
    $mine = mtService($orgA, $node, 'alfa.cz');
    $foreign = mtService($orgB, $node, 'beta.cz');
    $untouched = mtService($orgB, mtNode('web-b'), 'delta.cz');
    mtWindow($this, ['affected_nodes' => [$node->id], 'text_cs' => 'Měníme disky.']);

    $a = $this->actingAs($ownerA, 'sanctum')->withHeader('X-Organization', $orgA->id)->getJson('/v1/my/maintenance')->assertOk()->json('data');
    expect($a)->toHaveCount(1)->and($a[0]['services'])->toBe([['id' => $mine->id, 'name' => 'alfa.cz']])->and($a[0]['text'])->toBe('Měníme disky.');
    expect(json_encode($a))->not->toContain($foreign->id)->not->toContain('beta.cz');

    app('auth')->forgetGuards();
    $b = $this->actingAs($ownerB, 'sanctum')->withHeader('X-Organization', $orgB->id)->getJson('/v1/my/maintenance')->assertOk()->json('data');
    expect($b[0]['services'])->toBe([['id' => $foreign->id, 'name' => 'beta.cz']])->and(json_encode($b))->not->toContain($mine->id)->not->toContain($untouched->id);

    // a window of somebody else's node is invisible to an organization with no service on it
    [$ownerD, $orgD] = $this->customerWithOrganization();
    app('auth')->forgetGuards();
    $this->actingAs($ownerD, 'sanctum')->withHeader('X-Organization', $orgD->id)->getJson('/v1/my/maintenance')->assertOk()->assertExactJson(['data' => []]);
    app('auth')->forgetGuards();
    $this->getJson('/v1/my/maintenance')->assertUnauthorized();
});

it('adds an information finding to the health check while the window is open, and hides no bad finding', function () {
    [, $org] = $this->customerWithOrganization();
    [, $orgOther] = $this->customerWithOrganization();
    $node = mtNode('web-a');
    $service = mtService($org, $node, 'alfa.cz');
    $untouched = mtService($orgOther, mtNode('web-b'), 'beta.cz');
    UptimeMonitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://alfa.cz/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10,
        'enabled' => true, 'notify' => true, 'state' => 'down', 'consecutive_failures' => 3, 'last_checked_at' => now()]);
    $id = mtWindow($this, ['affected_nodes' => [$node->id]]);
    $keys = fn (Service $s) => collect(app(ServiceHealthCheck::class)->run($s)['findings'])->keyBy('key');

    expect($keys($service))->not->toHaveKey('maintenance'); // announced, not yet open

    Date::setTestNow(now()->addHours(72)->addMinutes(5));
    app(MaintenanceService::class)->tick();
    UptimeMonitor::query()->where('service_id', $service->id)->update(['last_checked_at' => now()]);
    $during = $keys($service->fresh());
    expect($during)->toHaveKey('maintenance')->and($during['maintenance']['level'])->toBe('ok')->and($during['maintenance']['cs'])->toContain('plánovaná údržba')
        ->and($during['maintenance']['en'])->toContain('Planned maintenance')
        ->and($during['monitor']['level'])->toBe('bad') // the real outage is still reported
        ->and(app(ServiceHealthCheck::class)->run($service->fresh())['verdict'])->toBe('bad')
        ->and($keys($untouched))->not->toHaveKey('maintenance');

    Date::setTestNow(now()->addHours(3));
    app(MaintenanceService::class)->tick();
    expect($keys($service->fresh()))->not->toHaveKey('maintenance')
        ->and(Maintenance::query()->find($id)->state)->toBe('completed');
});

it('shows the public infrastructure page with aliases, measurement times and "not verified" for old readings, and no customer data', function () {
    [, $org] = $this->customerWithOrganization();
    $fresh = mtNode('secret-web-01.onhost.example', 'now');
    $old = mtNode('secret-web-02.onhost.example', now()->subDays(3)->toIso8601String());
    $never = mtNode('secret-web-03.onhost.example', null);
    $down = mtNode('secret-web-04.onhost.example', 'now', 'down');
    mtNode('secret-web-05.onhost.example', 'now', 'maintenance');
    Node::query()->create(['provider_instance_id' => 'pvi_mt', 'name' => 'mail-01', 'region_code' => 'cz1', 'role' => 'mail', 'state' => 'active', 'usage' => [], 'capacity' => []]);
    $service = mtService($org, $fresh, 'zakaznik-eshop.cz');
    $service->forceFill(['name' => 'Eshop Novák s.r.o.', 'label' => 'Eshop Novák s.r.o.'])->save();
    Node::query()->whereKey($fresh->id)->update(['tags' => json_encode(['ip' => '203.0.113.7'])]);

    $response = $this->getJson('/v1/status/infrastructure')->assertOk()->assertHeader('Cache-Control', 'max-age=60, public');
    $data = $response->json('data');
    $byKey = collect($data['components'])->keyBy('key');

    expect($data['components'])->toHaveCount(5)
        ->and($byKey['web-cz1-1']['state'])->toBe('operational')->and($byKey['web-cz1-1']['observed_at'])->toBe(now()->toIso8601String())
        ->and($byKey['web-cz1-1']['name']['cs'])->toBe('Webhosting · Praha · 1')
        ->and($byKey['web-cz1-2']['state'])->toBe('unknown')->and($byKey['web-cz1-2']['label']['cs'])->toBe('Neověřeno') // measured 3 days ago: stale, never green
        ->and($byKey['web-cz1-2']['observed_at'])->not->toBeNull()
        ->and($byKey['web-cz1-3']['state'])->toBe('unknown')->and($byKey['web-cz1-3']['observed_at'])->toBeNull()
        ->and($byKey['web-cz1-4']['state'])->toBe('down')
        ->and($byKey['web-cz1-5']['state'])->toBe('maintenance')
        ->and($data['summary'])->toBe(['operational' => 1, 'maintenance' => 1, 'down' => 1, 'unknown' => 2]);

    // nothing that identifies a node, a customer or an address leaves the platform
    $body = $response->getContent();
    expect($body)->not->toContain('secret-web')->not->toContain('onhost.example')->not->toContain($fresh->id)->not->toContain($service->id)->not->toContain($org->id)
        ->not->toContain('zakaznik-eshop')->not->toContain('Novák')->not->toContain('203.0.113.7')->not->toContain('mail-01')
        ->and(preg_match('/\b\d{1,3}(\.\d{1,3}){3}\b/', (string) $body))->toBe(0);
});

it('lists approved windows on the public page under aliases, keeps the answer for a minute and is rate limited', function () {
    [, $org] = $this->customerWithOrganization();
    $node = mtNode('secret-web-01.onhost.example');
    mtService($org, $node, 'zakaznik-eshop.cz');
    $id = mtWindow($this, ['affected_nodes' => [$node->id], 'text_cs' => 'Měníme disky.', 'text_en' => 'Replacing disks.']);

    $first = $this->getJson('/v1/status/infrastructure')->assertOk()->json('data');
    expect($first['maintenance'])->toHaveCount(1)->and($first['maintenance'][0])->toMatchArray(['state' => 'planned', 'components' => ['web-cz1-1'], 'title' => 'Výměna disků'])
        ->and($first['maintenance'][0]['text'])->toBe(['cs' => 'Měníme disky.', 'en' => 'Replacing disks.'])
        ->and(json_encode($first))->not->toContain($node->id)->not->toContain('zakaznik-eshop')->not->toContain($org->id);

    // kept for a minute: a change is not seen until it is over (and it is seen after)
    Maintenance::query()->whereKey($id)->update(['state' => 'cancelled']);
    expect($this->getJson('/v1/status/infrastructure')->json('data.maintenance'))->toHaveCount(1);
    Date::setTestNow(now()->addSeconds(InfrastructureStatus::CACHE_SECONDS + 1));
    expect($this->getJson('/v1/status/infrastructure')->json('data.maintenance'))->toHaveCount(0);

    $route = app('router')->getRoutes()->match(Request::create('/v1/status/infrastructure'));
    expect($route->gatherMiddleware())->toContain('throttle:public');
});
