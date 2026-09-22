<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\CapacityPlanner;
use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodeBootstrap;
use Onhost\Domain\Provisioning\NodeQualification;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/*
 * Vendor node bootstrap (audit §5o-7): the vendor order carries cloud-init user-data with a one-time token; the host calls
 * back when it is up, the request turns ready, operations get the playbook line, the token is spent.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('ships the readiness call-back in the user-data and accepts it once', function () {
    [$user, $org] = $this->customerWithOrganization();
    featureGameService($org, [], 77, 'e4c1abc0');
    featureGameService($org, [], 78, 'e4c1abc1');
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $hot = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    $hot->forceFill(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 40, 'ram_used_mb' => 45000, 'disk_used_gb' => 100], 'last_seen_at' => now()])->save();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 1024, 'disk_used_gb' => 10], 'last_seen_at' => now()]);
    for ($h = 7 * 24; $h >= 1; $h--) {
        NodeUsageSample::query()->create(['node_id' => $hot->id, 'sampled_at' => now()->subHours($h), 'cpu_pct' => 40, 'ram_used_mb' => (int) round(30000 + (7 * 24 - $h) * (15000 / (7 * 24))), 'disk_used_gb' => 100, 'source' => 'snapshot']);
    }
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['node_order' => ['driver' => 'hetzner', 'secret_ref' => 'env://HETZNER_CZ1', 'server_type' => 'cx42', 'ssh_keys' => ['ops']]])])->save();
    app(SecretStore::class)->write(SecretRef::parse('env://HETZNER_CZ1'), ['token' => 'hz-test-token']);
    config()->set('onhost.provisioning.node_bootstrap.callback_base', 'https://cp.onhost.test');
    config()->set('onhost.provisioning.node_bootstrap.ssh_key', 'ssh-ed25519 AAAA-ops ops@onhost');
    Http::fake(['https://api.hetzner.cloud/v1/servers' => Http::response(['server' => ['id' => 4711, 'name' => 'cz1-game03', 'public_net' => ['ipv4' => ['ip' => '203.0.113.20']]]], 201)]);

    // the order carries cloud-init with the call-back URL and a token the platform only keeps hashed
    $planner = app(CapacityPlanner::class);
    $planner->run();
    $request = CapacityRequest::query()->firstOrFail();
    $planner->decide($request, 'approve', null, CommandContext::system('test'));
    $userData = null;
    Http::assertSent(function (Request $r) use (&$userData) {
        $userData = $r['user_data'] ?? null;

        return $r->url() === 'https://api.hetzner.cloud/v1/servers' && is_string($r['user_data']);
    });
    $request->refresh();
    expect($userData)->toStartWith('#cloud-config')->toContain("https://cp.onhost.test/v1/probes/capacity/{$request->id}/ready")->toContain('ssh-ed25519 AAAA-ops ops@onhost')->toContain('packages: [curl, ca-certificates, python3, sudo]');
    preg_match('/nb_[A-Za-z0-9]{40}/', $userData, $m);
    $token = $m[0] ?? '';
    expect($token)->toStartWith('nb_')->and(data_get($request->meta, 'ready_token_hash'))->toBe(hash('sha256', $token))->and($request->state)->toBe(CapacityRequest::ORDERED)->and($request->ready_at)->toBeNull();
    expect(app(NodeBootstrap::class)->userData($request, 'x'))->toContain("/v1/probes/capacity/{$request->id}/ready")->not->toContain('{token}')->not->toContain('{callback}');

    // the host calls back: a wrong token is a 404, the right one turns the request ready once and tells operations
    $this->postJson("/v1/probes/capacity/{$request->id}/ready", ['token' => 'nb_wrong', 'hostname' => 'cz1-game03'])->assertNotFound();
    $this->postJson('/v1/probes/capacity/capr_nope/ready', ['token' => $token])->assertNotFound();
    $ready = $this->postJson("/v1/probes/capacity/{$request->id}/ready", ['token' => $token, 'hostname' => 'cz1-game03', 'ip' => '203.0.113.20', 'os' => 'Debian GNU/Linux 12 (bookworm)', 'cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 960])->assertStatus(202)->json('data');
    expect($ready['ready_at'])->not->toBeNull()->and($ready['ready'])->toMatchArray(['ip' => '203.0.113.20', 'os' => 'Debian GNU/Linux 12 (bookworm)', 'cpu_cores' => 16])->and($ready['state'])->toBe(CapacityRequest::ORDERED);
    $node = Node::query()->where('name', 'cz1-game03')->firstOrFail();
    expect(data_get($node->tags, 'bootstrap.ip'))->toBe('203.0.113.20')->and($node->state)->toBe('pending');
    app(OutboxPublisher::class)->relayPending();
    $note = Notification::query()->where('audience', 'internal')->where('event', 'capacity.request.ready')->firstOrFail();
    expect($note->title)->toBe('Uzel je připraven na instalaci: cz1-game03')->and($note->body)->toContain('203.0.113.20')->toContain('site.yml --limit cz1-game03');
    $this->postJson("/v1/probes/capacity/{$request->id}/ready", ['token' => $token])->assertNotFound(); // spent
    expect(data_get($request->refresh()->meta, 'ready_token_hash'))->toBeNull();
    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    expect($this->getJson('/v1/staff/capacity/requests')->assertOk()->json('data.0.ready.hostname'))->toBe('cz1-game03');

    // §5p-7: the readiness answer carried a one-time activation token; the playbook posts it when the hypervisor is installed
    expect($ready['activate_token'])->toStartWith('na_')->and($ready['activate_url'])->toBe("https://cp.onhost.test/v1/probes/capacity/{$request->id}/activate");
    expect($note->body)->toContain('-e onhost_activate_url=https://cp.onhost.test/v1/probes/capacity/');
    $this->postJson("/v1/probes/capacity/{$request->id}/activate", ['token' => 'na_wrong'])->assertNotFound();
    $activated = $this->postJson("/v1/probes/capacity/{$request->id}/activate", ['token' => $ready['activate_token'], 'hostname' => 'cz1-game03', 'ip' => '203.0.113.20', 'os' => 'Debian 12', 'cpu_cores' => 16, 'ram_mb' => 64000])->assertStatus(202)->json('data');
    expect($activated['state'])->toBe(CapacityRequest::DELIVERED)->and($activated['activated_at'])->not->toBeNull()->and($activated['delivered_at'])->not->toBeNull();
    $node->refresh();
    // the machine is installed and said so from its own boot script — which is exactly as much as that proves. The vendor's
    // part is delivered; the node itself waits to be qualified before the scheduler may see it (H471).
    expect($node->state)->toBe(Node::QUALIFYING)->and($node->isSchedulable())->toBeFalse()
        ->and($node->capacity['ram_mb'])->toBe(64000)->and($node->capacity['cpu_cores'])->toBe(16)->and(data_get($node->tags, 'bootstrap.activated_at'))->not->toBeNull();
    $this->postJson("/v1/probes/capacity/{$request->id}/activate", ['token' => $ready['activate_token']])->assertNotFound(); // spent
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'capacity.request.activated')->where('title', 'Uzel je aktivní: cz1-game03')->exists())->toBeTrue()
        ->and(Notification::query()->where('event', 'capacity.request.delivered')->exists())->toBeTrue();
    // it does not count as capacity that can be sold: it is installed, not qualified
    expect(app(NodeScheduler::class)->sellableCapacity('game', 'cz1')['nodes'])->toBe(2);

    $node->forceFill(['failure_domain' => 'cz1-rack-a', 'capacity' => (array) $node->capacity + ['disk_gb' => 500], 'usage' => ['disk_used_gb' => 10]])->save();
    app(NodeQualification::class)->accept($node->refresh(), CommandContext::system('test'));
    expect(app(NodeScheduler::class)->sellableCapacity('game', 'cz1')['nodes'])->toBe(3); // and now it counts
});
