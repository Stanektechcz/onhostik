<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Penpot\PenpotDockerProvider;
use Throwable;

/**
 * The scheduled look at every running Penpot (TASK-0123, `onhost:penpot:sweep`):
 *  - is it there and does it answer? (containers on the node + one HTTP request to the stack's own port) — written to
 *    `tags.penpot.health`; a change from answering to silent (and back) is an event the customer and staff hear;
 *  - is its daily backup due? then the ordinary `backup` service action (database dump + assets on the node), kept for the
 *    plan's `backup_days`.
 */
final class PenpotSweep
{
    /** A backup is due when the last finished one is older than this. */
    public const BACKUP_EVERY_HOURS = 23;

    /** Two silent probes in a row before the customer is told (a restart takes a minute). */
    public const DOWN_AFTER = 2;

    public function __construct(private readonly ProviderRegistry $providers, private readonly ServiceService $services, private readonly OutboxPublisher $outbox) {}

    /** @return array{checked:int, answering:int, down:int, backups:int, errors:int} */
    public function run(bool $backups = true, int $limit = 500): array
    {
        $out = ['checked' => 0, 'answering' => 0, 'down' => 0, 'backups' => 0, 'errors' => 0];
        $services = Service::query()->where('family', PenpotInstances::FAMILY)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->orderBy('id')->limit($limit)->get();
        foreach ($services as $service) {
            $out['checked']++;
            try {
                $ok = $this->probe($service);
                $out[$ok ? 'answering' : 'down']++;
                if ($backups && $this->backupDue($service)) {
                    $this->services->requestAction($service, 'backup', CommandContext::system('penpot daily backup'), 'penpot:backup:'.$service->id.':'.now()->format('YmdH'), ['kind' => 'daily', 'retention_days' => max(1, (int) data_get($service->entitlements, 'backup_days', 14))]);
                    $out['backups']++;
                }
            } catch (DomainError $e) {
                $out['errors'] += $e->error === 'operation_in_progress' ? 0 : 1; // something else runs on it now: the next pass takes it
            } catch (Throwable) {
                $out['errors']++;
            }
        }

        return $out;
    }

    private function probe(Service $service): bool
    {
        $binding = $service->primaryBinding();
        $instance = $binding === null ? null : ProviderInstance::query()->find($binding->provider_instance_id);
        $adapter = $instance === null ? null : $this->providers->forInstance($instance);
        $status = 'missing';
        $http = 0;
        if ($adapter instanceof PenpotDockerProvider && $binding !== null) {
            try {
                $ref = $binding->ref();
                $status = (string) $adapter->getActualState($ref)->status;
                $http = $status === 'running' ? $adapter->probe($ref) : 0;
            } catch (Throwable) {
                $status = 'unreachable';
            }
        }
        $answering = $http >= 200 && $http < 400;
        $fresh = Service::query()->findOrFail($service->id);
        $tags = (array) $fresh->tags;
        $before = (array) data_get($tags, 'penpot.health', []);
        $misses = $answering ? 0 : (int) ($before['misses'] ?? 0) + 1;
        $wasDown = ($before['status'] ?? null) === 'down';
        $now = $answering ? 'up' : ($misses >= self::DOWN_AFTER ? 'down' : (string) ($before['status'] ?? 'up'));
        $tags['penpot'] = array_replace((array) ($tags['penpot'] ?? []), ['health' => [
            'status' => $now, 'containers' => $status, 'http' => $http, 'misses' => $misses, 'checked_at' => now()->toIso8601String(),
            'since' => $now === ($before['status'] ?? null) ? ($before['since'] ?? now()->toIso8601String()) : now()->toIso8601String(),
        ]]);
        $fresh->forceFill(['tags' => $tags])->save();
        $label = (string) ($fresh->label ?: $fresh->hostname);
        if ($now === 'down' && ! $wasDown) {
            $this->outbox->publish(GenericEvent::of('penpot.instance.unreachable', 'service', $fresh->id, ['label' => $label, 'status' => $status, 'http' => $http], $fresh->organization_id));
        } elseif ($now === 'up' && $wasDown) {
            $this->outbox->publish(GenericEvent::of('penpot.instance.recovered', 'service', $fresh->id, ['label' => $label], $fresh->organization_id));
        }

        return $answering;
    }

    private function backupDue(Service $service): bool
    {
        if (Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists()) {
            return false;
        }
        $last = Backup::query()->where('service_id', $service->id)->whereIn('state', ['completed', 'running'])->where('kind', '!=', 'final')->max('started_at');

        return $last === null || now()->diffInHours($last, true) >= self::BACKUP_EVERY_HOURS;
    }
}
