<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\LegalHold;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\RetainedBackups;
use Throwable;

/**
 * Scheduled backups for web and mail services — and, under the owner's switch `backups.compute`, for servers and managed
 * databases (TASK-0019): the plan's entitlements say how often (15m, hourly, 6h, daily),
 * how many days and how many generations to keep and whether a copy goes off-site. Backups run as ordinary
 * `backup` operations (one per slot, idempotent); expired ones are deleted on the node, generation caps trim the
 * oldest, and the off-site copy is streamed through the control plane to the configured disk.
 */
final class BackupScheduler
{
    public const FREQUENCIES = ['15m' => 15, 'hourly' => 60, '6h' => 360, 'daily' => 1440, 'weekly' => 10080];

    /** The automation rule (off unless staff switch it on) under which servers and managed databases are backed up. */
    public const COMPUTE_RULE = 'backups.compute';

    /** Families whose backups are the hypervisor's own (vzdump to the instance's `backup_storage`). */
    public const COMPUTE_FAMILIES = ['cloud', 'data'];

    /** Families the scheduler has always looked at. */
    public const WEB_FAMILIES = ['web', 'managed', 'mail'];

    /** How many services a tick used to look at before TASK-0019 (the first hundred by id, never the rest). */
    public const OLD_WINDOW = 100;

    /** Every family the scheduler may keep a schedule for (the doctor counts stalled and paused schedules over all of them). */
    public const SCHEDULED_FAMILIES = ['web', 'managed', 'mail', 'cloud', 'data'];

    /**
     * How long one tick may take (80 % of its fifteen-minute cadence). `onhost:backups:run` runs withoutOverlapping, so a
     * tick that overruns makes the next one skip in silence and a 15m plan loses slots nobody counts; the doctor compares
     * the seconds the last tick recorded with this.
     */
    public const TICK_BUDGET_SECONDS = 720;

    /**
     * The automation rule (off unless staff switch it on) under which a plan is backed up as often and as long as sold
     * (owner decision 18, TASK-0024): `1h` read as hourly, and on web/managed one backup a day kept for `backup_days`
     * beyond the sub-daily generations. Who it would change: `onhost:backups:frequency-plan`.
     */
    public const AS_SOLD_RULE = 'backups.as_sold';

    /** Spellings of a frequency on the price list that the scheduler did not know (managed-woo and shop-growth sell `1h`). */
    public const FREQUENCY_ALIASES = ['1h' => 'hourly', '60m' => 'hourly', '24h' => 'daily', '1d' => 'daily'];

    /** Families that keep one backup a day for `backup_days` beyond their generations under `backups.as_sold`. */
    public const DAILY_KEEPER_FAMILIES = ['web', 'managed'];

    public function __construct(private readonly ServiceService $services, private readonly ServiceFeatures $features, private readonly OutboxPublisher $outbox, private readonly FinalArchive $archives, private readonly AutomationLedger $ledger) {}

    /**
     * The frequency the scheduler acts on: the price list's spelling (`1h`) is read as the frequency it means only under
     * `backups.as_sold`; otherwise the string stays what it was, and an unknown one keeps its old daily-at-midnight path.
     */
    public static function normalizeFrequency(string $frequency, bool $asSold): string
    {
        return $asSold ? (self::FREQUENCY_ALIASES[$frequency] ?? $frequency) : $frequency;
    }

