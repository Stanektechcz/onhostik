<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\NodeOrderProvider;

/**
 * Automatic pre-provisioning (audit §5n-7): a pool the forecast marks short gets one open capacity request sized like the
 * pool's largest node; operations approve it (`decision: approve`) or the rule `capacity.auto_order` approves it alone.
 * An approved request is ordered from the vendor when the pool's provider instance can order nodes (`NodeOrders`), the
 * node row is created `pending` (never sellable), and the request is delivered once operations put the node `active`.
 * Without a vendor the request waits for the human purchase and `decision: delivered {node_name}` closes it.
 */
final class CapacityPlanner
{
    public const RULE = 'capacity.auto_order';

    public function __construct(private readonly CapacityForecast $forecast, private readonly NodeOrders $orders, private readonly AutomationLedger $ledger, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox, private readonly CapacityBudget $budget) {}

    /**
     * The daily pass: propose for every short pool without an open request, order what the rule allows, track deliveries.
     *
     * @return array{proposed:list<string>, ordered:list<string>, delivered:list<string>}
     */
    public function run(): array
    {
        $out = ['proposed' => [], 'ordered' => [], 'delivered' => []];
        foreach ($this->forecast->forecast() as $pool) {
            if (! $pool['low']) {
                continue;
            }
            $request = $this->propose($pool);
            if ($request !== null) {
                $out['proposed'][] = $request->id;
                if ($this->ledger->enabled(self::RULE) && $this->vendorFor($request) !== null) {
                    $this->decide($request, 'approve', 'automatic: '.self::RULE, CommandContext::system('capacity.auto_order'));
                    if ($request->refresh()->state === CapacityRequest::ORDERED) {
                        $out['ordered'][] = $request->id;
                    }
                }
            }
        }
        foreach (CapacityRequest::query()->where('state', CapacityRequest::ORDERED)->get() as $ordered) {
            if ($this->track($ordered)) {
                $out['delivered'][] = $ordered->id;
            }
        }

        return $out;
    }

    /** One open request per pool; sized like the pool's largest node (that is what N+1 needs). @param array<string,mixed> $pool */
    public function propose(array $pool): ?CapacityRequest
    {
        $open = CapacityRequest::query()->where('role', $pool['role'])->where('region_code', $pool['region'])->whereIn('state', CapacityRequest::OPEN)->exists();
        if ($open) {
            return null;
        }
        $nodes = Node::query()->where('role', $pool['role'])->whereIn('state', ['active', 'drain'])->when($pool['region'] !== null, fn ($q) => $q->where('region_code', $pool['region']))->get();
        $largest = $nodes->sortByDesc(fn (Node $n) => $n->cap('ram_mb'))->first();
        $instance = $largest !== null ? ProviderInstance::query()->find($largest->provider_instance_id) : null;
        $request = CapacityRequest::query()->create([
            'role' => $pool['role'], 'region_code' => $pool['region'], 'provider_instance_id' => $instance?->id, 'state' => CapacityRequest::PROPOSED,
            'days_left' => $pool['days_left'], 'headroom_mb' => (int) $pool['headroom_mb'],
            'wanted_ram_mb' => (int) ($largest?->cap('ram_mb') ?: 65536), 'wanted_cpu_cores' => (int) ($largest?->cap('cpu_cores') ?: 16), 'wanted_disk_gb' => (int) ($largest?->cap('disk_gb') ?: 1000),
            'meta' => ['forecast' => $pool, 'vendor' => $instance !== null && $this->orders->canOrder($instance) ? (string) $instance->option('node_order.driver') : null],
        ]);
        $this->outbox->publish(GenericEvent::of('capacity.request.proposed', 'capacity_request', $request->id, self::present($request)));

        return $request;
    }

