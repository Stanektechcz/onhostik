<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;

/**
 * How long things take — measured, not promised.
 *
 * "An action is done in seconds" is a number somebody has to be able to read: for every panel and every kind of
 * action, from the moment the request was accepted (`queued_at`) to the moment it was finished. The wait in the
 * queue is reported apart from the run itself, because the two have different cures (more workers vs. a slow panel).
 * The operations table keeps whole seconds, so that is the grain of every number here.
 *
 * Only what a person waits for counts against the target: a restart, a PHP switch, a new database. Backups, restores,
 * installs, migrations and the creation of a service are long by nature and are reported without a verdict.
 */
final class OperationLatency
{
    /** actions nobody expects to finish in seconds (reported, never judged) */
    public const LONG_BY_NATURE = ['backup', 'restore', 'archive.restore', 'terminate', 'purge', 'resize', 'snapshot', 'rollback_snapshot', 'reinstall', 'wp.install', 'wp.update', 'import.run', 'ssl.issue', 'ssl.wildcard', 'app.install', 'database.import', 'database.export', 'file.archive', 'file.extract', 'game.migrate'];

    public static function targetSeconds(): int
    {
        return max(1, (int) config('onhost.provisioning.latency_target_seconds', 30));
    }

    /**
     * @return list<array{provider:string, action:string, count:int, p50_s:float, p95_s:float, max_s:float, wait_p95_s:float, judged:bool, slow:bool}>
     */
    public function summary(int $hours = 24, int $limit = 5000): array
    {
        $rows = Operation::query()->where('state', Operation::SUCCEEDED)->whereNotNull('queued_at')->whereNotNull('finished_at')
            ->where('finished_at', '>=', now()->subHours(max(1, $hours)))->orderByDesc('finished_at')->limit(max(1, $limit))
            ->get(['id', 'kind', 'desired', 'provider_instance_id', 'queued_at', 'started_at', 'finished_at']);
        $providers = ProviderInstance::query()->whereIn('id', $rows->pluck('provider_instance_id')->filter()->unique()->all())->pluck('provider', 'id');
        $target = self::targetSeconds();

        return $rows->groupBy(fn (Operation $o) => ($providers[$o->provider_instance_id] ?? 'platform').'|'.self::actionOf($o))
            ->map(function (Collection $group, string $key) use ($target): array {
                [$provider, $action] = explode('|', $key, 2);
                $total = $group->map(fn (Operation $o) => self::seconds($o->queued_at, $o->finished_at))->sort()->values();
                $wait = $group->map(fn (Operation $o) => self::seconds($o->queued_at, $o->started_at ?? $o->finished_at))->sort()->values();
                $judged = ! self::longByNature($action);
                $p95 = self::percentile($total, 95);

                return ['provider' => $provider, 'action' => $action, 'count' => $group->count(), 'p50_s' => self::percentile($total, 50), 'p95_s' => $p95, 'max_s' => (float) $total->last(),
                    'wait_p95_s' => self::percentile($wait, 95), 'judged' => $judged, 'slow' => $judged && $p95 > $target];
            })
            ->sortBy([['slow', 'desc'], ['p95_s', 'desc']])->values()->all();
    }

    /** @return list<array{provider:string, action:string, count:int, p50_s:float, p95_s:float, max_s:float, wait_p95_s:float, judged:bool, slow:bool}> */
    public function slow(int $hours = 24): array
    {
        return array_values(array_filter($this->summary($hours), fn (array $row) => $row['slow'] && $row['count'] >= 3)); // one slow run is an anecdote
    }

    private static function actionOf(Operation $operation): string
    {
        $action = is_array($operation->desired) ? (string) ($operation->desired['action'] ?? '') : '';

        return $action !== '' ? $action : (string) $operation->kind;
    }

    /** Judged are the single actions on a living service; creating a service, a domain saga or a migration is long by nature. */
    private static function longByNature(string $action): bool
    {
        if (in_array($action, self::LONG_BY_NATURE, true) || preg_match('/^(staging|deploy|cdn|import|wp)\./', $action) === 1) {
            return true;
        }

        return ! in_array($action, ServiceActionWorkflow::ACTIONS, true);
    }

    private static function seconds(?DateTimeInterface $from, ?DateTimeInterface $to): float
    {
        if ($from === null || $to === null) {
            return 0.0;
        }

        return round(max(0.0, (float) $to->format('U.u') - (float) $from->format('U.u')), 2);
    }

    /** @param Collection<int, float> $sorted ascending */
    private static function percentile(Collection $sorted, int $p): float
    {
        $n = $sorted->count();
        if ($n === 0) {
            return 0.0;
        }

        return (float) $sorted[(int) min($n - 1, max(0, (int) ceil($p / 100 * $n) - 1))];
    }
}
