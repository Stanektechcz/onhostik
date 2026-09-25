<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Carbon\CarbonImmutable;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Mail\MailboxBackupPolicy;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * The doctor's rows about backups the platform takes for its customers (TASK-0024): whether servers and managed databases
 * sold backups get them (`backups.compute`), whether their Proxmox instance has somewhere to put them, server backups whose
 * volume could not be removed or that left a second volume behind, whether the backup tick itself runs inside its fifteen
 * minutes, whether plans are backed up as often and as long as sold (`backups.as_sold`), and whether mail plans' mailboxes
 * keep the backups sold (`mail.backup_retention`). Read-only: nothing is written, no provider is asked. `onhost:doctor` adds the rows; the logic lives here to keep the doctor small.
 */
final class BackupOperationsCheck
{
    /** Older than this and the last backup tick counts as missing (two ticks of fifteen minutes). */
    public const TICK_STALE_MINUTES = 30;

    /** Frequencies on the price list that lose history or slots while `backups.as_sold` is off. */
    private const NOT_AS_SOLD = ['1h', '60m', '15m', 'hourly', '6h'];

    public function __construct(private readonly BackupScheduler $scheduler, private readonly AutomationLedger $ledger) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool}> */
    public function rows(): array
    {
        $ready = $this->scheduler->computeReadiness();
        $rows = [$this->soldRow($ready), $this->storageRow($ready), $this->deleteBlockedRow(), $this->orphanRow(), $this->tickRow(), $this->asSoldRow()];
        if ($ready['rule_on']) {
            $rows[] = $this->coverageRow($ready['services']);
        }
        $rows[] = MailboxBackupPolicy::doctorRow(); // mailboxes of mail plans keep the backups sold (mail.backup_retention)

        return $rows;
    }

    /** @param  array{rule_on:bool, sold:int, services:list<string>, instances_missing_storage:list<string>, services_missing_storage:int}  $ready */
    private function soldRow(array $ready): array
    {
        $detail = match (true) {
            $ready['rule_on'] => 'rule '.BackupScheduler::COMPUTE_RULE.' on · '.$ready['sold'].' service(s) sold backups',
            $ready['sold'] === 0 => 'no server or database is sold backups',
            default => 'rule '.BackupScheduler::COMPUTE_RULE.' off: '.$ready['sold'].' service(s) sold backups get none — onhost:backups:compute-plan, then Automations (docs/runbooks/backups.md)',
        };

        return self::row('backups: servers and databases sold backups get them', $ready['rule_on'] || $ready['sold'] === 0, $detail, false);
    }

    /**
     * Blocking once the rule is on: every tick then misses a paid backup on those servers. Before that it is a warning, since
     * nothing is being taken yet.
     *
     * @param  array{rule_on:bool, sold:int, services:list<string>, instances_missing_storage:list<string>, services_missing_storage:int}  $ready
     */
    private function storageRow(array $ready): array
    {
        $missing = $ready['instances_missing_storage'];

        return self::row('backups: every Proxmox instance carrying sold backups has a backup_storage', $missing === [],
            $missing === [] ? 'every instance names one' : implode(', ', $missing).' ('.$ready['services_missing_storage'].' service(s)) — set the instance option backup_storage (Nastavení systému → Integrace providerů), then onhost:backups:compute-plan shows 0 MISSING',
            $ready['rule_on']);
    }

    private function deleteBlockedRow(): array
    {
        $query = Backup::query()->where('kind', 'scheduled')->where('state', 'completed')->whereNotNull('meta->delete_blocked');
        $count = (clone $query)->count();
        $first = $query->orderBy('id')->limit(5)->get(['id', 'meta'])->map(fn (Backup $b) => $b->id.' ('.mb_substr((string) data_get($b->meta, 'delete_blocked.why', '?'), 0, 60).')')->implode(', ');

        return self::row('backups: expired server backups are gone from the backup storage', $count === 0,
            $count === 0 ? '' : $count.' backup(s) past retention still on the storage: '.$first.' — backups.meta.delete_blocked says why; the scheduler tries again every tick', false);
    }

    /** A second volume a retried backup left behind: reported, never deleted automatically (it is removed by hand after a look). */
    private function orphanRow(): array
    {
        $query = Backup::query()->whereNotNull('meta->orphan_volumes')->where('state', '!=', 'deleted');
        $count = (clone $query)->count();
        $first = $query->orderBy('id')->limit(5)->pluck('id')->implode(', ');

        return self::row('backups: no orphaned backup volume', $count === 0,
            $count === 0 ? '' : $count.' backup(s) left a second volume with their marker: '.$first.' — backups.meta.orphan_volumes lists them; check at the hypervisor and remove by hand (docs/runbooks/backups.md)', false);
    }

    private function tickRow(): array
    {
        $check = 'backups: the backup tick ran within '.self::TICK_STALE_MINUTES.' min and inside its budget';
        if (! $this->ledger->enabled('backups.run')) {
            return self::row($check, false, 'rule backups.run switched off by staff — no scheduled backup is taken', false);
        }
        $last = $this->ledger->last('backups.run');
        $at = isset($last['at']) ? CarbonImmutable::parse((string) $last['at']) : null;
        if ($at === null) {
            return self::row($check, false, 'no run recorded — is `php artisan schedule:run` running onhost:backups:run every 15 minutes?', false);
        }
        $seconds = (int) ($last['stats']['seconds'] ?? 0);
        $errors = (int) ($last['stats']['errors'] ?? 0);
        $problems = array_values(array_filter([
            $at->lt(now()->subMinutes(self::TICK_STALE_MINUTES)) ? 'last run '.$at->diffForHumans() : null,
            $seconds > BackupScheduler::TICK_BUDGET_SECONDS ? "last run took {$seconds} s of a budget of ".BackupScheduler::TICK_BUDGET_SECONDS.' s — the next tick is skipped while it runs (withoutOverlapping), so 15m plans lose slots in silence' : null,
            $errors > 0 ? "{$errors} error(s) in the last run — see the log" : null,
        ]));

        return self::row($check, $problems === [], $problems === [] ? 'last run '.$at->diffForHumans().' in '.$seconds.' s' : implode(' · ', $problems), false);
    }

    private function asSoldRow(): array
    {
        $check = 'backups: every plan is backed up as often and as long as sold';
        if ($this->ledger->enabled(BackupScheduler::AS_SOLD_RULE)) {
            return self::row($check, true, 'rule '.BackupScheduler::AS_SOLD_RULE.' on', false);
        }
        $count = Service::query()->whereIn('family', BackupScheduler::DAILY_KEEPER_FAMILIES)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereIn('entitlements->backup_frequency', self::NOT_AS_SOLD)->count();

        return self::row($check, $count === 0, $count === 0 ? 'no service sells a sub-daily frequency'
            : $count.' service(s) sell a sub-daily frequency: with rule '.BackupScheduler::AS_SOLD_RULE.' off, `1h` is backed up once a day and history stops at the generation count — onhost:backups:frequency-plan, then the owner decides', false);
    }

    /** @param  list<string>  $sold */
    private function coverageRow(array $sold): array
    {
        $days = max(1, (int) config('onhost.backups.coverage_days', 3));
        $old = $sold === [] ? collect() : Service::query()->whereIn('id', $sold)->where('created_at', '<', now()->subDays($days))->pluck('id');
        $covered = $old->isEmpty() ? collect() : Backup::query()->whereIn('service_id', $old->all())->where('state', 'completed')->where('finished_at', '>=', now()->subDays($days))->distinct()->pluck('service_id');
        $bare = $old->diff($covered)->values();

        return self::row("every server sold backups has one from the last {$days} days", $bare->isEmpty(),
            $bare->isEmpty() ? $old->count().' service(s) covered' : $bare->count().' without one: '.$bare->take(5)->implode(', ').($bare->count() > 5 ? ' …' : '').' — docs/runbooks/backups.md', false);
    }

    /** @return array{area:string, check:string, ok:bool, detail:string, blocking:bool} */
    private static function row(string $check, bool $ok, string $detail, bool $blocking): array
    {
        return ['area' => 'lifecycle', 'check' => $check, 'ok' => $ok, 'detail' => $detail, 'blocking' => $blocking];
    }
}
