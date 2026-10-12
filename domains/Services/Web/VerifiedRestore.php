<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflows\VerifiedRestoreWorkflow;
use Onhost\Domain\Services\BackupCompleteness;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\VerifiedRestoreRun;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * "Ověřená obnova" (W2-4): the paid step after the restore test (`RestoreTest`, which restores the dumps into databases of their
 * own and compares the tables). A verified restore takes the newest COMPLETE set of a web service, restores files and databases
 * into an isolated copy of the site (`RestoreTarget` — the staging facility, never the live site and never a site the platform
 * did not make), keeps the copy from acting on the world as far as the platform can and writes down what it could not, asks the
 * copy for its home page (and logs in only when the customer configured a test identity), measures RPO (how old the restored set
 * was) and RTO (from start to the checks done), removes the copy and checks it is gone — and writes a protocol of facts.
 *
 * Sold as the add-on `verified-restore` (`Addons`: it sets `verified_restore: monthly|quarterly` on the service it was bought
 * for and takes it back on cancellation); `RestoreTest::tick` (onhost:services:restore-test) asks for the due runs, staff can
 * ask for one through the bus (`restore.verify`). The result feeds the same record the restore test writes (`RestoreTest::TAG`),
 * so the security posture and the monthly report read one place.
 */
final class VerifiedRestore
{
    public const ACTION = 'restore.verify';

    public const ADDON = 'verified-restore';

    /** The key on the parent's entitlements the add-on sets. */
    public const ENTITLEMENT = 'verified_restore';

    public const CADENCE_DAYS = ['monthly' => 30, 'quarterly' => 91];

    /** The steps of a protocol, in the order they are shown. */
    public const STEPS = ['backup_selected', 'target_prepared', 'files_restored', 'database_imported', 'side_effects_suppressed', 'http_200', 'login', 'cleanup'];

    /** Steps whose failure means the restore did not come back (the cleanup is reported on its own). */
    private const VERDICT_STEPS = ['backup_selected', 'target_prepared', 'files_restored', 'database_imported', 'http_200', 'login'];

    public function __construct(private readonly OutboxPublisher $outbox, private readonly AuditRecorder $audit) {}

    // ── who is owed one ─────────────────────────────────────────────────────────────────────────────────

    public function cadence(Service $service): ?string
    {
        $cadence = (string) (((array) $service->entitlements)[self::ENTITLEMENT] ?? '');

        return array_key_exists($cadence, self::CADENCE_DAYS) ? $cadence : null;
    }

    public function cadenceDays(Service $service): ?int
    {
        $cadence = $this->cadence($service);

        return $cadence === null ? null : self::CADENCE_DAYS[$cadence];
    }

    /** A web service the add-on was bought for — never a carried site, a staging site or a copy of a verified restore. */
    public static function eligible(Service $service): bool
    {
        $tags = (array) $service->tags;

        return in_array($service->family, ['web', 'managed'], true)
            && ! isset($tags['parent_service_id']) && ! isset($tags['staging_of']) && ! isset($tags['restore_target_of']);
    }

    /** Whether the cadence asks for a run now (none running, the last one older than the cadence). */
    public function due(Service $service): bool
    {
        $days = $this->cadenceDays($service);
        if ($days === null || ! self::eligible($service) || $this->inFlight($service)) {
            return false;
        }
        $last = VerifiedRestoreRun::query()->where('service_id', $service->id)->max('started_at');

        return $last === null || Carbon::parse((string) $last)->lessThanOrEqualTo(now()->subDays($days));
    }

    /**
     * The newest COMPLETE platform set of the service, and the newer ones passed over on the way (meta.incomplete, BackupCompleteness).
     * A partial copy (the one taken before a database is overwritten) and a final archive are never candidates.
     *
     * @return array{backup: ?Backup, skipped: list<array{backup_id:string, taken_at:?string, reason:string}>}
     */
    public function pick(Service $service): array
    {
        $skipped = [];
        $sets = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->whereNull('remote_id')->where('kind', '!=', 'final')
            ->orderByDesc('finished_at')->orderByDesc('started_at')->orderByDesc('id')->limit(50)->get();
        foreach ($sets as $backup) {
            if (! FinalArchive::isSet($backup) || in_array(BackupCompleteness::PARTIAL_GAP, array_map('strval', (array) data_get($backup->meta, 'gaps', [])), true)) {
                continue;
            }
            if (data_get($backup->meta, 'incomplete') !== null) {
                $missing = BackupCompleteness::missingOf($backup);
                $problems = array_map('strval', (array) data_get($backup->meta, 'incomplete.problems', []));
                $skipped[] = ['backup_id' => $backup->id, 'taken_at' => self::takenAt($backup)?->toIso8601String(),
                    'reason' => mb_substr($missing !== [] ? 'chybí databáze: '.implode(', ', $missing) : ($problems[0] ?? 'sada není úplná'), 0, 200)];

                continue;
            }

            return ['backup' => $backup, 'skipped' => $skipped];
        }

        return ['backup' => null, 'skipped' => $skipped];
    }

