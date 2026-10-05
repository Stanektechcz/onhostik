<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0101: the relay takes a batch of messages ordered by available_at; messages published in the same instant
 * tie, and without a second key Postgres hands back an arbitrary subset of the tie — which events a batch delivered
 * (and which waited for the next run) changed from run to run. The id breaks the tie, the same on every database.
 */

it('relays messages that share an instant in id order, so a batch is the same subset on every database', function () {
    $at = now()->subMinute();
    // inserted in REVERSE id order: a database that answers in insertion (or any physical) order shows the tie
    foreach (['evt_0003', 'evt_0002', 'evt_0001'] as $id) {
        $message = new OutboxMessage(['aggregate_type' => 'test', 'aggregate_id' => 'x', 'name' => 'test.relay_order', 'payload' => [], 'available_at' => $at, 'attempts' => 0]);
        $message->id = $id;
        $message->save();
    }
    $seen = [];
    Event::listen('onhost.test.relay_order', function (OutboxMessage $m) use (&$seen) {
        $seen[] = $m->id;
    });

    expect(app(OutboxPublisher::class)->relayPending(2))->toBe(2)
        ->and($seen)->toBe(['evt_0001', 'evt_0002'])
        ->and(OutboxMessage::query()->whereNull('published_at')->pluck('id')->all())->toBe(['evt_0003']);
});
