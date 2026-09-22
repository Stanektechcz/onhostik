<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\Workflows\ProvisionVpsWorkflow;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\VirtualMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    pveLab();
    Http::preventStrayRequests();
});

it('provisions a VPS end-to-end: placement, IPAM, clone, sizing, cloud-init, firewall, start, verify, ACTIVE', function () {
    Http::fake([
        PVE.'/cluster/resources*' => Http::response(['data' => []]),
        PVE.'/cluster/nextid' => Http::response(['data' => '1042']),
        PVE.'/nodes/prg1-n2/qemu/9001/clone' => Http::response(['data' => 'UPID:prg1-n2:000A1B2C:0004E1F5:66F0AA11:qmclone:9001:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::sequence()->push(['data' => ['status' => 'running']])->whenEmpty(Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']])),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => fn (Request $r) => $r->method() === 'GET' ? Http::response(pveVmConfig()) : Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/qemu/1042/resize' => Http::response(['data' => 'UPID:prg1-n2:000A1B2D:0004E1F6:66F0AA12:resize:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/qemu/1042/cloudinit' => Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/qemu/1042/firewall/rules' => fn (Request $r) => $r->method() === 'GET' ? Http::response(['data' => []]) : Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/qemu/1042/firewall/options' => Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::sequence()->push(['data' => ['status' => 'stopped']])->push(['data' => ['status' => 'stopped']])->whenEmpty(Http::response(['data' => ['status' => 'running', 'uptime' => 7]])),
        PVE.'/nodes/prg1-n2/qemu/1042/status/start' => Http::response(['data' => 'UPID:prg1-n2:000A1B2E:0004E1F7:66F0AA13:qmstart:1042:onhost@pve!cp:']),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $product = Product::query()->where('key', 'vps')->firstOrFail();
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'compute-4'))->firstOrFail();

    $service = app(ServiceService::class)->create($org, $product, $version, ['ssh_keys' => ['ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIExample test@onhost'], 'options' => ['ipv4' => true]], $ctx);
    $operation = driveOperation(Operation::query()->where('service_id', $service->id)->firstOrFail());
    $service->refresh();

    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->kind)->toBe('provision.vps')->and($operation->step)->toBe(8);
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->node_id)->not->toBeNull()->and($service->provider_instance_id)->not->toBeNull()->and($service->hostname)->toEndWith('.cust.onhost.cz')->and($service->activated_at)->not->toBeNull();
    expect($service->entitlements['vcpu'])->toBe(4)->and($service->entitlements['ipv4'])->toBe(1);

    $binding = ProviderBinding::query()->where('service_id', $service->id)->firstOrFail();
    expect($binding->remote_type)->toBe('qemu')->and($binding->remote_id)->toBe('1042')->and($binding->remote_node)->toBe('prg1-n2');
    $vm = VirtualMachine::query()->where('service_id', $service->id)->firstOrFail();
    expect($vm->vmid)->toBe(1042)->and($vm->cores)->toBe(4)->and($vm->memory_mb)->toBe(8192)->and($vm->disk_gb)->toBe(160)->and($vm->state)->toBe('running');
    $v4 = IpAddress::query()->find($vm->ipv4_address_id);
    $v6 = IpAddress::query()->find($vm->ipv6_address_id);
    expect($v4->address)->toBe('192.0.2.2')->and($v4->state)->toBe('allocated')->and($v4->rdns)->toBe($service->hostname)->and($v6->address)->toStartWith('2001:db8:1:')->toEndWith('::/64')->and($v6->state)->toBe('allocated');
    expect($service->tags['access']['ipv4'])->toBe('192.0.2.2')->and($service->tags['access']['ipv6'])->toBe(rtrim($v6->address, '/64').'1');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/qemu/9001/clone') && (string) $r['newid'] === '1042' && (string) $r['full'] === '1' && $r['target'] === 'prg1-n2');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/qemu/1042/resize') && $r['size'] === '+140G' && $r['disk'] === 'scsi0');
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/qemu/1042/config') && ($r['ipconfig0'] ?? null) === 'ip=192.0.2.2/29,gw=192.0.2.1' && preg_match('#^ip6=2001:db8:1:[0-9a-f:]*1/64,gw6=2001:db8:1::1$#', (string) ($r['ipconfig1'] ?? '')) === 1 && str_contains((string) $r['sshkeys'], 'ssh-ed25519'));
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/qemu/1042/config') && ($r['cores'] ?? null) === 4 && ($r['memory'] ?? null) === 8192);
    expect(collect(Http::recorded())->filter(fn (array $p) => $p[0]->method() === 'POST' && str_ends_with($p[0]->url(), '/firewall/rules')))->toHaveCount(3);
    // the options are READ first (so a refused change can put them back) and then written: the write is the one that counts
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/firewall/options') && $r['policy_in'] === 'DROP' && $r['enable'] === 1);
    expect(AuditEvent::query()->where('action', 'service.activated')->where('resource_id', $service->id)->exists())->toBeTrue();
    expect(OutboxMessage::query()->where('name', 'service.activated')->where('aggregate_id', $service->id)->exists())->toBeTrue();
    expect(DB::table('provider_calls')->where('instance_key', 'proxmox-cz1')->count())->toBeGreaterThan(10);

    // idempotent: the provisioning key maps to the same operation, no second clone
    expect(app(OperationService::class)->start(ProvisionVpsWorkflow::class, "provision:{$service->id}", [], $ctx)->id)->toBe($operation->id);
    expect(collect(Http::recorded())->filter(fn (array $p) => str_ends_with($p[0]->url(), '/qemu/9001/clone')))->toHaveCount(1);
});

it('retries placement with backoff instead of failing the order when no node has N+1 capacity', function () {
    Node::query()->update(['usage' => ['cpu_pct' => 12, 'ram_used_mb' => 200000, 'disk_used_gb' => 500]]);
    Http::fake();
    [$user, $org] = $this->customerWithOrganization();
    $product = Product::query()->where('key', 'vps')->firstOrFail();
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'compute-4'))->firstOrFail();

    $service = app(ServiceService::class)->create($org, $product, $version, [], $this->contextFor($user, $org));
    $operation = Operation::query()->where('service_id', $service->id)->firstOrFail();
    expect($operation->state)->toBe(Operation::PENDING)->and($operation->step)->toBe(0)->and($operation->attempts)->toBe(1)->and($operation->next_run_at->greaterThan(now()->addMinutes(5)))->toBeTrue();
    expect($service->fresh()->state)->toBe(ServiceStateMachine::PROVISIONING)->and($operation->error['message'])->toContain('N+1');
    expect(OutboxMessage::query()->where('name', 'capacity.unavailable')->exists())->toBeTrue();
    Http::assertNothingSent();
});