    /**
     * The checks `ServiceService::requestAction` makes before a run starts: one run per service at a time, and at most
     * `onhost.verified_restore.max_parallel` scheduled runs on the platform (each one makes a site on a node).
     */
    public function assertCanStart(Service $service, CommandContext $context): void
    {
        if (! self::eligible($service)) {
            throw new DomainError('feature_unavailable', 'Ověřenou obnovu lze provést jen u webové služby, ne u stagingu ani u přidruženého webu.', 422, ['action' => self::ACTION]);
        }
        if ($this->inFlight($service)) {
            throw new DomainError('operation_in_progress', 'Ověřená obnova této služby už běží.', 409);
        }
        $parallel = max(1, (int) config('onhost.verified_restore.max_parallel', 2));
        // counted by the operations, not the protocol rows: a run asked for a moment ago has no row until its first step ran
        $busy = Operation::query()->where('kind', VerifiedRestoreWorkflow::kind())->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->count();
        if ($context->actorType === 'system' && $busy >= $parallel) {
            throw new DomainError('verified_restore_busy', 'Na platformě už běží nejvyšší počet ověřených obnov najednou; další se spustí později.', 409);
        }
    }

    public function inFlight(Service $service): bool
    {
        return VerifiedRestoreRun::query()->where('service_id', $service->id)->where('state', VerifiedRestoreRun::RUNNING)->exists()
            || Operation::query()->where('kind', VerifiedRestoreWorkflow::kind())->where('desired->service_id', $service->id)
                ->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists();
    }

    // ── one run ────────────────────────────────────────────────────────────────────────────────────────

    /** The protocol row of an operation, made on its first step. */
    public function begin(Operation $operation, Service $service): VerifiedRestoreRun
    {
        $existing = VerifiedRestoreRun::query()->where('operation_id', $operation->id)->first();
        if ($existing !== null) {
            return $existing;
        }
        $backup = Backup::query()->where('service_id', $service->id)->find((string) data_get($operation->desired, 'backup_id'));
        $run = VerifiedRestoreRun::query()->create([
            'organization_id' => $service->organization_id, 'service_id' => $service->id, 'operation_id' => $operation->id, 'backup_id' => $backup?->id,
            'trigger' => $operation->actor_type === 'system' ? 'schedule' : 'staff', 'state' => VerifiedRestoreRun::RUNNING,
            'backup_taken_at' => $backup !== null ? self::takenAt($backup) : null, 'started_at' => now(), 'steps' => [], 'suppression' => ['applied' => [], 'not_applied' => []],
            'target' => [], 'skipped_sets' => array_values((array) data_get($operation->desired, 'skipped_sets', [])),
        ]);
        $usable = $backup !== null && $backup->state === 'completed' && FinalArchive::isSet($backup) && data_get($backup->meta, 'incomplete') === null;
        $run->recordStep('backup_selected', $usable ? VerifiedRestoreRun::PASS : VerifiedRestoreRun::FAIL, $usable
            ? 'sada '.$backup->id.' dokončena '.self::takenAt($backup)?->format('Y-m-d H:i:s').' UTC'
            : 'žádná úplná sada záloh k obnově');

        return $run;
    }

    public function backupOf(VerifiedRestoreRun $run): ?Backup
    {
        return $run->stepStatus('backup_selected') === VerifiedRestoreRun::PASS ? Backup::query()->find((string) $run->backup_id) : null;
    }

    /** The test identity the customer configured for the login check, or null (the check is then "not verified"). @return array{user:string, password:string}|null */
    public function identity(Service $service): ?array
    {
        $identity = (array) data_get($service->tags, 'verified_restore.identity', []);
        $user = trim((string) ($identity['user'] ?? ''));
        $sealed = (string) ($identity['password'] ?? '');
        if ($user === '' || $sealed === '') {
            return null;
        }
        try {
            return ['user' => $user, 'password' => Crypt::decryptString($sealed)];
        } catch (Throwable) {
            return null;
        }
    }