    /**
     * One pass over EVERY eligible service. `$limit` is the size of one chunk read from the database, not a cap: the tick
     * used to take the first hundred services by id and never look at the rest, so from the 101st service on nobody
     * got a scheduled backup at all. A slot stays idempotent (`backup:auto:{id}:{slot}`), so a long pass is harmless.
     *
     * @return array{started:int, skipped:int, deleted:int, offsite:int, errors:int, missed:int, paused:int, seconds:int}
     */
    public function tick(int $limit = 100): array
    {
        $began = microtime(true);
        $stats = ['started' => 0, 'skipped' => 0, 'deleted' => 0, 'offsite' => 0, 'errors' => 0, 'missed' => 0, 'paused' => 0];
        $context = CommandContext::system('backup scheduler');
        $services = $this->eligible(self::WEB_FAMILIES, false, $limit);
        if ($this->ledger->enabled(self::COMPUTE_RULE)) {
            // only what the platform provisioned (an instance AND a binding): a server it merely found is not its to back up
            $services = (function () use ($services, $limit) {
                yield from $services;
                yield from $this->eligible(self::COMPUTE_FAMILIES, true, $limit);
            })();
        }
        foreach ($services as $service) {
            $schedule = null; // never the previous service's schedule, whatever throws below
            try {
                if (self::pausedAt($service) !== null) {
                    $stats['paused']++;

                    continue; // a schedule that paused itself waits for a person, however many ticks pass
                }
                $schedule = $this->scheduleFor($service);
                if ($schedule === null) {
                    continue;
                }
                if ($this->noteOutcome($service, $schedule)) {
                    $stats['paused']++;

                    continue;
                }
                if ($this->due($service, $schedule)) {
                    $this->services->requestAction($service, 'backup', $context, 'backup:auto:'.$service->id.':'.$schedule['slot'], ['kind' => 'scheduled', 'retention_days' => $schedule['days'], 'policy' => ['notes' => 'scheduled '.$schedule['frequency']]]);
                    $stats['started']++;
                    $this->record($service, $schedule, null);
                } else {
                    $stats['skipped']++;
                }
                $stats['deleted'] += $this->prune($service, $schedule, $context);
                $stats['offsite'] += $this->offsite($service, $schedule);
            } catch (DomainError $e) {
                // a slot that could not run is a backup the customer paid for and did not get: it is written down, and
                // when it keeps happening somebody is told (H434, H435, H446). An hourly plan on a site whose backup
                // takes longer than an hour misses every slot, and used to do it in silence.
                $stats['skipped']++;
                $stats['missed'] += $schedule === null ? 0 : (int) $this->record($service, $schedule, $e->error);
            } catch (Throwable $e) {
                $stats['errors']++;
                report($e);
            }
        }
        $stats['seconds'] = (int) round(microtime(true) - $began); // the doctor holds it against TICK_BUDGET_SECONDS

        return $stats;
    }

    /** How many slots in a row may be missed before somebody is told. */
    public const MISSES_BEFORE_ALARM = 3;

    /** The tag the schedule's own record lives under. */
    public const TAG = 'backup_schedule';

    /**
     * Writes down what happened to this slot. A slot that ran clears the count; a slot that could not run raises it,
     * and the third one in a row is reported once — to the operator, because a backup nobody takes is theirs to fix,
     * and to the customer, because it is their data.
     *
     * @param  array{frequency:string, minutes:int, days:int, generations:int, offsite:bool, slot:string, window_start:Carbon}  $schedule
     * @return bool whether this call counted a missed slot
     */
    private function record(Service $service, array $schedule, ?string $error): bool
    {
        $tags = (array) $service->tags;
        $state = (array) ($tags[self::TAG] ?? []);
        if ($error === null) {
            if (($state['missed'] ?? 0) === 0 && ($state['last_slot'] ?? null) === $schedule['slot']) {
                return false;
            }
            // merged, not replaced: what the slot did is one thing, what became of the backups is another (H447), and
            // replacing the record here used to wipe the failure count every time a new slot was started
            $tags[self::TAG] = array_merge(array_diff_key($state, array_flip(['last_missed_slot', 'last_missed_at', 'last_error'])),
                ['last_slot' => $schedule['slot'], 'last_run_at' => now()->toIso8601String(), 'frequency' => $schedule['frequency'], 'missed' => 0]);
            $service->forceFill(['tags' => $tags])->save();

            return false;
        }
        if (($state['last_missed_slot'] ?? null) === $schedule['slot']) {
            return false; // the same slot, looked at again within its window: one miss is one miss
        }
        $missed = (int) ($state['missed'] ?? 0) + 1;
        $tags[self::TAG] = array_merge($state, ['missed' => $missed, 'last_missed_slot' => $schedule['slot'], 'last_missed_at' => now()->toIso8601String(), 'last_error' => mb_substr($error, 0, 60), 'frequency' => $schedule['frequency']]);
        $service->forceFill(['tags' => $tags])->save();
        if ($missed === self::MISSES_BEFORE_ALARM || ($missed > self::MISSES_BEFORE_ALARM && $missed % (self::MISSES_BEFORE_ALARM * 8) === 0)) {
            $this->outbox->publish(GenericEvent::of('service.backup.schedule.stalled', 'service', $service->id, [
                'missed' => $missed, 'frequency' => $schedule['frequency'], 'reason' => mb_substr($error, 0, 60),
                'label' => $service->label ?: ($service->hostname ?: $service->name),
            ], $service->organization_id));
        }

        return true;
    }

