<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * Outbox messages the relay gave up on (G7, TASK-0115): OutboxPublisher stops after MAX_ATTEMPTS failed deliveries, and the
 * message stays unpublished for ever — the notification, the webhook, the follow-up the event should have started never
 * happen. Nothing said so: the lag gauge counted them as "pending" (and kept the lag alert and /healthz red for a reason
 * the relay could not fix), the doctor counted nothing. Now: a doctor row (count, oldest age, remedy), the Prometheus gauges
 * `onhost_outbox_dead_letters` / `onhost_outbox_dead_letter_oldest_seconds` and the alert OnhostOutboxDeadLetters
 * (infra/monitoring/slo-alerts.yml); `onhost:outbox:dead-letters` lists them and puts them back on the relay.
 */
final class OutboxDeadLetters
{
    /** The relay's limit: `OutboxPublisher::relayLocked()` picks a message only while `attempts < 10` (OutboxDeadLetterAlertTest pins it). */
    public const MAX_ATTEMPTS = 10;

    /** @return Builder<OutboxMessage> */
    public static function query(): Builder
    {
        return OutboxMessage::query()->whereNull('published_at')->where('attempts', '>=', self::MAX_ATTEMPTS);
    }

    /** @return array{count:int, oldest_seconds:int, names:list<string>} counts and event names only, never a payload */
    public function measure(): array
    {
        $count = self::query()->count();
        $oldest = $count === 0 ? null : self::query()->min('created_at');
        $names = $count === 0 ? [] : self::query()->distinct()->orderBy('name')->limit(5)->pluck('name')->map(fn ($name) => (string) $name)->all();

        return [
            'count' => $count,
            'oldest_seconds' => $oldest === null ? 0 : max(0, (int) Carbon::parse((string) $oldest)->diffInSeconds(now(), true)),
            'names' => array_values($names),
        ];
    }

    /** @return array{area:string, check:string, ok:bool, detail:string, remedy:string, blocking:bool} the doctor row (GoLiveChecks) */
    public function row(): array
    {
        $m = $this->measure();
        $ok = $m['count'] === 0;
        $detail = $ok ? 'the relay delivered or is still retrying every message'
            : $m['count'].' message(s) the relay gave up on after '.self::MAX_ATTEMPTS.' attempts, oldest '.self::age($m['oldest_seconds']).' old ('.implode(', ', $m['names']).($m['count'] > count($m['names']) ? ', …' : '').')';

        return [
            'area' => 'observability', 'check' => 'no outbox dead letters', 'ok' => $ok, 'detail' => $detail,
            'remedy' => $ok ? '' : 'php artisan onhost:outbox:dead-letters (the last error of each), fix the listener that fails, then onhost:outbox:dead-letters --requeue; runbook docs/runbooks/outbox-dead-letters.md',
            'blocking' => false,
        ];
    }

    private static function age(int $seconds): string
    {
        return match (true) {
            $seconds >= 86400 => intdiv($seconds, 86400).' d',
            $seconds >= 3600 => intdiv($seconds, 3600).' h',
            default => intdiv($seconds, 60).' min',
        };
    }
}