    /** Close the protocol: the verdict, RPO and RTO, the record the posture and the report read, and who must hear about it. */
    public function finish(VerifiedRestoreRun $run): VerifiedRestoreRun
    {
        if ($run->state === VerifiedRestoreRun::FINISHED) {
            return $run;
        }
        foreach (self::STEPS as $key) { // a step the run never reached is not verified, said as such
            if ($run->step($key) === null) {
                $run->recordStep($key, VerifiedRestoreRun::NOT_VERIFIED, 'krok neproběhl');
            }
        }
        $statuses = array_map(fn (string $key) => $run->stepStatus($key), self::VERDICT_STEPS);
        $outcome = match (true) {
            in_array(VerifiedRestoreRun::FAIL, $statuses, true) => 'failed',
            $run->stepStatus('http_200') === VerifiedRestoreRun::PASS => 'passed',
            default => 'not_verified',
        };
        $checksDone = $run->checks_done_at ?? now();
        $run->forceFill([
            'outcome' => $outcome, 'state' => VerifiedRestoreRun::FINISHED, 'finished_at' => now(), 'checks_done_at' => $checksDone,
            'rpo_seconds' => $run->backup_taken_at !== null && $run->started_at !== null ? max(0, (int) $run->backup_taken_at->diffInSeconds($run->started_at, true)) : null,
            'rto_seconds' => $run->started_at !== null ? max(0, (int) $run->started_at->diffInSeconds($checksDone, true)) : null,
        ])->save();
        $service = Service::query()->find($run->service_id);
        if ($service !== null) {
            $this->record($service, $run);
        }

        return $run;
    }

    private function record(Service $service, VerifiedRestoreRun $run): void
    {
        $tags = (array) $service->tags;
        $tags[RestoreTest::TAG] = [
            'last_at' => ($run->finished_at ?? now())->toIso8601String(), 'backup_id' => $run->backup_id, 'outcome' => $run->outcome === 'failed' ? 'failed' : 'ok',
            'kind' => 'verified', 'run_id' => $run->id, 'verdict' => $run->outcome, 'rpo_seconds' => $run->rpo_seconds, 'rto_seconds' => $run->rto_seconds,
            'http' => $run->stepStatus('http_200'), 'databases' => [], 'problems' => array_slice($this->problems($run), 0, 5),
        ];
        $service->forceFill(['tags' => $tags])->save();
        $label = $service->label ?: ($service->hostname ?: $service->name);
        $context = CommandContext::system('verified restore');
        $this->audit->record($context->withScope($service->organization_id), 'service.restore_verify.finished', $run->outcome === 'failed' ? 'failed' : 'succeeded',
            ['run_id' => $run->id, 'outcome' => $run->outcome, 'rpo_seconds' => $run->rpo_seconds, 'rto_seconds' => $run->rto_seconds, 'cleanup' => $run->stepStatus('cleanup')], 'service', $service->id);
        if ($run->outcome === 'failed') {
            $this->outbox->publish(GenericEvent::of('service.verified_restore.failed', 'service', $service->id, [
                'run_id' => $run->id, 'label' => $label, 'problem' => mb_substr((string) ($this->problems($run)[0] ?? ''), 0, 160),
            ], $service->organization_id));
        }
        if ($run->stepStatus('cleanup') === VerifiedRestoreRun::FAIL) {
            $this->outbox->publish(GenericEvent::of('service.verified_restore.cleanup_failed', 'service', $service->id, [
                'run_id' => $run->id, 'label' => $label, 'target_service_id' => (string) data_get($run->target, 'service_id', ''),
                'domain' => (string) data_get($run->target, 'domain', ''), 'problem' => mb_substr((string) ($run->step('cleanup')['detail'] ?? ''), 0, 160),
            ], $service->organization_id));
        }
    }

    /** @return list<string> what failed, step by step */
    public function problems(VerifiedRestoreRun $run): array
    {
        $out = [];
        foreach (self::STEPS as $key) {
            $step = $run->step($key);
            if (($step['status'] ?? null) === VerifiedRestoreRun::FAIL) {
                $out[] = $key.': '.(string) ($step['detail'] ?? '');
            }
        }

        return $out;
    }

    public static function takenAt(Backup $backup): ?Carbon
    {
        return $backup->finished_at ?? $backup->started_at;
    }
}
