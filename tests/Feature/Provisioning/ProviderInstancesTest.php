<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

beforeEach(fn () => Http::preventStrayRequests());

it('registers an aaPanel instance with console-managed credentials, encrypts them at rest and never returns them', function () {
    $admin = $this->staff('infrastructure_admin');
    $this->actingAs($admin, 'sanctum');
    $payload = ['key' => 'aapanel-managed02', 'provider' => 'aapanel', 'name' => 'aaPanel managed02', 'base_url' => 'https://managed02.onhost.internal:7800/', 'options' => ['public_ipv4' => '192.0.2.44', 'verify_tls' => false], 'credentials' => ['api_key' => 'aap_live_secret_0123456789']];

    $this->postJson('/v1/staff/integrations', $payload)->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($admin, 'totp', null, '127.0.0.1');
    $created = $this->postJson('/v1/staff/integrations', $payload)->assertCreated();
    expect($created->json('key'))->toBe('aapanel-managed02')->and($created->json('base_url'))->toBe('https://managed02.onhost.internal:7800')
        ->and($created->json('credentials.secret_ref'))->toBe('db://provider_instances/aapanel-managed02')->and($created->json('credentials.present'))->toBe(['api_key'])->and($created->json('credentials.missing'))->toBe([])
        ->and($created->json('capabilities'))->toBe(['web.create' => true]);
    expect(json_encode($created->json()))->not->toContain('aap_live_secret');

    // encrypted at rest, readable through the secret store, redacted in the audit trail
    $row = DB::table('secrets')->where('name', 'provider_instances/aapanel-managed02')->first();
    expect($row)->not->toBeNull()->and($row->payload)->not->toContain('aap_live_secret')->and(json_decode($row->keys, true))->toBe(['api_key']);
    expect(app(SecretStore::class)->read(SecretRef::parse('db://provider_instances/aapanel-managed02')))->toBe(['api_key' => 'aap_live_secret_0123456789']);
    $audit = AuditEvent::query()->where('action', 'provisioning.instance.upsert')->orderByDesc('created_at')->first();
    expect(json_encode($audit->detail))->not->toContain('aap_live_secret');

    // blank credential fields keep the stored value; options and base URL update
    $this->putJson('/v1/staff/integrations/aapanel-managed02', array_merge($payload, ['credentials' => ['api_key' => ''], 'options' => ['public_ipv4' => '192.0.2.45']]))->assertCreated()->assertJsonPath('options.public_ipv4', '192.0.2.45');
    expect(app(SecretStore::class)->read(SecretRef::parse('db://provider_instances/aapanel-managed02'))['api_key'])->toBe('aap_live_secret_0123456789');
    $this->putJson('/v1/staff/integrations/aapanel-managed02', ['key' => 'aapanel-managed02', 'provider' => 'proxmox', 'base_url' => 'https://x.example'])->assertStatus(409)->assertJsonPath('error', 'instance_provider_immutable');

    $schema = $this->getJson('/v1/staff/integrations/schema')->assertOk();
    expect(collect($schema->json('data.providers'))->firstWhere('provider', 'wedos')['credentials']['required'])->toBe(['login', 'wapi_password']);
    $show = $this->getJson('/v1/staff/integrations/aapanel-managed02')->assertOk();
    expect($show->json('data.credentials.managed_in_console'))->toBeTrue()->and($show->json('data.schema.credentials.hint'))->toContain('API interface');

    $this->actingAs($this->staff('sre'), 'sanctum');
    $this->postJson('/v1/staff/integrations', $payload)->assertForbidden(); // SRE reads integrations, infrastructure admins register them
});

