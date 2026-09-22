<?php

declare(strict_types=1);

use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodeBootstrap;
use Onhost\Domain\Provisioning\NodeQualification;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * `nodes.state` defaulted to `active`, and both ways a node comes into being put it there at once — a discovery from a
 * panel, and `NodeBootstrap::activate()`, which a freshly installed machine calls FROM ITS OWN BOOT SCRIPT. One curl
 * from cloud-init and the scheduler would place a paying customer on a host nobody had looked at (Brain card H471).
 */

function qualifiableNode(array $overrides = []): Node
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'proxmox-qual'], ['provider' => 'proxmox', 'name' => 'PVE qual', 'region_code' => 'cz1',
        'base_url' => 'https://pve.qual.test:8006', 'secret_ref' => 'env://PROXMOX_QUAL', 'state' => 'active', 'capabilities' => ['prereqs' => ['api' => 'up', 'warnings' => []]]]);

    // the overrides come first: with `+` the left-hand keys win
    return Node::query()->create($overrides + ['provider_instance_id' => $instance->id, 'name' => 'prg1-n9', 'region_code' => 'cz1', 'role' => 'compute',
        'state' => Node::QUALIFYING, 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => ['disk_used_gb' => 100],
        'failure_domain' => 'prg1-rack-c', 'last_seen_at' => now()]);
}

it('does not put a node into the offer just because it finished installing itself', function () {
    $instance = ProviderInstance::query()->create(['key' => 'hetzner-boot', 'provider' => 'proxmox', 'name' => 'Boot', 'region_code' => 'cz1',
        'base_url' => 'https://pve.boot.test:8006', 'secret_ref' => 'env://PROXMOX_BOOT', 'state' => 'active', 'capabilities' => []]);
    $node = Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'prg1-n8', 'region_code' => 'cz1', 'role' => 'compute',
        'state' => 'pending', 'capacity' => ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 500], 'usage' => [], 'tags' => []]);
    $request = CapacityRequest::query()->create(['region_code' => 'cz1', 'role' => 'compute', 'state' => CapacityRequest::ORDERED,
        'wanted_cpu_cores' => 8, 'wanted_ram_mb' => 32768, 'wanted_disk_gb' => 500, 'node_id' => $node->id, 'node_name' => $node->name,
        'provider_instance_id' => $instance->id, 'meta' => ['activate_token_hash' => hash('sha256', 'tok-1')]]);

    app(NodeBootstrap::class)->activate($request, 'tok-1', ['hostname' => 'prg1-n8', 'ip' => '203.0.113.8', 'cpu_cores' => 8, 'ram_mb' => 32768]);

    // it used to go straight to active — a curl from cloud-init was all it took to become sellable
    expect($node->refresh()->state)->toBe(Node::QUALIFYING)->and($node->isSchedulable())->toBeFalse()->and($node->isQualified())->toBeFalse();
});

it('does not put a node discovered from a panel into the offer either, and leaves a known node alone', function () {
    $instance = ProviderInstance::query()->create(['key' => 'ispconfig-disc', 'provider' => 'ispconfig', 'name' => 'Disc', 'region_code' => 'cz1',
        'base_url' => 'https://node.disc.test:8080', 'secret_ref' => 'env://ISPCONFIG_DISC', 'state' => 'active', 'capabilities' => []]);
    $service = app(ProviderInstanceService::class);
    $context = CommandContext::system('discovery');

    $fresh = $service->upsertNode($instance, ['name' => 'web-new', 'role' => 'web', 'capacity' => ['cpu_cores' => 4, 'ram_mb' => 8192, 'disk_gb' => 200]], $context);
    expect($fresh->state)->toBe(Node::QUALIFYING);

    // a node already in the offer keeps its state when the discovery runs again: qualification is not undone by a refresh
    $fresh->forceFill(['state' => Node::ACTIVE, 'qualified_at' => now()])->save();
    $again = $service->upsertNode($instance, ['name' => 'web-new', 'role' => 'web', 'capacity' => ['cpu_cores' => 4, 'ram_mb' => 8192, 'disk_gb' => 400]], $context);
    expect($again->state)->toBe(Node::ACTIVE)->and($again->cap('disk_gb'))->toBe(400.0);
});

it('names what a node still fails on, and refuses to accept it until it passes', function () {
    $qualification = app(NodeQualification::class);
    $context = CommandContext::system('test');

    // no failure domain: nothing can be spread away from this host, so the scheduler's spread means nothing
    $node = qualifiableNode(['failure_domain' => null]);
    $report = $qualification->inspect($node);
    expect($report['passed'])->toBeFalse()->and($report['failed'])->toBe(['placement'])
        ->and($report['points']['placement']['detail'])->toContain('failure domain');
    expect(fn () => $qualification->accept($node, $context))->toThrow(DomainError::class);
    expect($node->refresh()->state)->toBe(Node::QUALIFYING);

    // a full disk: the node is there, it is big, and there is nowhere to put anything
    $node->forceFill(['failure_domain' => 'prg1-rack-c', 'usage' => ['disk_used_gb' => 1950]])->save();
    expect($qualification->inspect($node->refresh())['failed'])->toBe(['headroom']);

    // a node the panel has not confirmed for hours is not a node we know is there
    $node->forceFill(['usage' => ['disk_used_gb' => 100], 'last_seen_at' => now()->subHours(5)])->save();
    expect($qualification->inspect($node->refresh())['failed'])->toBe(['seen']);
});

it('accepts a node that passes, writes down what was looked at, and never claims a check nobody made', function () {
    $node = qualifiableNode();
    $qualification = app(NodeQualification::class);

    $accepted = $qualification->accept($node, CommandContext::system('cli:nodes:qualify'), 'management interface still on the shared VLAN');

    expect($accepted->state)->toBe(Node::ACTIVE)->and($accepted->isSchedulable())->toBeTrue()->and($accepted->isQualified())->toBeTrue();
    $record = (array) $accepted->qualification;
    expect($record['passed'])->toBeTrue()->and($record['exception'])->toContain('shared VLAN')->and($record['accepted_at'])->not->toBeNull();
    // the points that need a shell on the node are reported as not checked — a check nobody made is not a pass
    foreach (NodeQualification::INFORMATIONAL as $key) {
        expect($record['points'][$key]['status'])->toBe('not_checked', $key);
    }
    expect($record['points']['capacity']['status'])->toBe('ok')->and($record['points']['headroom']['detail'])->toContain('% of the disk free');
});
