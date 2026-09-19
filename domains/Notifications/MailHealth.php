<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Redaction\Redactor;

/**
 * Does the platform's own mail still leave (Brain card H24). Invoices, the reminders that come before a suspension,
 * password resets and incident notices are all mail; a wrong SMTP password or a blocked sender made every one of them
 * fail five times and stop — quietly. A customer would then be suspended for not answering reminders that were never
 * delivered. This watch reads the outbox the sender already keeps and says so: mail is failing when, in the last
 * window, deliveries errored and none went through, or when a due mail has been waiting far longer than the sender's
 * one-minute cadence (the sender is not running). It reports the turn, not every pass: once when it breaks
 * (`platform.mail.failing` — staff inbox and the pager, never by mail) and once when mail flows again.
 */
final class MailHealth
{
    private const STATE_KEY = 'onhost:mail:health';

    public function __construct(private readonly OutboxPublisher $outbox, private readonly Redactor $redactor) {}

    /** @return array{state:'ok'|'failing', sent:int, errors:int, waiting:int, oldest_minutes:int, dead:int, last_error:?string, turn:?string} */
    public function observe(): array
    {
        $reading = $this->measure();
        $before = (string) Cache::get(self::STATE_KEY, 'ok');
        if ($before === 'failing' && $reading['state'] === 'ok' && $reading['sent'] === 0) {
            // silence is not recovery: once everything gave up the errors simply stop. One dead mail goes back as a probe,
            // and only a delivery that went through ends the alarm
            $reading['state'] = 'failing';
            $this->probe();
        }
        $turn = $reading['state'] === $before ? null : ($reading['state'] === 'failing' ? 'failing' : 'recovered');
        if ($turn !== null) {
            Cache::forever(self::STATE_KEY, $reading['state']);
            $requeued = $turn === 'recovered' ? $this->requeueDead() : 0; // what gave up during the outage still has to reach its reader
            $this->outbox->publish(GenericEvent::of('platform.mail.'.$turn, 'platform', 'mail', array_diff_key($reading, ['state' => 1]) + ['requeued' => $requeued]));
        }

        return $reading + ['turn' => $turn];
    }

    /** The last verdict, without measuring: what enforcement asks before it acts on unanswered reminders. */
    public function failing(): bool
    {
        return Cache::get(self::STATE_KEY, 'ok') === 'failing';
    }

    /** A delivery was seen to work (`onhost:mail:test`): the alarm ends now and what gave up goes out again. */
    public function confirmDelivery(): int
    {
        if (! $this->failing()) {
            return 0;
        }
        Cache::forever(self::STATE_KEY, 'ok');
        $requeued = $this->requeueDead();
        $this->outbox->publish(GenericEvent::of('platform.mail.recovered', 'platform', 'mail', array_diff_key($this->measure(), ['state' => 1]) + ['requeued' => $requeued, 'confirmed_by' => 'test mail']));

        return $requeued;
    }

    /** One mail that gave up goes back into the queue, so the next pass has something to judge by. */
    private function probe(): void
    {
        $dead = MailOutbox::query()->where('state', 'failed')->where('updated_at', '>=', now()->subDay())->where(fn ($q) => $q->whereNull('last_error')->orWhere('last_error', '!=', 'template missing'))->orderByDesc('updated_at')->first();
        $dead?->forceFill(['state' => 'queued', 'attempts' => 4, 'scheduled_at' => null])->save(); // one attempt, not five
    }

    /** Mails that gave up in the last day for a transport reason go back into the queue; a missing template stays dead. */
    public function requeueDead(): int
    {
        return MailOutbox::query()->where('state', 'failed')->where('updated_at', '>=', now()->subDay())->where(fn ($q) => $q->whereNull('last_error')->orWhere('last_error', '!=', 'template missing'))
            ->update(['state' => 'queued', 'attempts' => 0, 'scheduled_at' => null]);
    }

    /** @return array{state:'ok'|'failing', sent:int, errors:int, waiting:int, oldest_minutes:int, dead:int, last_error:?string} */
    public function measure(): array
    {
        $window = now()->subMinutes(max(5, (int) config('onhost.mail_health.window_minutes', 30)));
        $sent = MailOutbox::query()->where('state', 'sent')->where('sent_at', '>=', $window)->count();
        $errored = MailOutbox::query()->whereIn('state', ['queued', 'failed'])->whereNotNull('last_error')->where('attempts', '>', 0)->where('updated_at', '>=', $window);
        $errors = (clone $errored)->count();
        $due = MailOutbox::query()->where('state', 'queued')->where('attempts', 0)->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()));
        $oldest = (clone $due)->min('created_at');
        $oldestMinutes = $oldest === null ? 0 : (int) now()->diffInMinutes($oldest, true);
        $lastError = (clone $errored)->orderByDesc('updated_at')->value('last_error');

        $failing = ($errors >= max(1, (int) config('onhost.mail_health.min_errors', 3)) && $sent === 0)
            || $oldestMinutes >= max(5, (int) config('onhost.mail_health.stalled_minutes', 15));

        return [
            'state' => $failing ? 'failing' : 'ok', 'sent' => $sent, 'errors' => $errors, 'waiting' => (clone $due)->count(), 'oldest_minutes' => $oldestMinutes,
            'dead' => MailOutbox::query()->where('state', 'failed')->where('updated_at', '>=', now()->subDay())->count(), // gave up after five attempts; requeued when mail flows again
            'last_error' => $lastError === null ? null : mb_substr($this->redactor->redactString((string) $lastError), 0, 200),
        ];
    }
}