    /**
     * Operations decide: approve (orders from the vendor when there is one), cancel, delivered {node_name} for a manual
     * purchase, or retry after a failed order.
     */
    public function decide(CapacityRequest $request, string $decision, ?string $note, CommandContext $context, ?string $nodeName = null, bool $overrideBudget = false): CapacityRequest
    {
        if (! in_array($decision, ['approve', 'cancel', 'delivered', 'retry'], true)) {
            throw new DomainError('capacity_decision_invalid', 'Decision must be approve, cancel, delivered or retry.', 422, ['field' => 'decision']);
        }
        if ($decision === 'retry' && $request->state !== CapacityRequest::FAILED) {
            throw new DomainError('capacity_request_not_failed', 'Only a failed order can be retried.', 409, ['state' => $request->state]);
        }
        if ($decision !== 'retry' && ! in_array($request->state, CapacityRequest::OPEN, true)) {
            throw new DomainError('capacity_request_closed', 'This request was closed already.', 409, ['state' => $request->state]);
        }
        $request->forceFill(['decided_by' => $context->actorId, 'decided_at' => now(), 'note' => $note !== null ? mb_substr($note, 0, 500) : $request->note]);
        if ($decision === 'cancel') {
            $request->forceFill(['state' => CapacityRequest::CANCELLED])->save();
            $this->audit->record($context, 'capacity.request.cancel', 'succeeded', ['request' => $request->id, 'note' => $note], 'capacity_request', $request->id);

            return $request;
        }
        if ($decision === 'delivered') {
            $node = $nodeName !== null ? Node::query()->where('name', $nodeName)->where('role', $request->role)->first() : null;
            $request->forceFill(['state' => CapacityRequest::DELIVERED, 'delivered_at' => now(), 'node_name' => $nodeName ?? $request->node_name, 'node_id' => $node?->id])->save();
            $this->audit->record($context, 'capacity.request.delivered', 'succeeded', ['request' => $request->id, 'node' => $nodeName], 'capacity_request', $request->id);
            $this->outbox->publish(GenericEvent::of('capacity.request.delivered', 'capacity_request', $request->id, self::present($request)));

            return $request;
        }
        // approve / retry: order from the vendor when there is one, otherwise the purchase is a human step
        $request->forceFill(['state' => CapacityRequest::APPROVED])->save();
        $this->audit->record($context, 'capacity.request.approve', 'succeeded', ['request' => $request->id, 'note' => $note], 'capacity_request', $request->id);
        $vendor = $this->vendorFor($request);
        if ($vendor === null) {
            return $request;
        }
        $instance = ProviderInstance::query()->findOrFail($request->provider_instance_id);
        $name = $this->nodeName($request);
        $vendorOptions = (array) $instance->option('node_order', []);
        // §5q-5: the monthly budget cap — an automatic order that would cross it waits for a person, a person must override it with a note (the finance approval on the audit row)
        $estimate = $this->estimate($vendor, $request, $vendorOptions);
        if (! $overrideBudget && ! $this->budget->allows($estimate['cost_minor'], $estimate['currency'])) {
            $described = $this->budget->describe($estimate['cost_minor']);
            if ($context->actorType !== 'system') {
                throw new DomainError('capacity_budget_exceeded', 'This order would cross the monthly capacity budget ('.$described['cost'].' on top of '.$described['spent'].' of '.$described['budget'].'); approve again with override_budget and a note naming who approved it.', 409, $described + ['cost_minor' => $estimate['cost_minor'], 'field' => 'override_budget']);
            }
            $request->forceFill(['meta' => array_merge((array) $request->meta, ['budget_hold' => ['cost_minor' => $estimate['cost_minor'], 'currency' => $estimate['currency'], 'at' => now()->toIso8601String()]])])->save();
            $this->audit->record($context, 'capacity.request.budget_hold', 'succeeded', ['request' => $request->id] + $described, 'capacity_request', $request->id);
            $this->outbox->publish(GenericEvent::of('capacity.budget.exceeded', 'capacity_request', $request->id, $described + self::present($request)));

            return $request;
        }
        if (empty($vendorOptions['user_data'])) { // §5o-7: cloud-init prepares the host and calls back with a one-time token
            $vendorOptions['user_data'] = app(NodeBootstrap::class)->prepare($request)['user_data'];
        }
        try {
            $ordered = $vendor->order(['name' => $name, 'ram_mb' => (int) $request->wanted_ram_mb, 'cpu_cores' => (int) $request->wanted_cpu_cores, 'disk_gb' => (int) $request->wanted_disk_gb, 'region' => $request->region_code, 'options' => $vendorOptions + ['role' => $request->role]]);
        } catch (DomainError $e) {
            $request->forceFill(['state' => CapacityRequest::FAILED, 'meta' => array_merge((array) $request->meta, ['error' => $e->error, 'error_message' => $e->getMessage()])])->save();
            $this->audit->record($context, 'capacity.request.order', 'failed', ['request' => $request->id, 'error' => $e->error], 'capacity_request', $request->id);
            $this->outbox->publish(GenericEvent::of('capacity.request.failed', 'capacity_request', $request->id, self::present($request) + ['error' => $e->getMessage()]));

            return $request;
        }
        $node = Node::query()->create([
            'provider_instance_id' => $instance->id, 'name' => $ordered['name'], 'region_code' => $request->region_code, 'role' => $request->role, 'state' => 'pending', 'remote_id' => is_numeric($ordered['remote_id']) ? (int) $ordered['remote_id'] : null,
            'capacity' => ['cpu_cores' => (int) $request->wanted_cpu_cores, 'ram_mb' => (int) $request->wanted_ram_mb, 'disk_gb' => (int) $request->wanted_disk_gb], 'usage' => [],
            'tags' => ['ordered' => ['request' => $request->id, 'vendor' => (string) $instance->option('node_order.driver'), 'remote_id' => $ordered['remote_id'], 'ip' => $ordered['ip'], 'type' => $ordered['type'], 'at' => now()->toIso8601String()]],
        ]);
        $cost = $ordered['cost_minor'] ?? $estimate['cost_minor'];
        $request->forceFill(['state' => CapacityRequest::ORDERED, 'ordered_at' => now(), 'node_name' => $node->name, 'node_id' => $node->id, 'remote_id' => $ordered['remote_id'], 'cost_minor' => $cost, 'cost_currency' => $cost !== null ? strtoupper((string) ($ordered['currency'] ?? $estimate['currency'])) : null, 'meta' => array_merge(array_diff_key((array) $request->meta, ['budget_hold' => true]), ['ip' => $ordered['ip'], 'type' => $ordered['type'], 'budget_override' => $overrideBudget ?: null])])->save();
        $this->audit->record($context, 'capacity.request.order', 'succeeded', ['request' => $request->id, 'node' => $node->name, 'remote_id' => $ordered['remote_id'], 'cost_minor' => $cost, 'override_budget' => $overrideBudget], 'capacity_request', $request->id);
        $this->outbox->publish(GenericEvent::of('capacity.request.ordered', 'capacity_request', $request->id, self::present($request)));

        return $request;
    }

