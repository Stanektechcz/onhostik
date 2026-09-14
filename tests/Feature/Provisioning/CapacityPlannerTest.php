<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\CapacityPlanner;
use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/*
 * Automatic pre-provisioning (audit §5n-7): a short pool gets one capacity request sized like its largest node; operations
 * approve it (a human purchase, closed with the node's name) or the rule orders it from the vendor the pool's instance
 * names — the node row waits `pending` (never sellable) until operations put it active, which delivers the request.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function plannerShortPool(): array
{
    [$user, $org] = test()->customerWithOrganization();
    featureGameService($org, [], 77, 'e4c1abc0');
    featureGameService($org, [], 78, 'e4c1abc1');
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $hot = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    $hot->forceFill(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 40, 'ram_used_mb' => 45000, 'disk_used_gb' => 100], 'last_seen_at' => now()])->save();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 1024, 'disk_used_gb' => 10], 'last_seen_at' => now()]);
    for ($h = 7 * 24; $h >= 1; $h--) {
        NodeUsageSample::query()->create(['node_id' => $hot->id, 'sampled_at' => now()->subHours($h), 'cpu_pct' => 40, 'ram_used_mb' => (int) round(30000 + (7 * 24 - $h) * (15000 / (7 * 24))), 'disk_used_gb' => 100, 'source' => 'snapshot']);
    }

    return [$instance, $hot];
}

it('proposes one request per short pool, lets operations close a manual purchase and orders from the vendor when the rule is on', function () {
    [$instance] = plannerShortPool();
    $planner = app(CapacityPlanner::class);

    // the daily pass proposes once; operations see it with the capacity view
    $run = $planner->run();
    expect($run['proposed'])->toHaveCount(1)->and($run['ordered'])->toBe([])->and($planner->run()['proposed'])->toBe([]);
    $request = CapacityRequest::query()->firstOrFail();
    expect($request)->toMatchArray(['role' => 'game', 'region_code' => 'cz1', 'state' => 'proposed', 'provider_instance_id' => $instance->id, 'wanted_ram_mb' => 65536, 'wanted_cpu_cores' => 16])->and($request->days_left)->toBeLessThan(30)->and(data_get($request->meta, 'vendor'))->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'capacity.request.proposed')->where('body', 'like', '%64 GB RAM%ruční nákup%')->exists())->toBeTrue();
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    expect($this->getJson('/v1/staff/capacity')->assertOk()->json('data.requests.0.id'))->toBe($request->id);
    $list = $this->getJson('/v1/staff/capacity/requests')->assertOk()->json();
    expect($list['data'])->toHaveCount(1)->and($list['auto_order'])->toBeFalse();

    // a manual purchase: approve (nothing to order), then close it with the node that arrived
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'cap-1')->postJson("/v1/staff/capacity/requests/{$request->id}/decide", ['decision' => 'approve', 'note' => 'objednáno u Dellu'])->assertOk()->assertJsonPath('state', 'approved')->assertJsonPath('note', 'objednáno u Dellu');
    expect($this->getJson('/v1/staff/capacity/requests?state=approved')->json('data'))->toHaveCount(1);
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games03', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => [], 'last_seen_at' => now()]);
    $this->withHeader('Idempotency-Key', 'cap-2')->postJson("/v1/staff/capacity/requests/{$request->id}/decide", ['decision' => 'delivered', 'node_name' => 'games03'])->assertOk()->assertJsonPath('state', 'delivered')->assertJsonPath('node_name', 'games03');
    $this->withHeader('Idempotency-Key', 'cap-3')->postJson("/v1/staff/capacity/requests/{$request->id}/decide", ['decision' => 'cancel'])->assertStatus(409)->assertJsonPath('error', 'capacity_request_closed');
    expect($this->getJson('/v1/staff/capacity/requests')->json('data'))->toBe([]);
    Node::query()->where('name', 'games03')->delete();

    // a vendor on the instance: the rule off still only proposes; on, the request is ordered, the node waits pending and is never sellable
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['node_order' => ['driver' => 'hetzner', 'secret_ref' => 'env://HETZNER_CZ1', 'server_type' => 'cx42', 'image' => 'debian-12', 'location' => 'fsn1', 'ssh_keys' => ['ops']]])])->save();
    app(SecretStore::class)->write(SecretRef::parse('env://HETZNER_CZ1'), ['token' => 'hz-test-token']);
    Http::fake(['https://api.hetzner.cloud/v1/servers' => Http::sequence() // fakes accumulate across calls, so the vendor's three answers are one sequence: accepted, refused, accepted
        ->push(['server' => ['id' => 4711, 'name' => 'cz1-game03', 'public_net' => ['ipv4' => ['ip' => '203.0.113.20']]]], 201)
        ->push(['error' => ['code' => 'resource_limit_exceeded', 'message' => 'server limit reached']], 422)
        ->push(['server' => ['id' => 4712, 'name' => 'cz1-game03', 'public_net' => ['ipv4' => ['ip' => '203.0.113.21']]]], 201)]);
    expect($planner->run()['proposed'])->toHaveCount(1);
    $second = CapacityRequest::query()->where('state', CapacityRequest::PROPOSED)->firstOrFail();
    expect(data_get($second->meta, 'vendor'))->toBe('hetzner');
    Http::assertNothingSent();
    $sellableBefore = app(NodeScheduler::class)->sellableCapacity('game', 'cz1')['ram_mb'];
    $this->withHeader('Idempotency-Key', 'cap-4')->postJson("/v1/staff/capacity/requests/{$second->id}/decide", ['decision' => 'approve'])->assertOk()->assertJsonPath('state', 'ordered')->assertJsonPath('node_name', 'cz1-game03')->assertJsonPath('remote_id', '4711')->assertJsonPath('ip', '203.0.113.20');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.hetzner.cloud/v1/servers' && $r['server_type'] === 'cx42' && $r['name'] === 'cz1-game03' && $r['image'] === 'debian-12' && $r->header('Authorization')[0] === 'Bearer hz-test-token');
    $ordered = Node::query()->where('name', 'cz1-game03')->firstOrFail();
    expect($ordered->state)->toBe('pending')->and($ordered->remote_id)->toBe(4711)->and(data_get($ordered->tags, 'ordered.request'))->toBe($second->id);
    expect(app(NodeScheduler::class)->sellableCapacity('game', 'cz1')['ram_mb'])->toBe($sellableBefore);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'capacity.request.ordered')->where('title', 'Uzel objednán: cz1-game03')->exists())->toBeTrue();

    // operations install the hypervisor and put the node active: the next pass delivers the request
    expect($planner->run()['delivered'])->toBe([]);
    $ordered->forceFill(['state' => 'active'])->save();
    expect($planner->run()['delivered'])->toBe([$second->id])->and($second->refresh()->state)->toBe(CapacityRequest::DELIVERED);
    expect(app(NodeScheduler::class)->sellableCapacity('game', 'cz1')['ram_mb'])->toBeGreaterThan($sellableBefore);

    // the rule on: a short pool is ordered without a human; a vendor refusal leaves a failed request operations may retry
    CapacityRequest::query()->delete();
    Node::query()->where('name', 'cz1-game03')->delete();
    app(AutomationLedger::class)->setEnabled(CapacityPlanner::RULE, true, 'test');
    $run = $planner->run(); // the vendor's second answer: refused
    expect($run['proposed'])->toHaveCount(1)->and($run['ordered'])->toBe([]);
    $failed = CapacityRequest::query()->firstOrFail();
    expect($failed->state)->toBe(CapacityRequest::FAILED)->and(data_get($failed->meta, 'error'))->toBe('node_order_refused')->and($failed->decided_by)->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'capacity.request.failed')->exists())->toBeTrue();
    expect($planner->decide($failed, 'retry', null, CommandContext::system('test'))->state)->toBe(CapacityRequest::ORDERED); // the vendor's third answer: accepted
    expect(Artisan::call('onhost:provisioning:capacity-forecast'))->toBe(0)->and(Artisan::output())->toContain('requests proposed: 0');
    app(AutomationLedger::class)->setEnabled(CapacityPlanner::RULE, false, 'test');
    expect(app(AutomationLedger::class)->enabled(CapacityPlanner::RULE))->toBeFalse();
});