    /** What the schedule of a service has been doing lately (the panel and the doctor read it). @return array<string,mixed> */
    public static function health(Service $service): array
    {
        return (array) data_get($service->tags, self::TAG, []);
    }

    /** When the schedule stopped itself, or null while it is running. */
    public static function pausedAt(Service $service): ?string
    {
        $at = data_get($service->tags, self::TAG.'.paused_at');

        return is_string($at) && $at !== '' ? $at : null;
    }

    /**
     * How many scheduled backups may fail in a row before the schedule stops itself.
     *
     * The operation runner already retries a failed backup, so five failures in a row are five slots that each tried
     * and could not: the cause is the node, the disk or the site, not luck.
     */
    public const FAILURES_BEFORE_PAUSE = 5;

    /**
     * What became of the backup this schedule asked for last time — and, when the answer keeps being "it failed",
     * stopping the schedule (H447).
     *
     * A backup that fails costs the node the whole packing run every time, so a schedule that cannot succeed is a
     * schedule that must not go on trying for ever. It stops itself, says so once, and waits for a person to look
     * and set the schedule again; nothing is deleted and every backup already made stays where it is.
     *
     * @param  array{frequency:string, slot:string, ...}  $schedule
     * @return bool whether the schedule was paused by this call
     */
    private function noteOutcome(Service $service, array $schedule): bool
    {
        $last = Backup::query()->where('service_id', $service->id)->where('kind', 'scheduled')->whereIn('state', ['completed', 'failed'])
            ->orderByDesc('started_at')->orderByDesc('id')->first();
        $tags = (array) $service->tags;
        $state = (array) ($tags[self::TAG] ?? []);
        if ($last === null || (string) ($state['last_outcome'] ?? '') === (string) $last->id) {
            return false; // nothing has finished yet, or this one is already counted
        }
        $failures = $last->state === 'failed' ? (int) ($state['failures'] ?? 0) + 1 : 0;
        $state['failures'] = $failures;
        $state['last_outcome'] = (string) $last->id;
        $reason = mb_substr((string) (data_get($last->meta, 'error') ?? 'záloha selhala'), 0, 60);
        if ($last->state === 'failed') {
            $state['last_failure'] = $reason;
        }
        $paused = $failures >= self::FAILURES_BEFORE_PAUSE;
        if ($paused) {
            $state['paused_at'] = now()->toIso8601String();
        }
        $tags[self::TAG] = $state;
        $service->forceFill(['tags' => $tags])->save();
        if ($paused) {
            $this->outbox->publish(GenericEvent::of('service.backup.schedule.paused', 'service', $service->id, [
                'failures' => $failures, 'frequency' => $schedule['frequency'], 'reason' => $reason,
                'label' => $service->label ?: ($service->hostname ?: $service->name),
            ], $service->organization_id));
        }

        return $paused;
    }

    /**
     * Start the schedule again after somebody has looked at it — the controlled resume H447 asks for. Called when the
     * backup schedule is set, which is a person deciding the schedule is right; the failure count starts from nothing.
     */
    public static function resume(Service $service): bool
    {
        $tags = (array) $service->tags;
        $state = (array) ($tags[self::TAG] ?? []);
        if (($state['paused_at'] ?? null) === null && (int) ($state['failures'] ?? 0) === 0) {
            return false;
        }
        unset($state['paused_at'], $state['last_failure']);
        $state['failures'] = 0;
        $tags[self::TAG] = $state;
        $service->forceFill(['tags' => $tags])->save();

        return true;
    }

