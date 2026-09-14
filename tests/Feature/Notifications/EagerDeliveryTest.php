<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Notifications\SendMailOutboxJob;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Outbox\RelayOutboxJob;

/*
 * Speed of the customer journey: an event published in a request is relayed by a worker within seconds (one job per
 * burst), and a queued transactional mail leaves on the `mails` queue right away. The minute scheduler stays as the
 * safety net; the test suite itself runs with eager delivery off and relays explicitly.
 */

it('asks a worker to relay events and to send mails right after they are queued', function () {
    config(['onhost.outbox.eager' => true]);
    Queue::fake();
    [$user, $org] = $this->customerWithOrganization();

    $outbox = app(OutboxPublisher::class);
    $outbox->publish(GenericEvent::of('order.paid', 'order', 'ord_1', ['number' => 'OH-1'], $org->id));
    $outbox->publish(GenericEvent::of('order.active', 'order', 'ord_1', ['number' => 'OH-1'], $org->id));
    Queue::assertPushed(RelayOutboxJob::class, 1); // debounced: a burst of events costs one relay

    $mail = app(NotificationService::class)->queueMail('welcome', $user->email, ['jmeno' => $user->name, 'url' => 'https://example.test/panel'], 'user', $user->id, $org->id, 'cs', $user->id);
    expect($mail)->toBeInstanceOf(MailOutbox::class)->and($mail->state)->toBe('queued');
    Queue::assertPushed(SendMailOutboxJob::class, fn (SendMailOutboxJob $job) => $job->mailId === $mail->id && $job->queue === 'mails');

    config(['onhost.outbox.eager' => false]);
    $outbox->publish(GenericEvent::of('order.paid', 'order', 'ord_2', ['number' => 'OH-2'], $org->id));
    Queue::assertPushed(RelayOutboxJob::class, 1); // nothing more when eager delivery is off
});