    /**
     * An ordered node that is installed closes the request: the vendor did their part.
     *
     * Qualifying it is ours and takes as long as it takes (H471), so `qualifying` counts as delivered here — otherwise
     * a request stays open for days and the planner keeps proposing hardware for capacity it has already bought.
     */
    public function track(CapacityRequest $request): bool
    {
        $node = $request->node_id !== null ? Node::query()->find($request->node_id) : null;
        if ($node === null || ! in_array($node->state, [Node::ACTIVE, Node::QUALIFYING], true)) {
            return false;
        }
        $request->forceFill(['state' => CapacityRequest::DELIVERED, 'delivered_at' => now()])->save();
        $this->outbox->publish(GenericEvent::of('capacity.request.delivered', 'capacity_request', $request->id, self::present($request)));

        return true;
    }

    public function vendorFor(CapacityRequest $request): ?NodeOrderProvider
    {
        $instance = $request->provider_instance_id !== null ? ProviderInstance::query()->find($request->provider_instance_id) : null;

        return $instance !== null ? $this->orders->for($instance) : null;
    }

    /** @return array<string,mixed> */
    public static function present(CapacityRequest $r): array
    {
        return [
            'id' => $r->id, 'role' => $r->role, 'region' => $r->region_code, 'provider_instance_id' => $r->provider_instance_id, 'state' => $r->state, 'days_left' => $r->days_left, 'headroom_mb' => (int) $r->headroom_mb,
            'wanted' => ['ram_mb' => (int) $r->wanted_ram_mb, 'cpu_cores' => (int) $r->wanted_cpu_cores, 'disk_gb' => (int) $r->wanted_disk_gb], 'node_name' => $r->node_name, 'node_id' => $r->node_id, 'remote_id' => $r->remote_id,
            'vendor' => data_get($r->meta, 'vendor'), 'ip' => data_get($r->meta, 'ip'), 'error' => data_get($r->meta, 'error_message'), 'note' => $r->note, 'decided_by' => $r->decided_by, 'decided_at' => $r->decided_at?->toIso8601String(),
            'ordered_at' => $r->ordered_at?->toIso8601String(), 'delivered_at' => $r->delivered_at?->toIso8601String(), 'created_at' => $r->created_at?->toIso8601String(),
            'ready_at' => $r->ready_at?->toIso8601String(), 'ready' => data_get($r->meta, 'ready'), // §5o-7
            'activated_at' => $r->activated_at?->toIso8601String(), // §5p-7
            'cost' => $r->cost_minor !== null ? ['minor' => (int) $r->cost_minor, 'currency' => $r->cost_currency] : null, 'budget_hold' => data_get($r->meta, 'budget_hold'), // §5q-5
        ];
    }