    /**
     * @param  bool|null  $asSold  null: as the rule `backups.as_sold` stands now; true/false: as it would be (the frequency plan)
     * @return array{frequency:string, minutes:int, days:int, generations:int, offsite:bool, slot:string, window_start:Carbon}|null
     */
    public function scheduleFor(Service $service, ?bool $asSold = null): ?array
    {
        $features = $this->features->features($service);
        $schedule = $features['backup_schedule'] ?? null;
        if (empty($features['backups']['enabled']) || $schedule === null || empty($schedule['enabled'])) {
            return null;
        }

        return $this->scheduleFrom($service, (array) ($schedule['options'] ?? []), $asSold);
    }

    /**
     * The schedule from what the plan sells (`$options`) and what the customer set within it (the policy).
     *
     * @param  array<string,mixed>  $options
     * @return array{frequency:string, minutes:int, days:int, generations:int, offsite:bool, slot:string, window_start:Carbon}
     */
    private function scheduleFrom(Service $service, array $options, ?bool $asSold = null): array
    {
        $asSold ??= $this->ledger->enabled(self::AS_SOLD_RULE);
        if (isset($options['frequency'])) {
            $options['frequency'] = self::normalizeFrequency((string) $options['frequency'], $asSold);
        }
        $policy = BackupPolicy::query()->where('service_id', $service->id)->first();
        $frequency = self::normalizeFrequency((string) ($policy?->schedule['frequency'] ?? $options['frequency'] ?? 'daily'), $asSold);
        $minutes = self::FREQUENCIES[$frequency] ?? self::FREQUENCIES['daily'];
        $days = max(1, (int) ($policy?->retention['days'] ?? $options['days'] ?? 7));
        $generations = max(1, (int) ($policy?->retention['generations'] ?? $options['generations'] ?? 7));
        if (in_array($service->family, self::COMPUTE_FAMILIES, true)) {
            // on a server the options ARE the ceiling sold (plan or active add-ons), so a stored policy never goes above them: a
            // downgrade or a cancelled add-on does not keep an old hourly/long policy in force. (On the web a backup add-on's
            // policy is legitimately above the plan's own options, which do not know it — not capped there.)
            $ceiling = self::FREQUENCIES[(string) ($options['frequency'] ?? 'daily')] ?? self::FREQUENCIES['daily'];
            if ($minutes < $ceiling) {
                [$frequency, $minutes] = [(string) ($options['frequency'] ?? 'daily'), $ceiling];
            }
            $days = min($days, max(1, (int) ($options['days'] ?? $days)));
            $generations = min($generations, max(1, (int) ($options['generations'] ?? $generations)));
        }
        $offsite = (bool) ($policy?->offsite ?? data_get($service->entitlements, 'backup_offsite', false));
        $now = now();
        $windowStart = match ($frequency) {
            'daily' => $now->copy()->startOfDay()->setTime((int) config('onhost.backups.daily_hour', 2), 30),
            'weekly' => $now->copy()->startOfWeek()->setTime((int) config('onhost.backups.daily_hour', 2), 30),
            default => $now->copy()->startOfDay()->addMinutes(intdiv($now->hour * 60 + $now->minute, $minutes) * $minutes),
        };
        if ($windowStart->greaterThan($now)) {
            $windowStart = $frequency === 'weekly' ? $windowStart->subWeek() : $windowStart->subDay();
        }

        return ['frequency' => $frequency, 'minutes' => $minutes, 'days' => $days, 'generations' => $generations, 'offsite' => $offsite, 'slot' => $windowStart->format('YmdHi'), 'window_start' => $windowStart, 'as_sold' => $asSold];
    }

