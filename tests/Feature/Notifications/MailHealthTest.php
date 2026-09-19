<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Notifications\MailHealth;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Does the platform's own mail still leave (Brain card H24: a deliverability incident has to reach somebody). A wrong
 * SMTP password made every invoice, reminder and password reset fail five times and stop — quietly — and dunning went
 * on to suspend customers for not answering reminders that were never delivered.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** A transport that refuses everything while `$down` is true. */
function mailHealthTransport(bool &$down): void
{
    Mail::shouldReceive('to')->andReturnUsing(function () use (&$down) {
        return new class($down)
        {
            public function __construct(private bool $down) {}

            public function send(mixed $mailable): void
            {
                if ($this->down) {
                    throw new RuntimeException('Failed to authenticate on SMTP server with username "mailer@onhost.cz" using password=Sup3rSecret');
                }
            }
        };
    });
}

function mailHealthQueue(int $count, string $template = 'ticket-ack'): void
{
    for ($i = 0; $i < $count; $i++) {
        MailOutbox::query()->create(['template_key' => $template, 'locale' => 'cs', 'to' => "zakaznik{$i}@test.cz", 'subject' => 'x', 'vars' => ['cislo' => 'TK-1', 'predmet' => 'x', 'sla' => 60, 'url' => 'https://x'], 'state' => 'queued', 'attempts' => 0]);
    }
}

it('says once that mail stopped leaving, holds enforcement meanwhile, and ends the alarm only on a real delivery', function () {
    $down = true;
    mailHealthTransport($down);
    $health = app(MailHealth::class);
    $notifications = app(NotificationService::class);
    expect($health->observe())->toMatchArray(['state' => 'ok', 'turn' => null]); // an empty outbox is not an outage

    mailHealthQueue(4);
    $notifications->sendQueued();
    $first = $health->observe();
    expect($first)->toMatchArray(['state' => 'failing', 'turn' => 'failing', 'errors' => 4, 'sent' => 0])
        ->and($first['last_error'])->toContain('SMTP')->not->toContain('Sup3rSecret'); // the transport's message is redacted before it travels
    expect($health->observe()['turn'])->toBeNull(); // the turn is reported, not every pass
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('name', 'platform.mail.failing')->count())->toBe(1)
        ->and(Notification::query()->where('audience', 'internal')->where('event', 'platform.mail.failing')->sole()->title)->toContain('neodchází')->toContain('nevymáhají')
        ->and(OnCallAlert::query()->where('event', 'platform.mail.failing')->whereNull('resolved_at')->count())->toBe(1) // the pager, because mail cannot carry this one
        ->and(MailOutbox::query()->where('template_key', '!=', 'ticket-ack')->count())->toBe(0);

    // reminders that did not arrive are no ground for a suspension
    [, $org] = $this->customerWithOrganization();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'desired_spec' => [], 'entitlements' => [], 'sla_class' => 'standard']);
    $case = DunningCase::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'state' => DunningCase::GRACE, 'due_at' => now()->subDays(40), 'notices_sent' => [3, 7, 14]]);
    expect(app(DunningService::class)->tick()['suspended'])->toBe(0)->and($case->fresh()->state)->toBe(DunningCase::GRACE);

    // everything gave up and the errors fell silent: that is not recovery
    $this->travel(3)->hours();
    $notifications->sendQueued();
    MailOutbox::query()->where('state', 'queued')->update(['state' => 'failed', 'attempts' => 5]);
    $this->travel(40)->minutes();
    $quiet = $health->observe();
    expect($quiet)->toMatchArray(['state' => 'failing', 'turn' => null, 'errors' => 0])->and($health->failing())->toBeTrue()
        ->and(MailOutbox::query()->where('state', 'queued')->count())->toBe(1); // one dead mail went back as a probe

    // the password is fixed: the probe goes through, the alarm ends, and what gave up is sent after all
    $down = false;
    $notifications->sendQueued();
    $back = $health->observe();
    expect($back)->toMatchArray(['state' => 'ok', 'turn' => 'recovered'])->and($health->failing())->toBeFalse();
    expect(OutboxMessage::query()->where('name', 'platform.mail.recovered')->sole()->payload['requeued'])->toBe(3);
    $notifications->sendQueued();
    expect(MailOutbox::query()->where('state', 'sent')->count())->toBe(4)->and(MailOutbox::query()->where('state', 'failed')->count())->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    expect(OnCallAlert::query()->where('event', 'platform.mail.failing')->whereNull('resolved_at')->count())->toBe(0);

    // and enforcement resumes
    $case->forceFill(['next_action_at' => null])->save();
    expect(app(DunningService::class)->tick()['suspended'])->toBe(1);
});

it('notices a sender that is not running, and lets a test mail that went through close the alarm', function () {
    $down = false;
    mailHealthTransport($down);
    $health = app(MailHealth::class);
    mailHealthQueue(2);
    $this->travel(20)->minutes(); // due for twenty minutes, never attempted: nobody runs `onhost:mail:send`
    expect($health->observe())->toMatchArray(['state' => 'failing', 'turn' => 'failing', 'errors' => 0, 'waiting' => 2])->and($health->observe()['oldest_minutes'])->toBeGreaterThanOrEqual(20);

    MailOutbox::query()->update(['state' => 'failed', 'attempts' => 5, 'last_error' => 'connection timed out']);
    $this->artisan('onhost:mail:test', ['to' => 'ops@onhost.test'])->expectsOutputToContain('queued again')->assertSuccessful();
    expect($health->failing())->toBeFalse()->and(MailOutbox::query()->where('state', 'queued')->count())->toBe(2)
        ->and(OutboxMessage::query()->where('name', 'platform.mail.recovered')->sole()->payload['confirmed_by'])->toBe('test mail');
});
