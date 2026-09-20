<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use App\Http\Presenters\Presenters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Staff operations board (audit §5e-3): what is stuck across all tenants right now — operations waiting on a node
 * error, failed in the last day, running suspiciously long — and the nodes behind them with their recent success and
 * failure counts. The same numbers drive the automatic drain: a node whose operations keep failing on transient
 * errors stops receiving new placements (state `draining`, the scheduler only picks `active` nodes) and is put back
 * once its operations succeed again; staff can drain or resume a node by hand at any time.
 */
final class OperationsBoard
{
    public const LONG_RUNNING_MINUTES = 10;

    public const DRAIN_FAILURES = 3;

    public const DRAIN_WINDOW_MINUTES = 15;

    public function __construct(private readonly OutboxPublisher $outbox, private readonly AuditRecorder $audit) {}

    /** @return array{stalled:list<array<string,mixed>>, failed:list<array<string,mixed>>, long_running:list<array<string,mixed>>, nodes:list<array<string,mixed>>, latency:array<string,mixed>, counts:array<string,int>} */
    public function board(): array
    {
        $now = CarbonImmutable::now();
        $stalled = Operation::query()->where('state', Operation::WAITING)->where(fn ($q) => $q->where('error->retryable', true)->orWhere('attempts', '>', 1))->orderBy('queued_at')->limit(100)->get();
        $failed = Operation::query()->where('state', Operation::FAILED)->where('finished_at', '>=', $now->subDay())->orderByDesc('finished_at')->limit(100)->get();
        $long = Operation::query()->where('state', Operation::RUNNING)->where('started_at', '<=', $now->subMinutes(self::LONG_RUNNING_MINUTES))->orderBy('started_at')->limit(50)->get();
        $present = fn (Operation $o) => Presenters::operation($o, true) + ['node' => $o->provider_instance_id ? ($this->instanceName($o->provider_instance_id)) : null];

        return [
            'stalled' => $stalled->map($present)->values()->all(),
            'failed' => $failed->map($present)->values()->all(),
            'long_running' => $long->map($present)->values()->all(),
            'nodes' => $this->nodes()->all(),
            // how long things take, by panel and action: from accepted to finished, the queue apart from the run (OperationLatency)
            'latency' => ['target_s' => OperationLatency::targetSeconds(), 'window_hours' => 24, 'rows' => array_slice((new OperationLatency)->summary(), 0, 60)],
            'counts' => ['stalled' => $stalled->count(), 'failed_24h' => $failed->count(), 'long_running' => $long->count(), 'draining' => Node::query()->where('state', 'draining')->count()],
        ];
    }

    /**
     * Per node: state, the instance's health, and the operations of the last window (succeeded, transient failures),
     * plus whether the automatic drain would act.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function nodes(): Collection
    {
        $window = CarbonImmutable::now()->subMinutes(self::DRAIN_WINDOW_MINUTES);
        $health = IntegrationHealth::query()->get()->keyBy('provider_instance_id');
        $instances = ProviderInstance::query()->get()->keyBy('id');

        return Node::query()->orderBy('region_code')->orderBy('name')->get()->map(fn (Node $node): array => $this->nodeRow($node, $window, $health, $instances));
    }

    /**
     * @param  Collection<array-key, IntegrationHealth>  $health
     * @param  Collection<array-key, ProviderInstance>  $instances
     * @return array<string,mixed>
     */
    private function nodeRow(Node $node, CarbonImmutable $window, Collection $health, Collection $instances): array
    {
        $ops = Operation::query()->where('provider_instance_id', $node->provider_instance_id)->where(fn ($q) => $q->where('queued_at', '>=', $window)->orWhere('finished_at', '>=', $window));
        $succeeded = (clone $ops)->where('state', Operation::SUCCEEDED)->count();
        $transient = (clone $ops)->whereIn('state', [Operation::WAITING, Operation::FAILED])->where('error->retryable', true)->count();
        $instance = $instances->get($node->provider_instance_id);
        $h = $health->get($node->provider_instance_id);

        return [
            'id' => $node->id, 'name' => $node->name, 'role' => $node->role, 'region' => $node->region_code, 'state' => $node->state,
            'instance' => $instance ? ['id' => $instance->id, 'key' => $instance->key, 'provider' => $instance->provider, 'state' => $instance->state] : null,
            'health' => $h ? ['up' => (bool) $h->up, 'error_rate_1h' => $h->error_rate_1h, 'checked_at' => $h->checked_at?->toIso8601String(), 'last_error' => $h->last_error] : null,
            'window_minutes' => self::DRAIN_WINDOW_MINUTES, 'succeeded' => $succeeded, 'transient_failures' => $transient,
            'auto_drained' => (bool) data_get($node->tags, 'auto_drain.at'), 'suggest_drain' => $node->state === 'active' && $transient >= self::DRAIN_FAILURES && $succeeded === 0,
            'bmc' => is_array(data_get($node->usage, 'bmc')) ? array_intersect_key((array) data_get($node->usage, 'bmc'), array_flip(['temp_max_c', 'fans_failed', 'psu_failed', 'psus', 'at'])) : null, // §5p-6: what the controller reported last
            'power_w' => data_get($node->usage, 'power_w'),
        ];
    }

