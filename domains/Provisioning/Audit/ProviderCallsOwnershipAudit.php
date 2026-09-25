<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Platform\Redaction\Redactor;

/**
 * Did anybody use the TASK-0005 hole before it was closed? (TASK-0020)
 *
 * Until TASK-0005 a customer could name any ISPConfig remote id — a shell user, a database user, a mailbox, an alias —
 * and the platform acted on it without checking that the service owned it. This walks the `service.action`
 * operations that named such an id, finds the write the platform sent for each (ISPConfig calls carry no operation id,
 * so the tie is the attempt's time window plus the same function and the same id), and decides whose the record was
 * from evidence the platform already logged. It also lists refusals made after the fix (someone still trying) and
 * writes no operation explains.
 *
 * Read-only by construction: Eloquent and query-builder reads only, no CommandBus, no ProviderRegistry — no panel is
 * called and no row is written. The only output is the report the command prints or files.
 */
final class ProviderCallsOwnershipAudit
{
    private const WINDOW_SLACK_SECONDS = 2;

    private const OPEN_ATTEMPT_MINUTES = 15;

    private const NEAREST_OPERATION_SECONDS = 60;

    private const PROBES_PER_ACTOR = 3;

    /** @param list<string> $instanceKeys ISPConfig instances to examine */
    public function run(CarbonImmutable $since, CarbonImmutable $until, array $instanceKeys, bool $includeClean = false): ProviderCallsAuditReport
    {
        $evidence = IspConfigOwnershipEvidence::build($instanceKeys);
        $map = PlatformOwnerMap::load();
        $judge = new OwnershipJudge($map, $evidence);
        $targets = OwnershipAuditTargets::targets();
        $actions = [];
        $refusals = [];
        Operation::query()->where('kind', 'service.action')->whereBetween('created_at', [$since, $until])
            ->select(['id', 'organization_id', 'service_id', 'state', 'actor_type', 'actor_id', 'desired', 'error', 'provider_instance_id', 'started_at', 'finished_at', 'created_at'])
            ->lazyById(500)
            ->each(function (Operation $operation) use (&$actions, &$refusals, $targets, $map, $judge, $instanceKeys): void {
                $target = $targets[(string) data_get($operation->desired, 'action', '')] ?? null;
                $instances = $target === null ? [] : $this->instancesOf($operation, $map, $instanceKeys);
                if ($instances === []) {
                    return;
                }
                $actions[] = $this->action($operation, $target, $instances, $judge);
                $refusal = $this->refusal($operation, $target);
                if ($refusal !== null) {
                    $refusals[$refusal['actor']][] = $refusal;
                }
            });
        $explained = array_fill_keys(array_merge([], ...array_column($actions, 'provider_call_ids')), true);
        $sections = [
            'actions' => $actions,
            'probes' => array_map(fn (array $group) => $this->probe($group), array_values($refusals)),
            'unattributed' => $this->unattributed($since, $until, $instanceKeys, $explained, $judge, $map, $includeClean),
        ];

        return new ProviderCallsAuditReport(
            ['generated_at' => CarbonImmutable::now()->toIso8601String(), 'since' => $since->toIso8601String(), 'until' => $until->toIso8601String(), 'instances' => $instanceKeys, 'include_clean' => $includeClean],
            self::summary($sections),
            $includeClean ? $sections['actions'] : array_values(array_filter($sections['actions'], fn (array $row) => ! in_array($row['severity'], ['CLEAN', 'INFO'], true))),
            $sections['probes'],
            array_values(array_filter($sections['unattributed'], fn (array $row) => $includeClean || $row['severity'] !== 'INFO')),
            $this->coverage($since, $instanceKeys, $evidence),
        );
    }

