<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Support\Carbon;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Is the server the customer pays for actually running (Brain card H14). Sites are probed over HTTP by the uptime
 * monitor; a VPS or a game server has no address of ours to probe, but the reconciler reads its power state from the
 * panel on every pass anyway. This watch turns that reading into an alarm: a server that is stopped although nobody
 * asked the platform to stop it — a crashed game server the daemon gave up restarting, a VM that went down with its
 * host — is reported once per episode after `power_passes_before_down` consecutive readings, and again when it runs.
 *
 * What keeps it quiet: only ACTIVE services are judged (a suspended or degraded one has its own alarm), a panel under
 * maintenance is only observed, an operation in flight explains any state, transitional states are not a verdict, a
 * stop the customer ordered here is the intent, and a game server whose own schedule stops it is not reported. The
 * customer can switch the mail off per service (`tags.policy.availability_alerts`); the episode is recorded regardless.
 */
final class AvailabilityWatch
{
    public const FAMILIES = ['cloud', 'data', 'game'];

    public function __construct(private readonly OutboxPublisher $outbox, private readonly ServiceFeatures $features) {}

    /** A verified power action is what the customer wants from now on: `running` or `stopped`. */
    public function intend(Service $service, string $target): void
    {
        if (! in_array($service->family, self::FAMILIES, true) || ! in_array($target, ['running', 'stopped'], true)) {
            return;
        }
        $tags = (array) ($service->tags ?? []);
        $watch = (array) ($tags['availability'] ?? []);
        $watch['intent'] = $target;
        if ($target === 'stopped') { // an open episode ends without a "runs again": the customer took over
            unset($watch['down_since'], $watch['passes'], $watch['alerted_at'], $watch['explained']);
        }
        $service->forceFill(['tags' => array_merge($tags, ['availability' => $watch])])->save();
    }

    /** One reading of the panel by the reconciler. Returns what the reading did: null, `counted`, `down`, `up` or `explained`. */
    public function observe(Service $service, string $status, bool $observeOnly = false): ?string
    {
        if (! in_array($service->family, self::FAMILIES, true) || $observeOnly || $service->state !== ServiceStateMachine::ACTIVE || ! in_array($status, ['running', 'stopped'], true)) {
            return null;
        }
        if (Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists()) {
            return null; // a restore, a reinstall or a migration stops the server on purpose
        }
        $tags = (array) ($service->tags ?? []);
        $watch = (array) ($tags['availability'] ?? []);
        $before = $watch;
        $outcome = null;
        if ($status === 'running') {
            if (! empty($watch['alerted_at'])) {
                $outcome = 'up';
                $this->outbox->publish(GenericEvent::of('service.running_again', 'service', $service->id, $this->payload($service, $watch) + ['minutes' => (int) Carbon::parse((string) ($watch['down_since'] ?? $watch['alerted_at']))->diffInMinutes(now(), true)], $service->organization_id));
            }
            $watch = ['intent' => 'running']; // whoever started it, a running server is the one to keep an eye on
        } elseif (($watch['intent'] ?? 'running') === 'running' && empty($watch['alerted_at']) && empty($watch['explained'])) { // one alarm per episode
            $watch['passes'] = (int) ($watch['passes'] ?? 0) + 1;
            $watch['down_since'] ??= now()->toIso8601String();
            $outcome = 'counted';
            if ($watch['passes'] >= max(1, (int) config('onhost.monitoring.power_passes_before_down', 2))) {
                if ($this->stoppedBySchedule($service)) {
                    $watch['explained'] = 'schedule';
                    $outcome = 'explained';
                } else {
                    $watch['alerted_at'] = now()->toIso8601String();
                    $outcome = 'down';
                    $this->outbox->publish(GenericEvent::of('service.stopped_unexpectedly', 'service', $service->id, $this->payload($service, $watch) + ['status' => $status, 'passes' => $watch['passes']], $service->organization_id));
                }
            }
        }
        if ($watch !== $before) {
            $service->forceFill(['tags' => array_merge($tags, ['availability' => $watch])])->save();
        }

        return $outcome;
    }

    /** What the panel row and the summary say about it. @return array{intent:string, down_since:?string, alerted:bool, explained:?string, alerts:bool} */
    public static function of(Service $service): array
    {
        $tags = (array) ($service->tags ?? []);
        $watch = (array) ($tags['availability'] ?? []);

        return [
            'intent' => (string) ($watch['intent'] ?? 'running'), 'down_since' => isset($watch['down_since']) ? (string) $watch['down_since'] : null,
            'alerted' => ! empty($watch['alerted_at']), 'explained' => isset($watch['explained']) ? (string) $watch['explained'] : null,
            'alerts' => (bool) ($tags['policy']['availability_alerts'] ?? true),
        ];
    }

    /** @param array<string,mixed> $watch @return array<string,mixed> */
    private function payload(Service $service, array $watch): array
    {
        return [
            'family' => $service->family, 'label' => $service->label, 'hostname' => $service->hostname ?: $service->name, 'since' => $watch['down_since'] ?? null,
            'sla_class' => $service->sla_class, 'notify' => (bool) (((array) ($service->tags ?? []))['policy']['availability_alerts'] ?? true),
        ];
    }

    /** A game server's own schedule may stop it every night; that is the customer's doing, not an outage. */
    private function stoppedBySchedule(Service $service): bool
    {
        if ($service->family !== 'game') {
            return false;
        }
        try {
            foreach ($this->features->resources($service, 'schedules') as $schedule) {
                foreach ((array) ($schedule['tasks'] ?? []) as $task) {
                    if (! empty($schedule['active']) && ($task['action'] ?? '') === 'power' && in_array($task['payload'] ?? '', ['stop', 'kill'], true)) {
                        return true;
                    }
                }
            }
        } catch (Throwable) {
            // the listing is not available: an alarm too many beats a blind spot
        }

        return false;
    }
}
