<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Onhost\Domain\Notifications\Mail\TemplatedMail;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

function publishAndRelay(GenericEvent $event): void
{
    app(OutboxPublisher::class)->publish($event);
    app(OutboxPublisher::class)->relayPending();
}

beforeEach(fn () => Http::preventStrayRequests());

it('routes a domain event to an in-app notification and a templated mail, then delivers the mail', function () {
    Mail::fake();
    [$user, $org] = $this->customerWithOrganization([], ['billing_email' => 'ucty@example.cz']);
    publishAndRelay(GenericEvent::of('invoice.issued', 'invoice', 'inv_test', ['number' => 'FV-2026-0042', 'type' => 'invoice', 'total' => Money::decimal('1210', 'CZK'), 'due_at' => '2026-09-20'], $org->id));

    $notification = Notification::query()->where('audience', 'customer')->where('organization_id', $org->id)->firstOrFail();
    expect($notification->kind)->toBe('invoice.issued')->and($notification->title)->toContain('FV-2026-0042')->and($notification->surface)->toBe('/panel/fakturace')->and($notification->read_at)->toBeNull();
    $mail = MailOutbox::query()->where('template_key', 'invoice')->firstOrFail();
    expect($mail->to)->toBe('ucty@example.cz')->and($mail->state)->toBe('queued')->and($mail->subject)->toBe('Doklad FV-2026-0042')->and($mail->vars['castka'])->toBe('1 210 Kč');

    $stats = app(NotificationService::class)->sendQueued();
    expect($stats)->toBe(['sent' => 1, 'failed' => 0]);
    Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $m) => $m->mailSubject === 'Doklad FV-2026-0042' && str_contains($m->body, '1 210 Kč') && $m->hasTo('ucty@example.cz'));
    expect($mail->fresh()->state)->toBe('sent')->and($mail->fresh()->sent_at)->not->toBeNull();

    $this->actingAs($user, 'sanctum');
    $this->getJson('/v1/notifications?unread=1')->assertOk()->assertHeader('X-Total-Count', '1')->assertJsonPath('data.0.kind', 'invoice.issued');
    $this->postJson('/v1/notifications/read', ['ids' => [$notification->id]])->assertOk()->assertJsonPath('data.read', 1);
    $this->getJson('/v1/notifications?unread=1')->assertOk()->assertHeader('X-Total-Count', '0');
});

it('honours user preferences for optional mails but never for mandatory legal/security notices', function () {
    [$user, $org] = $this->customerWithOrganization();
    $notifications = app(NotificationService::class);
    $notifications->setPreference($user->id, 'service', 'mail', false);
    expect(fn () => $notifications->setPreference($user->id, 'invoice.issued', 'mail', false))->toThrow(DomainError::class, 'mandatory');

    publishAndRelay(GenericEvent::of('service.activated', 'service', 'srv_test', ['product_key' => 'vps', 'family' => 'cloud', 'access' => ['ipv4' => '192.0.2.5']], $org->id));
    expect(MailOutbox::query()->where('template_key', 'service-activated')->value('state'))->toBe('skipped');
    expect(Notification::query()->where('kind', 'service')->where('audience', 'customer')->exists())->toBeTrue(); // in-app still delivered

    publishAndRelay(GenericEvent::of('security.login', 'user', $user->id, ['email' => $user->email, 'ip' => '10.0.0.7', 'user_agent' => 'pest', 'at' => now()->toIso8601String()]));
    $security = MailOutbox::query()->where('template_key', 'security-login')->firstOrFail();
    expect($security->state)->toBe('queued')->and($security->to)->toBe(strtolower($user->email));

    $this->actingAs($user, 'sanctum');
    $this->putJson('/v1/notifications/preferences', ['kind' => 'security.login', 'channel' => 'mail', 'enabled' => false])->assertUnprocessable()->assertJsonPath('error', 'notification_mandatory');
    $this->getJson('/v1/notifications/preferences')->assertOk()->assertJsonPath('data.0.kind', 'service');
});

it('reports missing placeholders on test render and refuses unknown templates', function () {
    $notifications = app(NotificationService::class);
    $render = $notifications->testRender('domain-renewal', 'mail', 'en', ['domena' => 'example.cz', 'dni' => 7]);
    expect($render['subject'])->toBe('Domain example.cz expires in 7 days')->and($render['missing'])->toBe(['expirace', 'autorenew', 'url'])->and($render['html'])->toContain('<p>');
    expect(fn () => $notifications->queueMail('no-such-template', 'a@b.cz', []))->toThrow(DomainError::class, 'not defined');
    $this->actingAs($this->staff('sre'), 'sanctum');
    $this->postJson('/v1/staff/templates/render', ['key' => 'welcome', 'vars' => ['jmeno' => 'Jana', 'organizace' => 'X', 'url' => 'https://onhost.cz/panel']])->assertForbidden(); // template management is not an SRE permission
});

it('signs and delivers customer webhooks, retries with backoff and pauses failing endpoints', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $created = $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost', 'events' => ['service.*', 'invoice.paid']])->assertCreated();
    $secret = $created->json('data.secret');
    expect($secret)->toStartWith('whsec_');
    $this->getJson('/v1/webhooks')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.secret');

    Http::fake(['hooks.example.cz/onhost' => Http::sequence()->push(['ok' => true], 200)->push('boom', 500)->push(['ok' => true], 200)]);
    publishAndRelay(GenericEvent::of('service.activated', 'service', 'srv_hook', ['product_key' => 'vps', 'access' => ['ipv4' => '192.0.2.9']], $org->id));
    publishAndRelay(GenericEvent::of('domain.renewed', 'domain', 'dom_x', ['fqdn' => 'example.cz'], $org->id)); // not subscribed → no delivery
    $delivery = WebhookDelivery::query()->firstOrFail();
    expect(WebhookDelivery::query()->count())->toBe(1)->and($delivery->state)->toBe('delivered')->and($delivery->event)->toBe('service.activated')->and($delivery->response_status)->toBe(200);
    Http::assertSent(function (Request $r) use ($secret) {
        $ts = $r->header('X-ONhost-Timestamp')[0];
        $expected = 'v1='.hash_hmac('sha256', $ts.'.'.$r->body(), $secret);

        return $r->header('X-ONhost-Signature')[0] === $expected && $r->header('X-ONhost-Event')[0] === 'service.activated' && json_decode($r->body(), true)['data']['payload']['access']['ipv4'] === '192.0.2.9';
    });

    publishAndRelay(GenericEvent::of('service.suspended', 'service', 'srv_hook', ['reason' => 'dunning'], $org->id));
    $failed = WebhookDelivery::query()->where('event', 'service.suspended')->firstOrFail();
    expect($failed->state)->toBe('failed')->and($failed->attempts)->toBe(1)->and($failed->next_attempt_at)->not->toBeNull()->and($failed->last_error)->toBe('HTTP 500');
    $this->travel(2)->minutes();
    expect(app(WebhookDispatcher::class)->retryDue())->toMatchArray(['delivered' => 1]);
    expect($failed->fresh()->state)->toBe('delivered');
    $this->getJson('/v1/webhooks/'.$created->json('data.id').'/deliveries')->assertOk()->assertHeader('X-Total-Count', '2');
});
