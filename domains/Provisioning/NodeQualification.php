<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Carbon;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * A node nobody has qualified sells nothing (H471).
 *
 * `nodes.state` defaulted to `active`, and both ways a node comes into being put it there at once: a discovery from a
 * panel (`ProviderInstanceService::upsertNode`) and — worse — `NodeBootstrap::activate()`, which a freshly installed
 * machine calls **from its own boot script**. One curl from cloud-init and the scheduler would put a paying customer
 * on a host nobody had looked at: its size unknown, its disk possibly full, its failure domain unset so the spread
 * across hosts silently stopped meaning anything.
 *
 * A node is now born `qualifying`. It is listed, it is watched, and the scheduler does not see it (`isSchedulable()`
 * is `state === active`). Somebody — or something — has to look at it first, and what was looked at is written down
 * on the node so a handover can be read back later.
 *
 * What is checked here is what the platform can establish on its own, from what the panels already tell it. The
 * points that need a shell on the node itself — its clock (H472), what it can resolve (H473) and reach (H474), how
 * its management interface is exposed (H480) — are reported as `not_checked` with the reason, never as passed: a
 * check nobody made is not a check. The same goes for the synthetic service (H479), which is recorded as owed until
 * it can really be made and removed.
 */
final class NodeQualification
{
    /** Every point a qualification reports on; the required ones must pass before a node may carry anybody. */
    public const REQUIRED = ['instance', 'seen', 'capacity', 'placement', 'headroom'];

    public const INFORMATIONAL = ['clock', 'resolver', 'egress', 'management', 'synthetic'];

    /** How stale the panel's last word about a node may be and still count as "the node is there". */
    public const SEEN_MINUTES = 120;

    /** How much of a node's disk must be free before anything new is put on it. */
    public const DISK_HEADROOM_PCT = 15.0;

    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /**
     * Look at a node and write down what was found. Nothing is accepted here — this only establishes the facts.
     *
     * @return array{checked_at:string, points:array<string,array{status:string, detail:string}>, passed:bool, failed:list<string>}
     */
    public function inspect(Node $node): array
    {
        $instance = $node->provider_instance_id !== null ? ProviderInstance::query()->find($node->provider_instance_id) : null;
        $points = [];
        $ok = fn (string $detail) => ['status' => 'ok', 'detail' => $detail];
        $bad = fn (string $detail) => ['status' => 'failed', 'detail' => $detail];
        $open = fn (string $detail) => ['status' => 'not_checked', 'detail' => $detail];

        $points['instance'] = match (true) {
            $instance === null => $bad('the node belongs to no provider instance'),
            ! $instance->isUsable() => $bad("the instance {$instance->key} is {$instance->state}"),
            data_get($instance->capabilities, 'prereqs.api') === 'down' => $bad("the API of {$instance->key} did not answer the last prerequisite check"),
            default => $ok($instance->key.((array) data_get($instance->capabilities, 'prereqs.warnings', []) === [] ? '' : ' (with warnings on the instance)')),
        };

        $seen = $node->last_seen_at === null ? null : Carbon::parse($node->last_seen_at);
        $points['seen'] = $seen !== null && $seen->greaterThan(now()->subMinutes(self::SEEN_MINUTES))
            ? $ok('last confirmed '.$seen->diffForHumans())
            : $bad($seen === null ? 'the panel has never confirmed this node' : 'last confirmed '.$seen->diffForHumans());

        $ram = (int) $node->cap('ram_mb');
        $cores = (int) $node->cap('cpu_cores');
        $disk = (int) $node->cap('disk_gb');
        $points['capacity'] = $ram > 0 && $cores > 0 && $disk > 0
            ? $ok("{$cores} cores · {$ram} MB · {$disk} GB")
            : $bad('the node does not say how big it is ('.($cores > 0 ? '' : 'no cores, ').($ram > 0 ? '' : 'no memory, ').($disk > 0 ? '' : 'no disk').') — the scheduler cannot size anything on it');

        // a node with no failure domain is a node the spread across hosts cannot count: two copies may land on one rack
        $points['placement'] = match (true) {
            (string) $node->region_code === '' => $bad('the node is in no region'),
            (string) $node->role === '' => $bad('the node has no role'),
            (string) ($node->failure_domain ?? '') === '' => $bad('the node has no failure domain, so nothing can be spread away from it'),
            default => $ok("{$node->role} · {$node->region_code} · {$node->failure_domain}"),
        };

        $usedGb = (float) $node->use('disk_used_gb');
        $freePct = $disk > 0 ? round(100 - ($usedGb / $disk * 100), 1) : null;
        $points['headroom'] = match (true) {
            $disk <= 0 => $bad('the node does not say how big its disk is'),
            $freePct === null || $freePct < self::DISK_HEADROOM_PCT => $bad(($freePct ?? 0).'% of the disk is free, less than the '.self::DISK_HEADROOM_PCT.'% a node keeps for itself'),
            default => $ok($freePct.'% of the disk free'),
        };

        $points['clock'] = $open('the node\'s clock is not read from here yet (H472)');
        $points['resolver'] = $open('name resolution from the node itself is not read from here yet (H473)');
        $points['egress'] = $open('outbound reach from the node itself is not read from here yet (H474)');
        $points['management'] = $open('how the management interface is exposed is not read from here yet (H480)');
        $points['synthetic'] = $open('no service has been created and removed on this node yet (H479)');

        $failed = array_values(array_filter(self::REQUIRED, fn (string $key) => $points[$key]['status'] !== 'ok'));

        return ['checked_at' => now()->toIso8601String(), 'points' => $points, 'passed' => $failed === [], 'failed' => $failed];
    }