    /**
     * Every live service of the families with a provider instance, read in chunks of `$chunk` by id (keyset, so a row
     * written meanwhile neither repeats nor hides one). `$owned` also asks for a binding — for servers and managed
     * databases: one without a binding is not the platform's, and the schedule would only write misses onto it.
     *
     * @param  list<string>  $families
     * @return \Generator<int, Service>
     */
    private function eligible(array $families, bool $owned, int $chunk): \Generator
    {
        $chunk = max(1, $chunk);
        $after = null;
        do {
            $batch = Service::query()->whereIn('family', $families)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->whereNotNull('provider_instance_id')
                ->when($owned, fn ($q) => $q->whereHas('bindings'))
                ->when($after !== null, fn ($q) => $q->where('id', '>', $after))
                ->orderBy('id')->limit($chunk)->get();
            foreach ($batch as $service) {
                yield $service;
            }
            $after = $batch->last()?->id;
        } while ($batch->count() === $chunk);
    }

    /**
     * Who `backups.compute` would start backing up, read-only (`onhost:backups:compute-plan`): the owner decides on
     * existing services with this list in hand. Nothing is written, nothing is asked of a provider.
     *
     * @return list<array{service:string, family:string, plan:string, frequency:string, days:int, generations:int, backup_storage:string}>
     */
    public function computePlan(int $limit = 500): array
    {
        $rows = [];
        foreach ($this->eligible(self::COMPUTE_FAMILIES, true, $limit) as $service) { // all of them; `$limit` is the chunk size
            $sold = $this->features->computeBackupSchedule($service);
            if ($sold === null) {
                continue; // sold no backups: the rule leaves it alone
            }
            $schedule = $this->scheduleFrom($service, $sold);
            $planId = $service->plan_version_id !== null ? PlanVersion::query()->whereKey($service->plan_version_id)->value('plan_id') : null;
            $planKey = $planId !== null ? (string) Plan::query()->whereKey($planId)->value('key') : '';
            $storage = (string) data_get(ProviderInstance::query()->find($service->provider_instance_id)?->options, 'backup_storage', '');
            $rows[] = [
                'service' => (string) $service->id, 'family' => (string) $service->family, 'plan' => $service->product_key.'/'.($planKey !== '' ? $planKey : '-'),
                'frequency' => $schedule['frequency'], 'days' => $schedule['days'], 'generations' => $schedule['generations'], 'backup_storage' => $storage === '' ? 'MISSING' : $storage,
            ];
        }

        return $rows;
    }

    /**
     * What visiting every service (instead of the first hundred) changes for web, managed and mail — read-only, for the owner's
     * decision: how many are eligible, how many lie beyond the old window and so are newly visited (new backups, pruning of
     * expired backups, off-site copies), and how many of those have a backup schedule at all.
     *
     * @return array{eligible:int, beyond_old_window:int, beyond_with_schedule:int}
     */
    public function webWindow(int $chunk = 500): array
    {
        $out = ['eligible' => 0, 'beyond_old_window' => 0, 'beyond_with_schedule' => 0];
        foreach ($this->eligible(self::WEB_FAMILIES, false, $chunk) as $service) {
            $out['eligible']++;
            if ($out['eligible'] <= self::OLD_WINDOW) {
                continue;
            }
            $out['beyond_old_window']++;
            try {
                $out['beyond_with_schedule'] += self::pausedAt($service) === null && $this->scheduleFor($service) !== null ? 1 : 0;
            } catch (Throwable) {
                // a service whose features cannot be read right now is counted as newly visited, not as scheduled
            }
        }

        return $out;
    }