    /**
     * Scheduled every few minutes: drain nodes that only fail, resume the ones the automatic drain took out once they
     * succeed again. Staff-set states are never touched (only nodes tagged `auto_drain` are resumed automatically).
     *
     * @return array{checked:int, drained:int, resumed:int}
     */
    public function autoDrain(): array
    {
        $stats = ['checked' => 0, 'drained' => 0, 'resumed' => 0, 'probed' => 0];
        $context = CommandContext::system('operations-board');
        foreach ($this->nodes() as $row) {
            $stats['checked']++;
            $node = Node::query()->find($row['id']);
            if ($node === null) {
                continue;
            }
            if ($row['suggest_drain']) {
                $this->setState($node, 'draining', "automatic: {$row['transient_failures']} transient failures in {$row['window_minutes']} min, no success", $context, true, $this->failedOperations($node));
                $stats['drained']++;
            } elseif ($node->state === 'draining' && $row['auto_drained'] && $row['succeeded'] > 0 && $row['transient_failures'] === 0) {
                $this->setState($node, 'active', 'automatic: operations succeed again', $context, true);
                $stats['resumed']++;
            } elseif ($node->state === 'draining' && $row['auto_drained'] && $row['succeeded'] === 0 && $row['transient_failures'] === 0 && ! data_get($node->tags, 'auto_drain.keep')) {
                // feedback loop (audit §5f-7): a drained node receives no work, so nothing would ever succeed there; probe the
                // integration instead and resume after two consecutive healthy probes — unless staff asked to keep it drained
                $stats['probed']++;
                $ok = $this->probeOk($node);
                $tags = (array) ($node->tags ?? []);
                $streak = $ok ? (int) data_get($tags, 'auto_drain.probe_ok', 0) + 1 : 0;
                $tags['auto_drain'] = array_merge((array) ($tags['auto_drain'] ?? []), ['probe_ok' => $streak, 'probed_at' => now()->toIso8601String()]);
                $node->forceFill(['tags' => $tags])->save();
                if ($streak >= self::RESUME_PROBES) {
                    $this->setState($node, 'active', "automatic: the integration answered {$streak} probes in a row", $context, true);
                    $stats['resumed']++;
                }
            }
        }

        return $stats;
    }

    /** Healthy probes in a row before an automatically drained node comes back without any operation succeeding. */
    public const RESUME_PROBES = 2;

    /** Drain or resume a node; staff and the automatic drain share this path (audit + `node.drained` / `node.resumed`). @param list<array<string,mixed>> $failed */
    public function setState(Node $node, string $state, ?string $reason, CommandContext $context, bool $automatic = false, array $failed = [], bool $keep = false): Node
    {
        $tags = (array) ($node->tags ?? []);
        if ($state === 'draining') {
            $tags['auto_drain'] = $automatic ? ['at' => now()->toIso8601String(), 'reason' => $reason] : null;
            if ($keep && $automatic === false) { // staff: "keep drained" — the feedback loop leaves the node alone
                $tags['auto_drain'] = ['at' => now()->toIso8601String(), 'reason' => $reason, 'keep' => true];
            }
        } else {
            unset($tags['auto_drain']);
        }
        $from = $node->state;
        $node->forceFill(['state' => $state, 'tags' => array_filter($tags, fn ($v) => $v !== null)])->save();
        $this->audit->record($context, 'provider.node.state', 'succeeded', ['from' => $from, 'to' => $state, 'reason' => $reason, 'automatic' => $automatic, 'failed' => array_column($failed, 'id')], 'node', $node->id);
        if ($from !== $state && in_array($state, ['draining', 'active'], true)) {
            $this->outbox->publish(GenericEvent::of($state === 'draining' ? 'node.drained' : 'node.resumed', 'node', $node->id, ['name' => $node->name, 'region' => $node->region_code, 'role' => $node->role, 'reason' => $reason, 'automatic' => $automatic, 'from' => $from, 'failed' => $failed]));
        }

        return $node;
    }

    /** The transient failures behind an automatic drain (id, step, message) — what staff see in the notification. @return list<array<string,mixed>> */
    private function failedOperations(Node $node): array
    {
        $window = CarbonImmutable::now()->subMinutes(self::DRAIN_WINDOW_MINUTES);

        return Operation::query()->where('provider_instance_id', $node->provider_instance_id)->whereIn('state', [Operation::WAITING, Operation::FAILED])->where('error->retryable', true)
            ->where(fn ($q) => $q->where('queued_at', '>=', $window)->orWhere('finished_at', '>=', $window))->orderByDesc('queued_at')->limit(5)->get()
            ->map(fn (Operation $o) => ['id' => $o->id, 'kind' => $o->kind, 'step' => $o->step_label, 'service_id' => $o->service_id, 'message' => mb_substr((string) ($o->error['message'] ?? ''), 0, 120)])->values()->all();
    }

    private function probeOk(Node $node): bool
    {
        $instance = $node->provider_instance_id ? ProviderInstance::query()->find($node->provider_instance_id) : null;
        if ($instance === null) {
            return false;
        }
        try {
            return (bool) (app(IntegrationHealthProbe::class)->probeInstance($instance)['up'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    private function instanceName(string $instanceId): ?string
    {
        static $names = [];

        return $names[$instanceId] ??= ProviderInstance::query()->whereKey($instanceId)->value('name');
    }
}
