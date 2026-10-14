<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail\Reputation;

use Illuminate\Support\Carbon;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Mail\Reputation\Models\ReputationCheck;
use Onhost\Domain\Services\Mail\Reputation\Models\ReputationListing;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Dns\Dnsbl\DnsblLookup;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * SP1a (phase V, V-R1): asks the curated blocklists about every sending address and every customer mail domain
 * (`ReputationTargets`), through the operator's resolver (`DnsblLookup`), each (list, target) pair at most once per
 * `min_interval_minutes` and the longest-unasked first, within `max_queries_per_run` and `max_run_seconds` (query
 * etiquette, proposal §2). A list that refuses public resolvers is never asked without an own resolver (or DQS): its pairs
 * are recorded `refused`, which the doctor row "mail blocklist resolver is accepted" reports.
 *
 * A listing opens when a list starts answering (`mail.reputation.listed`, audited) and is cleared when it answers "not
 * listed" again (`mail.reputation.cleared`); an error answer (refused querier) or no answer changes nothing. The node's
 * `tags.blocklist` keeps the open major/minor listings of its addresses, and the operators still hear `node.blocklisted`
 * once a day per node while one is open (the old daily check's message). Nothing here acts on a listing — the incidents and
 * the remediation are SP1c; the customer is never told from here.
 */
final class ReputationMonitor
{
    public function __construct(
        private readonly DnsblLookup $dns,
        private readonly ReputationTargets $targets,
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{checked:int, listed:int, cleared:int, refused:int, failed:int, deferred:int} */
    public function run(): array
    {
        $stats = ['checked' => 0, 'listed' => 0, 'cleared' => 0, 'refused' => 0, 'failed' => 0, 'deferred' => 0];
        $lists = BlocklistCatalog::enabled();
        $targets = $this->targets->all();
        if ($lists === [] || $targets === []) {
            return $stats;
        }
        $due = $this->duePairs($lists, $targets);
        $budget = max(1, (int) config('onhost.antispam.dnsbl.max_queries_per_run', 2000));
        $deadline = now()->addSeconds(max(1, (int) config('onhost.antispam.dnsbl.max_run_seconds', 1800)));
        $ownResolver = $this->dns->isOwnResolver();
        foreach ($due as $i => [$listKey, $target]) {
            if ($stats['checked'] >= $budget || now()->greaterThanOrEqualTo($deadline)) {
                $stats['deferred'] = count($due) - $i; // the oldest first next time

                break;
            }
            $question = BlocklistCatalog::questionFor($listKey, $target['kind'], $target['target']);
            if ($question === null) {
                continue;
            }
            if (! $ownResolver && BlocklistCatalog::needsOwnResolver($listKey)) {
                // never asked through the system or a public resolver: the list refuses it, and its answer would say nothing
                $result = ['outcome' => 'refused', 'code' => null, 'weight' => null];
            } else {
                $answer = $this->dns->a($question);
                $stats['checked']++;
                $result = $answer->failed ? ['outcome' => 'failed', 'code' => null, 'weight' => null] : BlocklistCatalog::classify($listKey, $answer->addresses);
            }
            ReputationCheck::query()->updateOrCreate(['list_key' => $listKey, 'target' => $target['target']], [
                'target_kind' => $target['kind'], 'outcome' => $result['outcome'], 'code' => $result['code'], 'checked_at' => now(),
            ]);
            match ($result['outcome']) {
                'listed' => $this->listed($listKey, $target, (string) $result['code'], (string) $result['weight']) ? $stats['listed']++ : null,
                'clean' => $this->clean($listKey, $target) ? $stats['cleared']++ : null,
                'refused' => $stats['refused']++,
                default => $stats['failed']++,
            };
        }
        $this->tagNodes($targets);

        return $stats;
    }

    /**
     * Every (list, target) pair not asked within the interval, the never-asked first, then the oldest answer first.
     *
     * @param  array<string, array<string,mixed>>  $lists
     * @param  list<array{kind:string, target:string, node_id:?string, service_ids:list<string>}>  $targets
     * @return list<array{0:string, 1:array{kind:string, target:string, node_id:?string, service_ids:list<string>}}>
     */
    private function duePairs(array $lists, array $targets): array
    {
        $since = now()->subMinutes(max(1, (int) config('onhost.antispam.dnsbl.min_interval_minutes', 60)));
        $last = [];
        foreach (ReputationCheck::query()->get(['list_key', 'target', 'checked_at']) as $check) {
            $last[$check->list_key."\0".$check->target] = $check->checked_at;
        }
        $due = [];
        foreach ($targets as $target) {
            foreach (array_keys($lists) as $listKey) {
                if (! BlocklistCatalog::asks($listKey, $target['kind'])) {
                    continue;
                }
                $at = $last[$listKey."\0".$target['target']] ?? null;
                if ($at instanceof Carbon && $at->gt($since)) {
                    continue;
                }
                $due[] = [$listKey, $target, $at instanceof Carbon ? $at->getTimestamp() : 0];
            }
        }
        usort($due, fn (array $a, array $b) => $a[2] <=> $b[2]);

        return array_map(fn (array $pair) => [$pair[0], $pair[1]], $due);
    }

    /** @param  array{kind:string, target:string, node_id:?string, service_ids:list<string>}  $target */
    private function listed(string $listKey, array $target, string $code, string $weight): bool
    {
        $open = ReputationListing::query()->where('list_key', $listKey)->where('target', $target['target'])->where('state', ReputationListing::OPEN)->first();
        if ($open !== null) {
            $open->forceFill(['code' => $code, 'weight' => $weight, 'last_seen_at' => now(), 'service_ids' => $target['service_ids'] ?: null])->save();

            return false;
        }
        $list = (array) BlocklistCatalog::list($listKey);
        $listing = ReputationListing::query()->create([
            'list_key' => $listKey, 'zone' => (string) $list['zone'], 'list_name' => (string) $list['name'], 'target_kind' => $target['kind'], 'target' => $target['target'],
            'weight' => $weight, 'code' => $code, 'state' => ReputationListing::OPEN, 'node_id' => $target['node_id'], 'service_ids' => $target['service_ids'] ?: null,
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
        $payload = $this->payload($listing, $list);
        $this->audit->record(CommandContext::system('mail.reputation'), 'mail.reputation.listed', 'succeeded', $payload, 'mail_reputation_listing', $listing->id);
        $this->outbox->publish(GenericEvent::of('mail.reputation.listed', 'mail_reputation_listing', $listing->id, $payload));

        return true;
    }

    /** @param  array{kind:string, target:string, node_id:?string, service_ids:list<string>}  $target */
    private function clean(string $listKey, array $target): bool
    {
        $open = ReputationListing::query()->where('list_key', $listKey)->where('target', $target['target'])->where('state', ReputationListing::OPEN)->first();
        if ($open === null) {
            return false;
        }
        $open->forceFill(['state' => ReputationListing::CLEARED, 'cleared_at' => now()])->save();
        $payload = $this->payload($open, (array) BlocklistCatalog::list($listKey)) + ['listed_for_hours' => (int) round($open->first_seen_at->diffInMinutes(now()) / 60)];
        $this->audit->record(CommandContext::system('mail.reputation'), 'mail.reputation.cleared', 'succeeded', $payload, 'mail_reputation_listing', $open->id);
        $this->outbox->publish(GenericEvent::of('mail.reputation.cleared', 'mail_reputation_listing', $open->id, $payload));

        return true;
    }

    /** @param  array<string,mixed>  $list @return array<string,mixed> */
    private function payload(ReputationListing $listing, array $list): array
    {
        return [
            'list' => $listing->list_name, 'zone' => $listing->zone, 'target' => $listing->target, 'kind' => $listing->target_kind, 'weight' => $listing->weight,
            'code' => $listing->code, 'node_id' => $listing->node_id, 'service_ids' => (array) ($listing->service_ids ?? []),
            'delist' => (string) ($list['delist'] ?? ''), 'delist_url' => (string) ($list['url'] ?? ''),
        ];
    }

    /**
     * The node's own view (kept from the daily check it replaces): its open major/minor listings in `tags.blocklist`, and
     * `node.blocklisted` to the operators once a day while one is open.
     *
     * @param  list<array{kind:string, target:string, node_id:?string, service_ids:list<string>}>  $targets
     */
    private function tagNodes(array $targets): void
    {
        $byNode = [];
        foreach ($targets as $target) {
            if ($target['node_id'] !== null) {
                $byNode[$target['node_id']][] = $target['target'];
            }
        }
        foreach ($byNode as $nodeId => $addresses) {
            $node = Node::query()->find($nodeId);
            if ($node === null) {
                continue;
            }
            $listed = ReputationListing::query()->whereIn('target', $addresses)->where('state', ReputationListing::OPEN)->whereIn('weight', [BlocklistCatalog::MAJOR, BlocklistCatalog::MINOR])
                ->orderBy('first_seen_at')->get()
                ->map(fn (ReputationListing $l) => ['zone' => $l->zone, 'name' => $l->list_name, 'code' => $l->code, 'address' => $l->target, 'weight' => $l->weight])->values()->all();
            $tags = (array) ($node->tags ?? []);
            $before = (array) ($tags['blocklist'] ?? []);
            if ($listed === [] && $before === []) {
                continue; // a clean node that was never listed keeps its tags untouched
            }
            $tags['blocklist'] = ['listed' => $listed, 'checked_at' => now()->toIso8601String(), 'told_on' => $before['told_on'] ?? null];
            if ($listed !== [] && (string) ($before['told_on'] ?? '') !== now()->toDateString()) {
                $tags['blocklist']['told_on'] = now()->toDateString();
                $this->outbox->publish(GenericEvent::of('node.blocklisted', 'node', $node->id, [
                    'name' => $node->name, 'role' => $node->role, 'address' => implode(', ', array_unique(array_column($listed, 'address'))),
                    'lists' => implode(', ', array_unique(array_column($listed, 'name'))), 'listed' => $listed, 'count' => count($listed),
                ]));
            }
            $node->forceFill(['tags' => $tags])->save();
        }
    }
}