    /**
     * Whether servers and managed databases sold backups can get them, read-only (the doctor): the rule's state, how many
     * are sold backups, and which Proxmox instances carrying them have no `backup_storage` to put the backups on.
     *
     * @return array{rule_on:bool, sold:int, services:list<string>, instances_missing_storage:list<string>, services_missing_storage:int}
     */
    public function computeReadiness(int $chunk = 500): array
    {
        $sold = 0;
        $ids = [];
        $instances = [];
        foreach ($this->eligible(self::COMPUTE_FAMILIES, true, $chunk) as $service) {
            if ($this->features->computeBackupSchedule($service) === null) {
                continue;
            }
            $sold++;
            $ids[] = (string) $service->id;
            $instances[(string) $service->provider_instance_id] = ($instances[(string) $service->provider_instance_id] ?? 0) + 1;
        }
        $missing = [];
        $services = 0;
        foreach (ProviderInstance::query()->whereIn('id', array_keys($instances))->get() as $instance) {
            if ((string) data_get($instance->options, 'backup_storage', '') === '') {
                $missing[] = (string) $instance->key;
                $services += $instances[(string) $instance->id];
            }
        }
        sort($missing);

        return ['rule_on' => $this->ledger->enabled(self::COMPUTE_RULE), 'sold' => $sold, 'services' => $ids, 'instances_missing_storage' => $missing, 'services_missing_storage' => $services];
    }

    /**
     * What `backups.as_sold` would change for web and managed services, read-only (`onhost:backups:frequency-plan`): the
     * frequency sold, the frequency and history the scheduler gives today and would give under the rule, and a storage
     * estimate (the last completed backup's size times the copies the rule keeps on top). Nothing is written.
     *
     * @return list<array{service:string, plan:string, sold:string, now:string, as_sold:string, history_now:int, history_as_sold:int, extra_copies:int, extra_bytes:?int, changes:bool}>
     */
    public function frequencyPlan(int $chunk = 500): array
    {
        $rows = [];
        foreach ($this->eligible(self::DAILY_KEEPER_FAMILIES, false, $chunk) as $service) {
            try {
                $now = $this->scheduleFor($service, false);
                $sold = $now === null ? null : $this->scheduleFor($service, true);
            } catch (Throwable) {
                continue; // features that cannot be read right now: the service is left out, not guessed
            }
            if ($now === null || $sold === null) {
                continue;
            }
            $historyNow = BackupDailyKeepers::historyMinutes($now['minutes'], $now['days'], $now['generations'], false);
            $historyAsSold = BackupDailyKeepers::historyMinutes($sold['minutes'], $sold['days'], $sold['generations'], true);
            $extra = max(0, BackupDailyKeepers::copies($sold['minutes'], $sold['days'], $sold['generations'], true) - BackupDailyKeepers::copies($now['minutes'], $now['days'], $now['generations'], false));
            $size = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('kind', 'scheduled')->whereNotNull('size_bytes')->orderByDesc('started_at')->value('size_bytes');
            $rows[] = [
                'service' => (string) $service->id, 'plan' => (string) $service->product_key, 'sold' => (string) data_get($service->entitlements, 'backup_frequency', 'daily'),
                'now' => $now['frequency'].' ('.$now['minutes'].' min)', 'as_sold' => $sold['frequency'].' ('.$sold['minutes'].' min)',
                'history_now' => $historyNow, 'history_as_sold' => $historyAsSold, 'extra_copies' => $extra, 'extra_bytes' => $size === null ? null : (int) $size * $extra,
                'changes' => $now['minutes'] !== $sold['minutes'] || $historyNow !== $historyAsSold,
            ];
        }

        return $rows;
    }

    private function due(Service $service, array $schedule): bool
    {
        // a failed attempt counts as this slot having been tried: the operation runner has already retried it, and
        // without this the scheduler started the whole packing run again at EVERY tick until the window passed
        $last = Backup::query()->where('service_id', $service->id)->where('kind', 'scheduled')->whereIn('state', ['running', 'completed', 'failed'])->orderByDesc('started_at')->orderByDesc('id')->first();

        return $last === null || $last->started_at === null || $last->started_at->lessThan($schedule['window_start']);
    }