    /**
     * @param  array{kind:string, target:string, functions:list<string>, match:array{0:string,1:string}|null}  $target
     * @param  list<string>  $instances
     * @return array<string,mixed>
     */
    private function action(Operation $operation, array $target, array $instances, OwnershipJudge $judge): array
    {
        $value = (string) data_get($operation->desired, $target['target'], '');
        $sent = $this->sent($operation, $target, $instances);
        $id = ProviderCallRow::intOf($value);
        $verdict = match (true) {
            $target['kind'] === 'address' => $judge->address($operation->service_id, $value),
            $id === null => ['verdict' => OwnershipJudge::UNKNOWN, 'owner_service_id' => null, 'owner_organization_id' => null, 'owner_site' => null, 'owner_name' => null, 'owner_address' => null, 'evidence_call_ids' => [], 'notes' => ['non_canonical_id']],
            default => $judge->record($operation->service_id, $sent['instance'] !== null ? [$sent['instance']] : $instances, $target['kind'], $id),
        };
        $notes = array_merge($verdict['notes'], $id !== null && (string) $id !== $value ? ['non_canonical_id'] : [], $sent['by_time'] ? ['tied_by_time_only'] : []);

        return [
            'severity' => self::severity($verdict, $operation->organization_id, $sent['state']), 'verdict' => $verdict['verdict'],
            'operation_id' => $operation->id, 'action' => (string) data_get($operation->desired, 'action'), 'state' => $operation->state,
            'service_id' => $operation->service_id, 'organization_id' => $operation->organization_id, 'actor' => $operation->actor_type.':'.($operation->actor_id ?? '-'),
            'created_at' => $operation->created_at?->toIso8601String(), 'instance_key' => $sent['instance'] ?? $instances[0],
            'target_kind' => $target['kind'], 'target' => $value, 'sent' => $sent['state'], 'provider_call_ids' => $sent['calls'],
            'owner_service_id' => $verdict['owner_service_id'], 'owner_organization_id' => $verdict['owner_organization_id'], 'owner_site' => $verdict['owner_site'],
            'owner_name' => $verdict['owner_name'], 'owner_address' => $verdict['owner_address'], 'evidence_call_ids' => $verdict['evidence_call_ids'],
            'sys_datalog' => $id === null ? null : OwnershipAuditTargets::datalogKey($target['kind'], (string) $id), 'key_fingerprint' => $sent['key_fingerprint'], 'notes' => $notes,
        ];
    }

    /** @param array<string,mixed> $verdict */
    private static function severity(array $verdict, ?string $actingOrganization, string $sent): string
    {
        $accepted = in_array($sent, ['accepted', 'uncertain'], true); // a write the panel never answered may still have been applied

        return match ($verdict['verdict']) {
            OwnershipJudge::OWN => 'CLEAN',
            OwnershipJudge::UNKNOWN => in_array($sent, ['accepted', 'refused', 'uncertain'], true) ? 'REVIEW' : 'INFO',
            OwnershipJudge::FOREIGN_UNMANAGED => $accepted ? 'CRITICAL' : 'HIGH',
            default => match (true) {
                $verdict['owner_service_id'] === null => 'REVIEW',
                $verdict['owner_organization_id'] === $actingOrganization => 'MEDIUM',
                default => $accepted ? 'CRITICAL' : 'HIGH',
            },
        };
    }

