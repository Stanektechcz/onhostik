<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxEventDispatched;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * available_at keeps whole seconds, so every message of one request ties on it. The relay takes them in publish order
 * (the time-ordered id breaks the tie) on SQLite and PostgreSQL alike; before, PostgreSQL could hand any subset of the
 * tied rows to a limited batch, which made the lexicon coverage test render a different batch per run.
 */

it('relays messages published in the same second in publish order, batch after batch', function () {
    $this->travelTo(Carbon::parse('2026-10-14 22:00:00', 'UTC'));
    $outbox = app(OutboxPublisher::class);
    $published = [];
    foreach (range(1, 6) as $i) {
        $published[] = $outbox->publish(GenericEvent::of('relay.order.probe', 'probe', 'p'.$i, ['i' => $i]))->id;
    }
    expect(OutboxMessage::query()->whereIn('id', $published)->distinct()->count('available_at'))->toBe(1); // they really tie

    $relayed = [];
    Event::listen(OutboxEventDispatched::class, function (OutboxEventDispatched $e) use (&$relayed, $published) {
        if (in_array($e->message->id, $published, true)) {
            $relayed[] = $e->message->id;
        }
    });
    foreach (range(1, 3) as $batch) {
        $outbox->relayPending(2);
    }

    expect($relayed)->toBe($published);
});