    /** Delete expired backups and trim generations beyond the plan; protected ones and final archives are never touched. */
    private function prune(Service $service, array $schedule, CommandContext $context): int
    {
        if (LegalHold::coversService($service)) {
            return 0; // a legal hold suspends deletion (H18): new backups are still made, old ones stay until the hold is lifted
        }
        $deleted = 0;
        $compute = in_array($service->family, self::COMPUTE_FAMILIES, true);
        // the final archive is FinalArchive's alone (its own retention, its own expiry at the provider), protected or not
        $expired = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('protected', false)->where('kind', '!=', 'final')->where(function ($q) {
            $q->whereNotNull('retention_until')->where('retention_until', '<', now());
        })
            // on a server only what this schedule made: a manual backup or a safety copy at the hypervisor was never its to take
            ->when($compute, fn ($q) => $q->where('kind', 'scheduled'))->get();
        $surplus = ! empty($schedule['as_sold']) && in_array($service->family, self::DAILY_KEEPER_FAMILIES, true)
            ? BackupDailyKeepers::surplus($service, $schedule['generations'], $schedule['days'], 50)
            : Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('protected', false)->where('kind', 'scheduled')->orderByDesc('started_at')->skip($schedule['generations'])->take(50)->get();
        foreach ($expired->merge($surplus)->unique('id') as $backup) {
            if ($this->deleteOnNode($service, $backup)) {
                $backup->forceFill(['state' => 'deleted', 'meta' => array_merge((array) $backup->meta, ['deleted_at' => now()->toIso8601String(), 'deleted_by' => 'retention'])])->save();
                $this->outbox->publish(GenericEvent::of('backup.deleted', 'service', $service->id, ['backup_id' => $backup->id, 'reason' => 'retention'], $service->organization_id));
                $deleted++;
            }
        }

        return $deleted;
    }

    private function deleteOnNode(Service $service, Backup $backup): bool
    {
        if ($backup->remote_id === null) {
            $this->archives->deleteSet($backup); // the platform's own set: marking the row deleted alone left the archive on the backup disk for good

            return true;
        }
        if (in_array($service->family, self::COMPUTE_FAMILIES, true)) {
            return $this->expireAtProvider($service, $backup);
        }
        $features = $this->features->features($service);
        if (empty($features['backup_delete']['enabled'])) {
            return false; // the panel keeps its own retention (ISPConfig copies); nothing to delete remotely
        }
        [$tools, $ref] = $this->features->toolsFor($service);
        $tools->deleteBackup($ref, (string) $backup->remote_id);

        return true;
    }

    /**
     * A backup volume id in one of the two shapes Proxmox names them — a vzdump file
     * (`<storage>:backup/vzdump-qemu-<vmid>-YYYY_MM_DD-HH_MM_SS.<ext>`) or a PBS snapshot (`<storage>:backup/vm/<vmid>/<ISO time>`).
     * Kept here as well as in the adapter on purpose: the scheduler decides what it asks to delete, the adapter what it deletes.
     */
    private const VOLID = '~^([A-Za-z0-9][A-Za-z0-9._-]*):backup/(?:vzdump-qemu-(\d+)-\d{4}_\d{2}_\d{2}-\d{2}_\d{2}_\d{2}\.[A-Za-z0-9.]+|vm/(\d+)/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)$~';

    /**
     * A scheduled backup of a server past its retention, removed from the backup storage (`RetainedBackups`) — never through
     * `expireBackup()`, which unprotects first and is the final archive's alone. Asked only for a volume whose id names the
     * service's own VMID on the instance's own `backup_storage`; the adapter then reads the volume and refuses it unless it
     * is unprotected, of that guest and marked with this row's id. Until the volume is gone the row stays `completed`; why
     * it could not go is written once (`meta.delete_blocked`), not again at every tick.
     */
    private function expireAtProvider(Service $service, Backup $backup): bool
    {
        $volid = (string) $backup->remote_id;
        $binding = $service->primaryBinding();
        $vmid = $binding === null ? '' : (string) $binding->remote_id;
        $storage = (string) data_get(ProviderInstance::query()->find($service->provider_instance_id)?->options, 'backup_storage', '');
        $volume = preg_match(self::VOLID, $volid, $m) === 1 ? ['storage' => $m[1], 'vmid' => ($m[2] ?? '') !== '' ? $m[2] : (string) ($m[3] ?? '')] : null;
        if ((string) $backup->provider_instance_id !== (string) $service->provider_instance_id || $vmid === '' || $volume === null
            || $volume['vmid'] !== $vmid || $storage === '' || $volume['storage'] !== $storage) {
            return $this->deleteBlocked($backup, 'the volume is not proven to belong to this service\'s VM on its backup storage');
        }
        try {
            $adapter = $this->features->adapterFor($service);
            if (! $adapter instanceof RetainedBackups) {
                return $this->deleteBlocked($backup, 'the provider of this backup cannot remove it by retention');
            }
            $adapter->deleteRetainedBackup($volid, $vmid, RetainedBackups::MARKER_PREFIX.$backup->id);
        } catch (Throwable $e) {
            return $this->deleteBlocked($backup, mb_substr($e->getMessage(), 0, 200));
        }

        return true;
    }