    /**
     * The write the platform sent for the operation: the mapped function, on one of its instances, inside the time its
     * attempts ran, naming the same id. accepted = the panel said `ok` in the body (HTTP 200 alone proves nothing); uncertain = no
     * answer came back (a 5xx, a timeout), so the write may have been applied; refused = the panel answered and refused.
     *
     * @param  array{kind:string, target:string, functions:list<string>, match:array{0:string,1:string}|null}  $target
     * @param  list<string>  $instances
     * @return array{state:string, calls:list<string>, instance:?string, key_fingerprint:?string, by_time:bool}
     */
    private function sent(Operation $operation, array $target, array $instances): array
    {
        if ($target['functions'] === []) {
            return ['state' => 'none', 'calls' => [], 'instance' => null, 'key_fingerprint' => null, 'by_time' => false];
        }
        [$from, $to] = $this->attemptWindow($operation);
        $expected = $target['match'] === null ? null : (string) data_get($operation->desired, $target['match'][1], '');
        $hits = DB::table('provider_calls')->where('provider', 'ispconfig')->whereIn('instance_key', $instances)->whereIn('action', $target['functions'])
            ->whereBetween('created_at', [$from, $to])->orderBy('created_at')->orderBy('id')->get(['id', 'instance_key', 'body_code', 'http_status', 'request'])
            ->filter(fn (object $call) => $target['match'] === null || self::same(ProviderCallRow::requestOf($call)->get($target['match'][0]), (string) $expected))->values();
        if ($hits->isEmpty()) {
            return ['state' => 'not_sent', 'calls' => [], 'instance' => null, 'key_fingerprint' => null, 'by_time' => false];
        }
        $key = ProviderCallRow::requestOf($hits->first())->get('params.ssh_rsa');

        return [
            'state' => match (true) {
                $hits->contains(fn (object $call) => (string) $call->body_code === 'ok') => 'accepted',
                $hits->contains(fn (object $call) => $call->body_code === null || $call->http_status === null || (int) $call->http_status >= 500) => 'uncertain',
                default => 'refused',
            },
            'calls' => $hits->pluck('id')->map(fn ($id) => (string) $id)->all(), 'instance' => (string) $hits->first()->instance_key,
            'key_fingerprint' => is_string($key) && trim($key) !== '' && $key !== Redactor::MASK ? Redactor::fingerprint($key) : null,
            'by_time' => $target['match'] === null,
        ];
    }

    /** @return array{0:CarbonImmutable, 1:CarbonImmutable} from the first attempt's start to the last one's end */
    private function attemptWindow(Operation $operation): array
    {
        $attempts = DB::table('operation_attempts')->where('operation_id', $operation->id)->get(['started_at', 'finished_at']);
        $starts = $attempts->pluck('started_at')->filter()->map(fn ($at) => CarbonImmutable::parse((string) $at));
        $ends = $attempts->map(fn (object $a) => $a->finished_at !== null ? CarbonImmutable::parse((string) $a->finished_at) : CarbonImmutable::parse((string) $a->started_at)->addMinutes(self::OPEN_ATTEMPT_MINUTES));
        $from = $starts->min() ?? CarbonImmutable::parse((string) ($operation->started_at ?? $operation->created_at));
        $to = $ends->max() ?? ($operation->finished_at !== null ? CarbonImmutable::parse((string) $operation->finished_at) : $from->addMinutes(self::OPEN_ATTEMPT_MINUTES));

        return [$from->subSeconds(self::WINDOW_SLACK_SECONDS), $to->addSeconds(self::WINDOW_SLACK_SECONDS)];
    }

    private static function same(mixed $logged, string $expected): bool
    {
        $a = ProviderCallRow::intOf($logged);
        $b = ProviderCallRow::intOf($expected);
        if ($a !== null || $b !== null) {
            return $a !== null && $a === $b;
        }

        return is_string($logged) && $expected !== '' && mb_strtolower(trim($logged)) === mb_strtolower(trim($expected));
    }

    /**
     * @param  list<string>  $selected
     * @return list<string> the selected ISPConfig instances the operation's service has resources on
     */
    private function instancesOf(Operation $operation, PlatformOwnerMap $map, array $selected): array
    {
        $own = array_filter([$map->instanceKey($operation->provider_instance_id), ...$map->instancesOf($operation->service_id)]);

        return array_values(array_intersect($selected, array_unique($own)));
    }