    /** Look at the node and keep the answer on it. */
    public function qualify(Node $node, CommandContext $context): array
    {
        $report = $this->inspect($node);
        $node->forceFill(['qualification' => array_merge((array) ($node->qualification ?? []), $report)])->save();
        $this->audit->record($context, 'provisioning.node.qualify', $report['passed'] ? 'succeeded' : 'failed',
            ['node' => $node->name, 'failed' => $report['failed']], 'node', $node->id);

        return $report;
    }

    /**
     * Put the node into the offer — never before it has passed, and never silently.
     *
     * @param  string|null  $exception  what is knowingly accepted anyway and why (H478); recorded, never hidden
     *
     * @throws DomainError when a required point has not passed
     */
    public function accept(Node $node, CommandContext $context, ?string $exception = null): Node
    {
        $report = $this->qualify($node, $context);
        if (! $report['passed']) {
            throw new DomainError('node_not_qualified', 'Uzel neprošel kvalifikací: '.implode(', ', array_map(
                fn (string $key) => $key.' ('.($report['points'][$key]['detail'] ?? '').')', $report['failed']
            )).'. Do nabídky ho nelze zařadit.', 409, ['failed' => $report['failed']]);
        }
        $node->forceFill([
            'state' => Node::ACTIVE, 'qualified_at' => now(),
            'qualification' => array_merge($report, ['accepted_by' => $context->actorId, 'accepted_at' => now()->toIso8601String(), 'exception' => $exception === null ? null : mb_substr($exception, 0, 250)]),
        ])->save();
        $this->audit->record($context, 'provisioning.node.accept', 'succeeded', ['node' => $node->name, 'exception' => $exception], 'node', $node->id);
        $this->outbox->publish(GenericEvent::of('node.qualified', 'node', $node->id, [
            'name' => $node->name, 'role' => $node->role, 'region' => $node->region_code, 'exception' => $exception,
        ]));

        return $node->refresh();
    }

    /**
     * Every node waiting to be qualified, with what its last look found.
     *
     * @return array{waiting:int, rows:list<array<string,mixed>>}
     */
    public function waiting(int $limit = 50): array
    {
        $rows = [];
        $nodes = Node::query()->where('state', Node::QUALIFYING)->orderBy('created_at')->orderBy('id')->limit($limit)->get();
        foreach ($nodes as $node) {
            $report = $this->inspect($node);
            $rows[] = ['node' => $node->name, 'id' => $node->id, 'role' => $node->role, 'region' => $node->region_code,
                'passed' => $report['passed'], 'failed' => implode(', ', $report['failed'])];
        }

        return ['waiting' => count($rows), 'rows' => $rows];
    }
}
