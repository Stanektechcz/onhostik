<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;

/*
 * The same reserve is not promised twice (Brain card H04). The scheduler judged a node by its last measurement and
 * held nothing, so every order between two measurements was promised the same free space. A placement now holds what
 * it took until the node's next measurement shows it, placements are serialized, and a server that no registered
 * node can take is refused while it is still a cart.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** One compute node with room for exactly two 8 GB servers inside its sellable share (75 % of 32 GB, 8 GB measured). */
function reserveLabNode(array $overrides = []): Node
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $node->forceFill(array_merge(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 32768, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 10, 'ram_used_mb' => 8192, 'disk_used_gb' => 100, 'io_wait_pct' => 1], 'last_seen_at' => now()->subMinutes(5)], $overrides))->save();
    expect($instance->id)->toBe($node->provider_instance_id);

    return $node->refresh();
}

function reservePaidVps(Organization $org, int $ramMb = 8192): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute', 'state' => ServiceStateMachine::PAID, 'region_code' => 'cz1', 'desired_spec' => [], 'entitlements' => ['vcpu' => 2, 'ram_mb' => $ramMb, 'nvme_gb' => 80], 'sla_class' => 'standard']);
}

it('holds what a placement took until the node is measured again, so a burst of orders cannot share one reserve', function () {
    [, $org] = $this->customerWithOrganization();
    $node = reserveLabNode();
    $scheduler = app(NodeScheduler::class);
    $want = ['role' => 'compute', 'provider' => 'proxmox', 'region' => 'cz1', 'ram_mb' => 8192, 'cpu_cores' => 2, 'disk_gb' => 80];

    // 24 576 MB sellable, 8 192 measured: two more fit, the third does not — without any measurement in between
    $first = reservePaidVps($org);
    $second = reservePaidVps($org);
    $third = reservePaidVps($org);
    expect($scheduler->place($first, $want)['node']->id)->toBe($node->id)->and($first->fresh()->node_id)->toBe($node->id)
        ->and($scheduler->place($second, $want)['node']->id)->toBe($node->id);
    expect(fn () => $scheduler->place($third, $want))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('capacity_unavailable')->and($e->getMessage())->toContain('RAM 24576+8192'));
    expect($third->fresh()->node_id)->toBeNull(); // nothing was held for the one that did not fit

    // built and running, but the node was not measured since: still held
    $first->forceFill(['state' => ServiceStateMachine::ACTIVE, 'activated_at' => now()])->save();
    expect($scheduler->canHost($want))->toBeFalse();

    // the next measurement contains the first server; only the unbuilt second one is held on top of it
    $this->travel(10)->minutes();
    $node->forceFill(['usage' => array_replace((array) $node->usage, ['ram_used_mb' => 9000]), 'last_seen_at' => now()])->save();
    expect($scheduler->canHost($want))->toBeFalse()              // 9 000 + 8 192 held + 8 192 > 24 576
        ->and($scheduler->canHost(['ram_mb' => 4096] + $want))->toBeTrue();

    // a cancelled order gives its hold back
    $second->forceFill(['state' => ServiceStateMachine::FAILED])->save();
    expect($scheduler->place($third, $want)['node']->id)->toBe($node->id);
});

it('judges an instance by what was sold on it when the operator says so', function () {
    [, $org] = $this->customerWithOrganization();
    $node = reserveLabNode(['usage' => ['cpu_pct' => 5, 'ram_used_mb' => 2048, 'disk_used_gb' => 50, 'io_wait_pct' => 1]]); // idle guests: the measurement says the node is empty
    foreach ([1, 2, 3] as $i) {
        reservePaidVps($org)->forceFill(['state' => ServiceStateMachine::ACTIVE, 'activated_at' => now()->subDays(3), 'node_id' => $node->id, 'provider_instance_id' => $node->provider_instance_id])->save();
    }
    $want = ['role' => 'compute', 'provider' => 'proxmox', 'region' => 'cz1', 'ram_mb' => 8192];
    expect(app(NodeScheduler::class)->canHost($want))->toBeTrue(); // measured: 2 GB used of 24 GB sellable

    $instance = $node->providerInstance;
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['capacity_basis' => 'sold'])])->save();
    expect(app(NodeScheduler::class)->canHost($want))->toBeFalse(); // sold: 3 × 8 GB is the whole sellable share
});

it('refuses a server no node can take while it is still a cart, and does not judge where no node is registered', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [, $org] = $this->customerWithOrganization();
    $quote = fn (string $plan) => app(QuoteService::class)->quote([['product_key' => 'vps', 'plan_key' => $plan]], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, null, $org);

    // no compute node is registered: provisioning is not automatic here, there is nothing to judge by
    expect($quote('compute-8')->subtotal_minor)->toBeGreaterThan(0);

    reserveLabNode(['usage' => ['cpu_pct' => 10, 'ram_used_mb' => 12288, 'disk_used_gb' => 100, 'io_wait_pct' => 1]]); // 12 GB left in the sellable share
    $before = [Quote::query()->count(), Order::query()->count()];
    expect(fn () => $quote('compute-8'))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('capacity_sold_out')->and($e->status)->toBe(409)); // 16 GB does not fit
    expect([Quote::query()->count(), Order::query()->count()])->toBe($before); // nothing to pay for
    expect($quote('compute-4')->subtotal_minor)->toBe(44900); // 8 GB does

    // a drained node sells nothing; the switch lets the operator accept orders anyway
    Node::query()->where('name', 'prg1-n2')->update(['state' => 'drain']);
    expect(fn () => $quote('compute-4'))->toThrow(DomainError::class, 'sold out');
    config(['onhost.provisioning.capacity_gate' => false]);
    expect($quote('compute-4')->subtotal_minor)->toBe(44900);
});
