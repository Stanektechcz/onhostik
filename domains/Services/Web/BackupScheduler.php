<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
use Throwable;

/**
 * Scheduled backups for web and mail services: the plan's entitlements say how often (15m, hourly, 6h, daily),
 * how many days and how many generations to keep and whether a copy goes off-site. Backups run as ordinary
 * `backup` operations (one per slot, idempotent); expired ones are deleted on the node, generation caps trim the
 * oldest, and the off-site copy is streamed through the control plane to the configured disk.
 */
final class BackupScheduler
{
    public const FREQUENCIES = ['15m' => 15, 'hourly' => 60, '6h' => 360, 'daily' => 1440, 'weekly' => 10080];

    public function __construct(private readonly ServiceService $services, private readonly ServiceFeatures $features, private readonly OutboxPublisher $outbox) {}

    /** @return array{started:int, skipped:int, deleted:int, offsite:int, errors:int} */
    public function tick(int $limit = 100): array
    {
        $stats = ['started' => 0, 'skipped' => 0, 'deleted' => 0, 'offsite' => 0, 'errors' => 0];
        $context = CommandContext::system('backup scheduler');
        $services = Service::query()->whereIn('family', ['web', 'managed', 'mail'])->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->whereNotNull('provider_instance_id')->orderBy('id')->limit($limit)->get();
        foreach ($services as $service) {
            try {
                $schedule = $this->scheduleFor($service);
                if ($schedule === null) {
                    continue;
                }
                if ($this->due($service, $schedule)) {
                    $this->services->requestAction($service, 'backup', $context, 'backup:auto:'.$service->id.':'.$schedule['slot'], ['kind' => 'scheduled', 'retention_days' => $schedule['days'], 'policy' => ['notes' => 'scheduled '.$schedule['frequency']]]);
                    $stats['started']++;
                } else {
                    $stats['skipped']++;
                }
                $stats['deleted'] += $this->prune($service, $schedule, $context);
                $stats['offsite'] += $this->offsite($service, $schedule);
            } catch (DomainError $e) {
                $stats['skipped']++; // another operation in progress, frozen provisioning, feature not available
            } catch (Throwable $e) {
                $stats['errors']++;
                report($e);
            }
        }

        return $stats;
    }

    /** @return array{frequency:string, minutes:int, days:int, generations:int, offsite:bool, slot:string, window_start:Carbon}|null */
    public function scheduleFor(Service $service): ?array
    {
        $features = $this->features->features($service);
        $schedule = $features['backup_schedule'] ?? null;
        if (empty($features['backups']['enabled']) || $schedule === null || empty($schedule['enabled'])) {
            return null;
        }
        $policy = BackupPolicy::query()->where('service_id', $service->id)->first();
        $options = (array) ($schedule['options'] ?? []);
        $frequency = (string) ($policy?->schedule['frequency'] ?? $options['frequency'] ?? 'daily');
        $minutes = self::FREQUENCIES[$frequency] ?? self::FREQUENCIES['daily'];
        $days = max(1, (int) ($policy?->retention['days'] ?? $options['days'] ?? 7));
        $generations = max(1, (int) ($policy?->retention['generations'] ?? $options['generations'] ?? 7));
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

        return ['frequency' => $frequency, 'minutes' => $minutes, 'days' => $days, 'generations' => $generations, 'offsite' => $offsite, 'slot' => $windowStart->format('YmdHi'), 'window_start' => $windowStart];
    }

    private function due(Service $service, array $schedule): bool
    {
        $last = Backup::query()->where('service_id', $service->id)->where('kind', 'scheduled')->whereIn('state', ['running', 'completed'])->orderByDesc('started_at')->first();

        return $last === null || $last->started_at === null || $last->started_at->lessThan($schedule['window_start']);
    }

    /** Delete expired backups and trim generations beyond the plan; protected ones are never touched. */
    private function prune(Service $service, array $schedule, CommandContext $context): int
    {
        $deleted = 0;
        $expired = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('protected', false)->where(function ($q) {
            $q->whereNotNull('retention_until')->where('retention_until', '<', now());
        })->get();
        $surplus = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('protected', false)->where('kind', 'scheduled')->orderByDesc('started_at')->skip($schedule['generations'])->take(50)->get();
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
            return true;
        }
        $features = $this->features->features($service);
        if (empty($features['backup_delete']['enabled'])) {
            return false; // the panel keeps its own retention (ISPConfig copies); nothing to delete remotely
        }
        [$tools, $ref] = $this->features->toolsFor($service);
        $tools->deleteBackup($ref, (string) $backup->remote_id);

        return true;
    }

    /** Copy the newest completed backup to the off-site disk once. */
    private function offsite(Service $service, array $schedule): int
    {
        $disk = (string) config('onhost.backups.offsite_disk', '');
        if (! $schedule['offsite'] || $disk === '' || config("filesystems.disks.{$disk}") === null) {
            return 0;
        }
        $backup = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('offsite', false)->whereNotNull('remote_id')->orderByDesc('started_at')->first();
        if ($backup === null) {
            return 0;
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