    /** Writes down why a backup is still there — once per reason, so a volume that is never ours is not rewritten every tick. */
    private function deleteBlocked(Backup $backup, string $why): bool
    {
        if (data_get($backup->meta, 'delete_blocked.why') !== $why) {
            $backup->forceFill(['meta' => array_merge((array) $backup->meta, ['delete_blocked' => ['at' => now()->toIso8601String(), 'why' => $why]])])->save();
        }

        return false;
    }

    /** Copy the newest completed backup to the off-site disk once. */
    private function offsite(Service $service, array $schedule): int
    {
        $disk = (string) config('onhost.backups.offsite_disk', '');
        if (! $schedule['offsite'] || $disk === '' || config("filesystems.disks.{$disk}") === null) {
            return 0;
        }
        $backup = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('offsite', false)->where('kind', '!=', 'final')
            ->where(fn ($q) => $q->whereNotNull('remote_id')->orWhereNotNull('meta->set'))->orderByDesc('started_at')->first();
        if ($backup === null) {
            return 0;
        }
        if ($backup->remote_id === null) { // the platform's own set: every part goes to the off-site disk as it stands (checksums are in its manifest)
            if (! FinalArchive::isSet($backup)) {
                return 0;
            }
            $prefix = 'backups/'.$service->organization_id.'/'.$service->id.'/'.$backup->id;
            foreach ($this->archives->disk()->files((string) data_get($backup->meta, 'set')) as $file) {
                $stream = $this->archives->disk()->readStream($file);
                if (! is_resource($stream)) {
                    throw new DomainError('offsite_read', 'A part of the backup set cannot be read for the off-site copy.', 503);
                }
                Storage::disk($disk)->put($prefix.'/'.basename($file), $stream);
                fclose($stream);
            }
            $backup->forceFill(['offsite' => true, 'meta' => array_merge((array) $backup->meta, ['offsite_path' => $prefix, 'offsite_disk' => $disk, 'offsite_at' => now()->toIso8601String()])])->save();
            $this->outbox->publish(GenericEvent::of('backup.offsite', 'service', $service->id, ['backup_id' => $backup->id, 'disk' => $disk], $service->organization_id));

            return 1;
        }
        $features = $this->features->features($service);
        if (empty($features['backup_download']['enabled'])) {
            return 0;
        }
        [$tools, $ref] = $this->features->toolsFor($service);
        $local = tempnam(sys_get_temp_dir(), 'ohbk');
        try {
            $tools->downloadBackup($ref, (string) $backup->remote_id, $local);
            $path = 'backups/'.$service->organization_id.'/'.$service->id.'/'.$backup->id.'.bin';
            $stream = fopen($local, 'rb');
            Storage::disk($disk)->put($path, $stream ?: '');
            if (is_resource($stream)) {
                fclose($stream);
            }
            $backup->forceFill(['offsite' => true, 'meta' => array_merge((array) $backup->meta, ['offsite_path' => $path, 'offsite_disk' => $disk, 'offsite_at' => now()->toIso8601String()])])->save();
            $this->outbox->publish(GenericEvent::of('backup.offsite', 'service', $service->id, ['backup_id' => $backup->id, 'disk' => $disk], $service->organization_id));

            return 1;
        } finally {
            @unlink($local);
        }
    }
}
