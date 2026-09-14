<?php

declare(strict_types=1);

namespace Onhost\Platform\Outbox;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Onhost\Platform\Events\DomainEvent;
use Onhost\Platform\Redaction\Redactor;
use Throwable;

final class OutboxPublisher
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly Redactor $redactor,
    ) {}

    /** Persist an event in the current transaction; it is delivered after commit. */
    public function publish(DomainEvent $event): OutboxMessage
    {
        $message = OutboxMessage::query()->create([
            'aggregate_type' => $event->aggregateType(),
            'aggregate_id' => $event->aggregateId(),
            'organization_id' => $event->organizationId(),
            'name' => $event->name(),
            'payload' => $this->redactor->redact($event->payload()),
            'correlation_id' => $event->correlationId(),
            'available_at' => now(),
            'attempts' => 0,
        ]);
        $this->relaySoon();

        return $message;
    }

    /**
     * Ask a worker to relay right after the surrounding transaction commits (seconds instead of the minute schedule);
     * one job per few seconds no matter how many events a request publishes. Off in tests, which relay explicitly.
     */
    private function relaySoon(): void
    {
        if (! (bool) config('onhost.outbox.eager', true)) {
            return;
        }
        try {
            if (! Cache::add('onhost:outbox:eager', 1, 5)) {
                return;
            }
            RelayOutboxJob::dispatch()->afterCommit();
        } catch (Throwable $e) {
            Log::warning('outbox eager relay not scheduled', ['error' => $e->getMessage()]); // the scheduler still relays every minute
        }
    }

    /** Deliver unpublished messages. Safe to call from the request cycle and from the relay command. */
    public function relayPending(int $limit = 100): int
    {
        // Never relay from inside an open application transaction: the message is not durable yet.
        // The test runner wraps every test in one transaction (RefreshDatabase); that outer level is the baseline there.
        if (DB::transactionLevel() > (app()->runningUnitTests() ? 1 : 0)) {
            return 0;
        }
        // one relay at a time: the eager job, the scheduler and a request-cycle relay must not deliver the same message twice
        $lock = Cache::lock('onhost:outbox:relay', 120);
        try {
            if (! $lock->block(15)) {
                return 0;
            }
        } catch (Throwable) {
            return 0; // another relay holds the lock; it takes these messages
        }
        try {
            return $this->relayLocked($limit);
        } finally {
            $lock->release();
        }
    }

    private function relayLocked(int $limit): int
    {
        $delivered = 0;
        $messages = OutboxMessage::query()
            ->whereNull('published_at')
            ->where('available_at', '<=', now())
            ->where('attempts', '<', 10)
            ->orderBy('available_at')
            ->limit($limit)
            ->get();
        foreach ($messages as $message) {
            try {
                $this->events->dispatch(new OutboxEventDispatched($message));
                $this->events->dispatch('onhost.'.$message->name, [$message]);
                $message->forceFill(['published_at' => now(), 'attempts' => $message->attempts + 1, 'last_error' => null])->save();
                $delivered++;
            } catch (Throwable $e) {
                $message->forceFill([
                    'attempts' => $message->attempts + 1,
                    'available_at' => now()->addSeconds(min(3600, 30 * (2 ** $message->attempts))),
                    'last_error' => mb_substr($this->redactor->redactString($e->getMessage()), 0, 500),
                ])->save();
            }
        }

        return $delivered;
    }
}
