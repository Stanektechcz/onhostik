<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Ops\PlatformBackup;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\Resilience\CircuitBreaker;

/**
 * P16 (phase R): the staff page "Provoz" — what an operator looks at first in a shift, in one read-only answer, from the same
 * sources the doctor and /metrics read: the scheduler and worker heartbeats, the operation backlog, outbox dead letters, failed
 * jobs and every scheduled job past its budget (P14); the platform backup and its restore drill (P13); bank lines nobody matched
 * and open dunning; managed certificates running out; nodes waiting for their qualification, panels held on an unverified version
 * and open circuit breakers.
 *
 * Each section is computed only for the person who may read it (the permission next to it); the others are named in `hidden`.
 * Only counts, states, times and the names of the platform's own jobs, nodes and panels leave it — no customer, no payload, no
 * error text a provider wrote (it can carry hostnames or credentials of the panel), no secret.
 */
final class OperationsOverview
{
    /** section => the permission that shows it */
    public const SECTIONS = [
        'automation' => 'provisioning.operation.read',
        'backups' => 'staff.backup.read',
        'payments' => 'billing.reconcile',
        'infrastructure' => 'provider.instance.read',
    ];

    /** How many named rows a list carries at most (the rest is a count). */
    private const LIST_LIMIT = 20;

    public function __construct(
        private readonly AutomationLedger $ledger,
        private readonly OutboxDeadLetters $deadLetters,
        private readonly ScheduleHealth $schedule,
        private readonly PlatformBackup $backup,
    ) {}

    /**
     * @param  callable(string): bool  $can  whether the person holds a permission at the global scope
     * @return array{generated_at: string, sections: array<string, array<string, mixed>>, hidden: list<string>}
     */
    public function for(callable $can): array
    {
        $sections = [];
        $hidden = [];
        foreach (self::SECTIONS as $section => $permission) {
            if (! $can($permission)) {
                $hidden[] = $section;

                continue;
            }
            $sections[$section] = match ($section) {
                'automation' => $this->automation(),
                'backups' => $this->backups(),
                'payments' => $this->payments(),
                'infrastructure' => $this->infrastructure(),
            };
        }

        return ['generated_at' => now()->toIso8601String(), 'sections' => $sections, 'hidden' => $hidden];
    }

    /** @return array<string, mixed> */
    private function automation(): array
    {
        $live = $this->ledger->liveness();
        $backlog = $this->ledger->backlog();
        $dead = $this->deadLetters->measure();
        $jobs = $this->schedule->jobs();
        $overdue = array_values(array_filter($jobs, fn (array $j) => $j['overdue']));

        return [
            'scheduler' => ['alive' => (bool) $live['scheduler']['alive'], 'last_seen_at' => $live['scheduler']['at']],
            'worker' => ['alive' => (bool) $live['worker']['alive'], 'last_seen_at' => $live['worker']['at'], 'driver' => $live['worker']['driver']],
            'backlog' => ['stale' => $backlog['stale'], 'by_queue' => $backlog['by_queue'], 'threshold' => $backlog['threshold'], 'age_minutes' => $backlog['age_minutes'], 'alert' => $backlog['alert']],
            'dead_letters' => ['count' => (int) $dead['count'], 'oldest_seconds' => (int) $dead['oldest_seconds']],
            'failed_jobs' => Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0,
            'scheduled_jobs' => [
                'count' => count($jobs),
                'overdue' => array_map(fn (array $j) => ['command' => $j['command'], 'age_seconds' => $j['age'], 'budget_seconds' => $j['budget'], 'failures_in_a_row' => $j['failures_in_a_row']], array_slice($overdue, 0, self::LIST_LIMIT)),
                'overdue_count' => count($overdue),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function backups(): array
    {
        $status = $this->backup->status();
        $verifiedAt = $status['verified']['at'] ?? null;

        return [
            'platform' => [
                'disk' => $status['disk'],
                'retention_days' => $status['retention_days'],
                'last_set' => $status['last']['set'] ?? null,
                'last_at' => $status['last']['at'] ?? null,
                'verified_set' => $status['verified']['set'] ?? null,
                'verified_at' => $verifiedAt,
                'verified_within_26h' => $verifiedAt !== null && CarbonImmutable::parse((string) $verifiedAt)->gt(now()->subHours(26)),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function payments(): array
    {
        $lastLine = DB::table('bank_statement_lines')->max('created_at');
        $dunning = DB::table('dunning_cases')->whereNotIn('state', ['RESOLVED', 'TERMINATED'])->selectRaw('state, count(*) as n')->groupBy('state')->pluck('n', 'state')->map(fn ($n) => (int) $n)->all();
        ksort($dunning);

        return [
            'bank' => ['last_line_at' => $lastLine === null ? null : CarbonImmutable::parse((string) $lastLine)->toIso8601String(), 'unmatched_lines' => (int) DB::table('bank_statement_lines')->where('state', 'unmatched')->count()],
            'dunning' => ['open' => array_sum($dunning), 'by_state' => $dunning],
        ];
    }

    /** @return array<string, mixed> */
    private function infrastructure(): array
    {
        $certs = DB::table('managed_certificates')->where('state', 'issued')->whereNotNull('expires_at');
        $qualifying = Node::query()->where('state', Node::QUALIFYING)->orderBy('created_at')->get(['id', 'name', 'role', 'region_code', 'created_at']);
        $instances = ProviderInstance::query()->platform()->orderBy('key')->get();
        $held = $instances->filter(fn (ProviderInstance $i) => data_get($i->version_gate, 'state') === 'held')->values();
        $http = app(ProviderHttpClient::class);
        $breakers = [];
        foreach ($instances as $instance) {
            $state = $http->breaker((string) $instance->key)->state();
            if ($state !== CircuitBreaker::CLOSED) {
                $breakers[] = ['instance' => (string) $instance->key, 'provider' => (string) $instance->provider, 'state' => $state];
            }
        }

        return [
            'certificates' => [
                'expiring_7d' => (int) (clone $certs)->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDays(7))->count(),
                'expired' => (int) (clone $certs)->where('expires_at', '<=', now())->count(),
            ],
            'nodes_qualifying' => [
                'count' => $qualifying->count(),
                'nodes' => $qualifying->take(self::LIST_LIMIT)->map(fn (Node $n) => ['id' => $n->id, 'name' => $n->name, 'role' => $n->role, 'region' => $n->region_code, 'since' => $n->created_at?->toIso8601String()])->values()->all(),
            ],
            'panels_held' => [
                'count' => $held->count(),
                'panels' => $held->take(self::LIST_LIMIT)->map(fn (ProviderInstance $i) => ['instance' => (string) $i->key, 'provider' => (string) $i->provider, 'version' => data_get($i->version_gate, 'version')])->all(),
            ],
            'breakers_open' => ['count' => count($breakers), 'instances' => array_slice($breakers, 0, self::LIST_LIMIT)],
        ];
    }
}