it('tests the connection of a registered instance and imports Proxmox cluster nodes into the scheduler', function () {
    Http::fake([
        'https://pve2.onhost.internal:8006/api2/json/version' => Http::response(['data' => ['version' => '8.2.4', 'release' => '8.2']]),
        'https://pve2.onhost.internal:8006/api2/json/nodes' => Http::response(['data' => [
            ['node' => 'prg2-n1', 'status' => 'online', 'cpu' => 0.12, 'maxcpu' => 64, 'mem' => 68719476736, 'maxmem' => 274877906944, 'disk' => 500 * 1073741824, 'maxdisk' => 4000 * 1073741824, 'uptime' => 86400],
            ['node' => 'prg2-n2', 'status' => 'offline', 'maxcpu' => 64, 'maxmem' => 274877906944, 'maxdisk' => 4000 * 1073741824],
        ]]),
        'https://pve2.onhost.internal:8006/*' => Http::response(['data' => []]),
    ]);
    $admin = $this->staff('infrastructure_admin');
    $this->actingAs($admin, 'sanctum');
    app(StepUpService::class)->grant($admin, 'totp', null, '127.0.0.1');
    Region::query()->firstOrCreate(['code' => 'cz2'], ['name' => 'Praha 2', 'country' => 'CZ', 'datacenter' => 'PRG2', 'state' => 'active', 'meta' => []]);
    $base = ['key' => 'proxmox-cz2', 'provider' => 'proxmox', 'base_url' => 'https://pve2.onhost.internal:8006', 'options' => ['verify_tls' => false, 'storage' => 'nvme', 'bridge' => 'vmbr0'], 'credentials' => ['token_id' => 'onhost@pve!cp', 'token_secret' => 'e1f2a3b4-0000-4000-8000-000000000000']];
    $this->postJson('/v1/staff/integrations', $base + ['region_code' => 'nowhere'])->assertUnprocessable()->assertJsonPath('error', 'instance_region_unknown');
    $this->postJson('/v1/staff/integrations', $base)->assertCreated();
    $this->postJson('/v1/staff/integrations/proxmox-cz2/discover')->assertUnprocessable()->assertJsonPath('error', 'instance_region_required');
    $this->putJson('/v1/staff/integrations/proxmox-cz2', $base + ['region_code' => 'cz2'])->assertCreated()->assertJsonPath('region', 'cz2');

    $probe = $this->postJson('/v1/staff/integrations/proxmox-cz2/probe')->assertOk();
    expect($probe->json('up'))->toBeTrue()->and($probe->json('version'))->toBe('8.2.4')->and($probe->json('instance.health.up'))->toBeTrue();
    expect(ProviderInstance::query()->where('key', 'proxmox-cz2')->value('vendor_version'))->toBe('8.2.4');

    $discovered = $this->postJson('/v1/staff/integrations/proxmox-cz2/discover')->assertOk();
    expect($discovered->json('nodes'))->toBe(['prg2-n1', 'prg2-n2']);
    $n1 = Node::query()->where('name', 'prg2-n1')->firstOrFail();
    expect($n1->role)->toBe('compute')->and($n1->state)->toBe('active')->and($n1->capacity['cpu_cores'])->toBe(64)->and($n1->capacity['ram_mb'])->toBe(262144)->and($n1->capacity['disk_gb'])->toBe(4000)->and($n1->usage['cpu_pct'])->toBe(12);
    expect(Node::query()->where('name', 'prg2-n2')->value('state'))->toBe('unreachable');

    // manual nodes for executors without discovery; maintenance state pauses scheduling
    $this->postJson('/v1/staff/integrations/proxmox-cz2/nodes', ['name' => 'prg2-n3', 'role' => 'compute', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'failure_domain' => 'rack-c'])->assertCreated()->assertJsonPath('name', 'prg2-n3');
    $this->postJson('/v1/staff/integrations/proxmox-cz2/state', ['state' => 'maintenance', 'reason' => 'firmware update', 'maintenance_until' => now()->addHours(4)->toIso8601String()])->assertOk()->assertJsonPath('state', 'maintenance');
    expect(ProviderInstance::query()->where('key', 'proxmox-cz2')->first()->maintenance_until)->not->toBeNull();

    $this->postJson('/v1/staff/integrations/proxmox-cz2/discover')->assertOk();
    $this->postJson('/v1/staff/integrations/nope/probe')->assertNotFound();
});
