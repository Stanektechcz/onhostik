<?php

declare(strict_types=1);

use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodeQualification;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\SyntheticService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;

/*
 * A node can pass every check on paper and still not be able to make the one thing it is there for (Brain card H479).
 * The only proof is to make one, see it, remove it and see it gone — and a test resource that could not be removed
 * fails the node, because a node that leaves things behind will leave a customer's cancelled service behind too.
 */

beforeEach(fn () => SyntheticService::$sleeper = fn (int $seconds) => null);
afterEach(fn () => SyntheticService::$sleeper = null);

/**
 * A panel that makes a VM asynchronously and removes it — or does not, when `$keep` says so.
 *
 * @param  list<string>  $log
 */
function syntheticPanel(Node $node, array &$log, bool $keep = false): void
{
    $made = [];
    $adapter = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('provision')->andReturnUsing(function (ResourceSpec $spec) use (&$log, &$made) {
        $log[] = 'provision '.$spec->kind.' on '.$spec->node.($spec->get('synthetic') ? ' (synthetic)' : '');
        $made['9901'] = true;

        return ProviderResult::accepted(new AsyncHandle('pve_task', 'UPID:prg1-n7:create', 'prg1-n7'), new ResourceRef('qemu', '9901', 'lab', [], $spec->serviceId));
    });
    $polls = 0;
    $adapter->shouldReceive('awaitStatus')->andReturnUsing(function () use (&$polls, &$log) {
        $log[] = 'poll';

        return ++$polls % 2 === 1 ? AsyncStatus::running() : AsyncStatus::succeeded(); // one "still running" before each answer
    });
    // a closure by reference, not an arrow function: `fn` would capture `$made` as it was — empty — for ever
    $adapter->shouldReceive('getActualState')->andReturnUsing(function (ResourceRef $ref) use (&$made) {
        return isset($made[$ref->remoteId]) ? new ActualState(true, [], 'running') : ActualState::missing();
    });
    $adapter->shouldReceive('terminate')->andReturnUsing(function (ResourceRef $ref) use (&$log, &$made, $keep) {
        $log[] = 'terminate '.$ref->remoteId;
        if (! $keep) {
            unset($made[$ref->remoteId]);
        }

        return ProviderResult::completed(null, ['deleted' => ! $keep]);
    });

    $registry = app(ProviderRegistry::class);
    $known = new ReflectionProperty($registry, 'instances');
    $known->setValue($registry, [(string) $node->provider_instance_id => $adapter] + (array) $known->getValue($registry));
}

function syntheticNode(): Node
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'proxmox-syn'], ['provider' => 'proxmox', 'name' => 'PVE syn', 'region_code' => 'cz1',
        'base_url' => 'https://pve.syn.test:8006', 'secret_ref' => 'env://PROXMOX_SYN', 'state' => 'active', 'capabilities' => ['prereqs' => ['api' => 'up', 'warnings' => []]]]);

    return Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'prg1-n7', 'region_code' => 'cz1', 'role' => 'compute', 'state' => Node::QUALIFYING,
        'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => ['disk_used_gb' => 100], 'failure_domain' => 'prg1-rack-a', 'last_seen_at' => now()]);
}

it('makes nothing at all while the owner has not written a template for the role', function () {
    config(['onhost.provisioning.qualification.synthetic' => []]);
    $node = syntheticNode();
    $log = [];
    syntheticPanel($node, $log);
    $qualification = app(NodeQualification::class);

    $result = $qualification->synthetic($node, CommandContext::system('test'));

    expect($result['status'])->toBe('not_configured')->and($log)->toBe([]); // no panel was touched
    expect($qualification->required($node))->not->toContain('synthetic');
    expect($qualification->inspect($node->refresh())['points']['synthetic']['status'])->toBe('not_checked');
});

it('makes a throw-away machine on the node, sees it, removes it and sees it gone', function () {
    config(['onhost.provisioning.qualification.synthetic.compute' => ['template' => 9000, 'cores' => 1, 'memory_mb' => 512, 'disk_gb' => 5]]);
    $node = syntheticNode();
    $log = [];
    syntheticPanel($node, $log);
    $qualification = app(NodeQualification::class);

    // configured, not yet run: the synthetic point is now REQUIRED and the node cannot be accepted without it
    expect($qualification->required($node))->toContain('synthetic');
    expect(fn () => $qualification->accept($node, CommandContext::system('test')))->toThrow(DomainError::class);

    $result = $qualification->synthetic($node->refresh(), CommandContext::system('test'));
    expect($result['status'])->toBe('ok', $result['detail']);
    expect($result)->toMatchArray(['created' => '9901', 'removed' => true, 'leftover' => null]);
    expect($log[0])->toBe('provision vm on prg1-n7 (synthetic)')->and($log)->toContain('terminate 9901');

    $accepted = $qualification->accept($node->refresh(), CommandContext::system('test'));
    expect($accepted->state)->toBe(Node::ACTIVE)
        ->and(data_get($accepted->qualification, 'points.synthetic.status'))->toBe('ok')
        ->and(data_get($accepted->qualification, 'synthetic.removed'))->toBeTrue(); // the run stays on the record after acceptance
});

it('fails a node that leaves its test resource behind, and says what is left', function () {
    config(['onhost.provisioning.qualification.synthetic.compute' => ['template' => 9000, 'cores' => 1, 'memory_mb' => 512, 'disk_gb' => 5]]);
    $node = syntheticNode();
    $log = [];
    syntheticPanel($node, $log, keep: true);
    $qualification = app(NodeQualification::class);

    $result = $qualification->synthetic($node, CommandContext::system('test'));

    // creating is not enough: it was made, the removal "succeeded", and it is still there
    expect($result)->toMatchArray(['status' => 'failed', 'removed' => false, 'leftover' => '9901']);
    expect(collect($log)->filter(fn (string $l) => $l === 'terminate 9901')->count())->toBe(2); // and it was tried twice before giving up
    expect(fn () => $qualification->accept($node->refresh(), CommandContext::system('test')))->toThrow(DomainError::class);

    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'node.synthetic.leftover')->where('audience', 'internal')->exists())->toBeTrue();
});

it('does not count a synthetic run from long ago', function () {
    config(['onhost.provisioning.qualification.synthetic.compute' => ['template' => 9000, 'cores' => 1, 'memory_mb' => 512, 'disk_gb' => 5]]);
    $node = syntheticNode();
    $node->forceFill(['qualification' => ['synthetic' => ['status' => 'ok', 'removed' => true, 'detail' => 'created and removed 9901', 'at' => now()->subDays(30)->toIso8601String()]]])->save();

    $point = app(NodeQualification::class)->inspect($node->refresh())['points']['synthetic'];
    expect($point['status'])->toBe('failed')->and($point['detail'])->toContain('older than');
});
