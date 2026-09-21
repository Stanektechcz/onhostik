<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Errors\DomainError;

/*
 * The reverse record of a server reaches the resolver (audit §5ae). `IpAddress.rdns` was a column and nothing else:
 * provisioning wrote the VM's hostname into it and no PTR was ever published anywhere. Every VPS the platform
 * delivered had no reverse record, so mail leaving it was refused by most receivers — while the IPv4 add-on sells
 * `rdns: true`. The record also stayed on the address when it was released, so the next tenant inherited the reverse
 * name of the previous one.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** A delivered VPS with the addresses the platform allocated to it. */
function rdnsVps(object $org): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => 'vm-test.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'ipv4' => 1], 'sla_class' => 'standard', 'activated_at' => now(),
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => ['name' => 'vm-test'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "rdns:{$service->id}", 'adapter_version' => '1.0.0']);
    app(IpamService::class)->allocate(4, 'cz1', 'vps', $service->id, $org->id);

    return $service;
}

/** The reverse zone of the 192.0.2.0/24 range, delegated to the platform's own nameservers. */
function rdnsZone(string $name = '2.0.192.in-addr.arpa'): DnsZone
{
    $zone = DnsZone::query()->create([
        'organization_id' => null, 'name' => $name, 'provider' => 'powerdns', 'provider_instance_id' => pdnsLab()->id,
        'serial' => 1, 'version' => 1, 'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz'],
    ]);
    $base = 'pdns.mgmt.test:8081/api/v1/servers/localhost/zones';
    Http::fake([
        "{$base}/{$name}.?rrsets=false" => Http::response(['name' => "{$name}.", 'serial' => 1]),
        "{$base}/{$name}./notify" => Http::response([], 200),
        "{$base}/{$name}." => Http::response(['name' => "{$name}.", 'rrsets' => []]),
    ]);

    return $zone;
}

it('publishes the reverse record of a server and takes it away with the address', function () {
    rdnsZone();
    [, $org] = $this->customerWithOrganization();
    $service = rdnsVps($org);
    $address = IpAddress::query()->where('service_id', $service->id)->where('family', 4)->sole();
    expect((string) $address->address)->toBe('192.0.2.2');

    app(IpamService::class)->setReverseDns($address, 'vps1.zakaznik.cz');

    // against the old code the column changed and no record existed anywhere
    $ptr = DnsRecord::query()->where('type', 'PTR')->sole();
    expect($ptr->name)->toBe('2')->and($ptr->content)->toBe('vps1.zakaznik.cz.')->and($ptr->managed_by)->toBe('system')
        ->and($ptr->comment)->toBe('ip:192.0.2.2')
        ->and($address->fresh()->rdns)->toBe('vps1.zakaznik.cz');

    // released: the reverse name of the last tenant never follows the address to the next one
    app(IpamService::class)->release($address->fresh());
    expect(DnsRecord::query()->where('type', 'PTR')->count())->toBe(0)->and($address->fresh()->rdns)->toBeNull();
});

it('lets the customer name the reverse record of their own server, and nobody else’s address', function () {
    rdnsZone();
    [$user, $org] = $this->customerWithOrganization();
    $service = rdnsVps($org);
    $services = app(ServiceService::class);
    $context = $this->contextFor($user, $org);

    expect(app(ServiceFeatures::class)->features($service)['vm_rdns']['enabled'] ?? false)->toBeTrue();
    // the customer names a host, never an address: which address it belongs to is the platform's business
    expect(fn () => $services->requestAction($service, 'rdns.set', $context, 'rdns-bad-1', ['hostname' => '192.0.2.9']))
        ->toThrow(DomainError::class, 'hostname must be a full domain name');

    $operation = driveOperation($services->requestAction($service, 'rdns.set', $context, 'rdns-ok-1', ['hostname' => 'Mail.Zakaznik.CZ']));
    expect($operation->state)->toBe('SUCCEEDED', (string) data_get($operation->error, 'message', ''));
    expect(DnsRecord::query()->where('type', 'PTR')->sole()->content)->toBe('mail.zakaznik.cz.')
        ->and(IpAddress::query()->where('service_id', $service->id)->where('family', 4)->sole()->rdns)->toBe('mail.zakaznik.cz');

    // an empty name removes it again
    $off = driveOperation($services->requestAction($service, 'rdns.set', $context, 'rdns-off-1', ['hostname' => '']));
    expect($off->state)->toBe('SUCCEEDED')->and(DnsRecord::query()->where('type', 'PTR')->count())->toBe(0);
});

it('offers nothing it cannot publish: no reverse zone, no reverse record', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = rdnsVps($org); // no reverse zone for 192.0.2.0/24 here
    expect(app(ServiceFeatures::class)->features($service)['vm_rdns']['enabled'] ?? false)->toBeFalse();

    expect(fn () => app(ServiceService::class)->requestAction($service, 'rdns.set', $this->contextFor($user, $org), 'rdns-none-1', ['hostname' => 'vps1.zakaznik.cz']))
        ->toThrow(DomainError::class);
});

it('knows which zone an address belongs to, and prefers the longest delegation', function () {
    $wide = rdnsZone('0.192.in-addr.arpa');
    [$zone, $relative] = app(DnsService::class)->reverseZoneFor('192.0.2.7');
    expect($zone->id)->toBe($wide->id)->and($relative)->toBe('7.2');

    $narrow = rdnsZone(); // 2.0.192.in-addr.arpa — one label closer to the address
    [$zone, $relative] = app(DnsService::class)->reverseZoneFor('192.0.2.7');
    expect($zone->id)->toBe($narrow->id)->and($relative)->toBe('7');

    // IPv6 goes by nibbles; an address the platform holds no zone for has none
    expect(app(DnsService::class)->reverseZoneFor('2001:db8::1'))->toBeNull()
        ->and(DnsService::reverseLabels('2001:db8::1')[1])->toBe('ip6.arpa')
        ->and(implode('', array_slice(DnsService::reverseLabels('2001:db8::1')[0], 0, 4)))->toBe('1000')
        ->and(DnsService::reverseLabels('not-an-address'))->toBeNull();
});