    /**
     * A refusal made after the fix: the guard's `not_ours`, or the adapter's "not found on this site".
     *
     * @param  array{target:string}  $target
     * @return array<string,mixed>|null
     */
    private function refusal(Operation $operation, array $target): ?array
    {
        if ($operation->state !== Operation::FAILED) {
            return null;
        }
        $notOurs = data_get($operation->error, 'detail.not_ours');
        if (($notOurs === null || $notOurs === '') && ! str_contains(mb_strtolower((string) data_get($operation->error, 'message', '')), 'not found on this site')) {
            return null;
        }

        return [
            'actor' => $operation->actor_type.':'.($operation->actor_id ?? '-'), 'operation_id' => $operation->id, 'organization_id' => $operation->organization_id,
            'target' => is_scalar($notOurs) && (string) $notOurs !== '' ? (string) $notOurs : (string) data_get($operation->desired, $target['target'], ''),
            'at' => $operation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $group  the refusals of one actor
     * @return array<string,mixed>
     */
    private function probe(array $group): array
    {
        $targets = array_values(array_unique(array_column($group, 'target')));
        $numbers = array_values(array_filter(array_map(fn (string $t) => ProviderCallRow::intOf($t), $targets)));
        sort($numbers);
        $consecutive = false;
        for ($i = 1; $i < count($numbers); $i++) {
            $consecutive = $consecutive || $numbers[$i] - $numbers[$i - 1] === 1;
        }

        return [
            'severity' => count($group) >= self::PROBES_PER_ACTOR || $consecutive ? 'HIGH' : 'INFO', 'actor' => $group[0]['actor'], 'count' => count($group),
            'consecutive' => $consecutive, 'targets' => $targets, 'organization_ids' => array_values(array_unique(array_column($group, 'organization_id'))),
            'operation_ids' => array_column($group, 'operation_id'), 'first_at' => $group[0]['at'], 'last_at' => $group[count($group) - 1]['at'],
        ];
    }

    /**
     * Writes on a record that no operation above explains, judged by their owner. A write the platform made on its own
     * (a site's leftovers removed, sending switched for a whole domain) lands on a platform record and stays INFO.
     *
     * @param  list<string>  $instanceKeys
     * @param  array<string,true>  $explained
     * @return list<array<string,mixed>>
     */
    private function unattributed(CarbonImmutable $since, CarbonImmutable $until, array $instanceKeys, array $explained, OwnershipJudge $judge, PlatformOwnerMap $map, bool $includeClean): array
    {
        $rows = [];
        DB::table('provider_calls')->where('provider', 'ispconfig')->whereIn('instance_key', $instanceKeys)->whereIn('action', array_keys(OwnershipAuditTargets::UNATTRIBUTED_WRITES))
            ->whereBetween('created_at', [$since, $until])->select(['id', 'instance_key', 'action', 'body_code', 'http_status', 'request', 'created_at'])
            ->lazyById(1000, 'id')
            ->each(function (object $call) use (&$rows, $explained, $judge, $map, $includeClean): void {
                if (isset($explained[(string) $call->id])) {
                    return;
                }
                $rows[] = $this->unexplainedWrite($call, $judge, $map, $includeClean);
            });

        return $rows;
    }

    /** @return array<string,mixed> */
    private function unexplainedWrite(object $call, OwnershipJudge $judge, PlatformOwnerMap $map, bool $includeClean): array
    {
        $kind = OwnershipAuditTargets::UNATTRIBUTED_WRITES[(string) $call->action];
        $id = ProviderCallRow::requestOf($call)->primaryId();
        $verdict = $id === null ? ['verdict' => OwnershipJudge::UNKNOWN, 'owner_service_id' => null, 'owner_organization_id' => null, 'owner_name' => null, 'owner_address' => null, 'owner_site' => null, 'evidence_call_ids' => [], 'notes' => []]
            : $judge->record(null, [(string) $call->instance_key], $kind, $id);
        $name = $verdict['verdict'] === OwnershipJudge::FOREIGN_PLATFORM ? 'PLATFORM' : $verdict['verdict'];
        $severity = match ($name) {
            OwnershipJudge::FOREIGN_UNMANAGED => 'HIGH',
            OwnershipJudge::UNKNOWN => $call->body_code === null || (string) $call->body_code === 'ok' ? 'REVIEW' : 'INFO', // done or never answered
            default => 'INFO',
        };

        return [
            'severity' => $severity, 'verdict' => $name, 'provider_call_id' => (string) $call->id, 'instance_key' => (string) $call->instance_key, 'function' => (string) $call->action,
            'primary_id' => $id, 'body_code' => $call->body_code, 'http_status' => $call->http_status, 'created_at' => CarbonImmutable::parse((string) $call->created_at)->toIso8601String(),
            'owner_service_id' => $verdict['owner_service_id'], 'owner_organization_id' => $verdict['owner_organization_id'], 'owner_site' => $verdict['owner_site'],
            'owner_name' => $verdict['owner_name'], 'owner_address' => $verdict['owner_address'], 'evidence_call_ids' => $verdict['evidence_call_ids'],
            'sys_datalog' => $id === null ? null : OwnershipAuditTargets::datalogKey($kind, (string) $id),
            'nearest_operation' => $severity !== 'INFO' || $includeClean ? $this->nearestOperation((string) $call->instance_key, CarbonImmutable::parse((string) $call->created_at), $map) : null,
        ];
    }

    /** @return array{operation_id:string, action:?string, service_id:?string}|null the service action that ran closest in time on the instance */
    private function nearestOperation(string $instanceKey, CarbonImmutable $at, PlatformOwnerMap $map): ?array
    {
        $candidates = DB::table('operation_attempts as a')->join('operations as o', 'o.id', '=', 'a.operation_id')
            ->where('o.kind', 'service.action')->where('o.provider_instance_id', $map->instanceId($instanceKey))
            ->whereBetween('a.started_at', [$at->subSeconds(self::NEAREST_OPERATION_SECONDS), $at->addSeconds(self::NEAREST_OPERATION_SECONDS)])
            ->get(['o.id', 'o.service_id', 'o.desired', 'a.started_at']);
        $nearest = $candidates->sortBy(fn (object $row) => abs(CarbonImmutable::parse((string) $row->started_at)->diffInSeconds($at, false)))->first();

        return $nearest === null ? null : ['operation_id' => (string) $nearest->id, 'action' => (json_decode((string) $nearest->desired, true)['action'] ?? null), 'service_id' => $nearest->service_id];
    }

    /**
     * How far back the logs reach per instance. Nothing prunes `provider_calls` in code, so the real depth of history is
     * only known here; a window that starts before the oldest row is said to be uncovered.
     *
     * @param  list<string>  $instanceKeys
     * @return list<array<string,mixed>>
     */
    private function coverage(CarbonImmutable $since, array $instanceKeys, IspConfigOwnershipEvidence $evidence): array
    {
        return array_map(function (string $key) use ($since, $evidence): array {
            $row = DB::table('provider_calls')->where('provider', 'ispconfig')->where('instance_key', $key)->selectRaw('min(created_at) as oldest, max(created_at) as newest, count(*) as calls')->first();
            $oldest = $row?->oldest === null ? null : CarbonImmutable::parse((string) $row->oldest);

            return [
                'instance_key' => $key, 'oldest' => $oldest?->toIso8601String(), 'newest' => $row?->newest === null ? null : CarbonImmutable::parse((string) $row->newest)->toIso8601String(),
                'calls' => (int) ($row->calls ?? 0), 'unparseable' => $evidence->unparseable()[$key] ?? 0,
                'warning' => match (true) {
                    $oldest === null => 'No provider call of this instance is logged: nothing in the window could be checked.',
                    $oldest->greaterThan($since) => 'The log starts at '.$oldest->toIso8601String().', after the window start '.$since->toIso8601String().': the time before it was not checked.',
                    default => null,
                },
            ];
        }, $instanceKeys);
    }

    /**
     * @param  array<string, list<array<string,mixed>>>  $sections
     * @return array<string,int>
     */
    private static function summary(array $sections): array
    {
        $summary = array_fill_keys(ProviderCallsAuditReport::SEVERITIES, 0);
        foreach ((new Collection($sections))->flatten(1) as $row) {
            $summary[$row['severity']]++;
        }

        return $summary;
    }
}