    /** The vendor's monthly price of what the request would order (the named type, else the smallest fitting one). @return array{cost_minor:?int, currency:?string} */
    private function estimate(NodeOrderProvider $vendor, CapacityRequest $request, array $vendorOptions): array
    {
        if (isset($vendorOptions['monthly_cost_minor'])) { // an operator-priced vendor (or a manual override on the instance)
            return ['cost_minor' => (int) $vendorOptions['monthly_cost_minor'], 'currency' => strtoupper((string) ($vendorOptions['currency'] ?? config('onhost.provisioning.capacity_budget.currency', 'EUR')))];
        }
        $catalogue = $vendor->catalogue($vendorOptions);
        if ($catalogue === []) {
            return ['cost_minor' => null, 'currency' => null];
        }
        $named = (string) ($vendorOptions['server_type'] ?? '');
        $row = null;
        foreach ($catalogue as $candidate) {
            if ($named !== '' ? $candidate['type'] === $named : ($candidate['ram_mb'] >= (int) $request->wanted_ram_mb && $candidate['cpu_cores'] >= (int) $request->wanted_cpu_cores && $candidate['disk_gb'] >= (int) $request->wanted_disk_gb)) {
                $row = $candidate;
                break;
            }
        }
        $row ??= $named === '' ? $catalogue[array_key_last($catalogue)] : null;

        return ['cost_minor' => $row['price_monthly_minor'] ?? null, 'currency' => $row['currency'] ?? null];
    }

    private function nodeName(CapacityRequest $request): string
    {
        $base = ($request->region_code !== null ? $request->region_code.'-' : '').$request->role;
        $n = Node::query()->where('role', $request->role)->when($request->region_code !== null, fn ($q) => $q->where('region_code', $request->region_code))->count() + 1;
        while (Node::query()->where('name', sprintf('%s%02d', $base, $n))->exists()) {
            $n++;
        }

        return sprintf('%s%02d', $base, $n);
    }
}
