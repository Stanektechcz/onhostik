<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Notifications\Webhooks\WebhookCommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Http\EgressGuard;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\FakeHostResolver;

/*
 * D4 security review of PR #70 (TASK-0077): a webhook endpoint is not a way to make the platform send requests on demand
 * (ping cooldown, redeliver limits, an outbound ceiling per endpoint), a delivery is attempted a bounded number of times
 * and never twice at once, and a destination is https on 443/8443 at every attempt.
 */

beforeEach(fn () => Http::preventStrayRequests());

function d4lRelay(GenericEvent $event): void
{
    app(OutboxPublisher::class)->publish($event);
    app(OutboxPublisher::class)->relayPending();
}

/** @return array<string, mixed> the created endpoint (signed in, with a step-up) */
function d4lEndpoint(mixed $test, mixed $user, string $url = 'https://hooks.example.cz/onhost'): array
{
    $test->actingAs($user, 'sanctum');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    return $test->postJson('/v1/webhooks', ['url' => $url, 'events' => ['*']])->assertCreated()->json('data');
}

/** A delivery written straight into the table, as an older row or a test fixture would be. */
function d4lDelivery(string $endpointId, array $attributes = []): WebhookDelivery
{
    return WebhookDelivery::query()->create($attributes + [
        'endpoint_id' => $endpointId, 'event' => 'service.created', 'state' => 'delivered', 'attempts' => 1,
        'payload' => ['aggregate' => ['type' => 'service', 'id' => 'srv_l'], 'organization_id' => null, 'payload' => ['product_key' => 'vps']],
    ]);
}

it('lets an endpoint be pinged once per cooldown', function () {
    [$user] = $this->customerWithOrganization();
    $hook = d4lEndpoint($this, $user);
    Http::fake(['hooks.example.cz/*' => Http::response('', 204)]);

    $this->withHeader('Idempotency-Key', 'ping-1')->postJson("/v1/webhooks/{$hook['id']}/ping")->assertStatus(202);
    $this->withHeader('Idempotency-Key', 'ping-2')->postJson("/v1/webhooks/{$hook['id']}/ping")->assertStatus(429)->assertJsonPath('error', 'webhook_ping_cooldown');
    expect(Http::recorded())->toHaveCount(1);

    $this->travel(WebhookCommandHandler::PING_COOLDOWN_SECONDS + 1)->seconds();
    $this->withHeader('Idempotency-Key', 'ping-3')->postJson("/v1/webhooks/{$hook['id']}/ping")->assertStatus(202);
    expect(Http::recorded())->toHaveCount(2);
});

it('limits redelivery per endpoint, never resets the attempt count and caps the attempts of one delivery', function () {
    [$user] = $this->customerWithOrganization();
    $hook = d4lEndpoint($this, $user);
    Http::fake(['hooks.example.cz/*' => Http::response('', 200)]);
    $delivery = d4lDelivery($hook['id']);

    // one more attempt, counted on top of the earlier ones
    $this->withHeader('Idempotency-Key', 'rd-0')->postJson("/v1/webhooks/{$hook['id']}/deliveries/{$delivery->id}/redeliver")->assertStatus(202);
    expect($delivery->fresh()->attempts)->toBe(2)->and($delivery->fresh()->state)->toBe('delivered');

    // a delivery that was attempted as often as one may be is not sent again
    $spent = d4lDelivery($hook['id'], ['attempts' => WebhookDispatcher::MAX_ATTEMPTS, 'state' => 'dead']);
    $this->withHeader('Idempotency-Key', 'rd-spent')->postJson("/v1/webhooks/{$hook['id']}/deliveries/{$spent->id}/redeliver")->assertStatus(409)->assertJsonPath('error', 'webhook_redeliver_limit');
    expect(Http::recorded())->toHaveCount(1);

    // per endpoint, so many redeliveries an hour and no more (counted per request, whatever the delivery)
    Queue::fake();
    for ($i = 2; $i < WebhookCommandHandler::REDELIVERS_PER_HOUR; $i++) {
        $this->withHeader('Idempotency-Key', "rd-{$i}")->postJson("/v1/webhooks/{$hook['id']}/deliveries/{$delivery->id}/redeliver")->assertStatus(202);
    }
    $this->withHeader('Idempotency-Key', 'rd-over')->postJson("/v1/webhooks/{$hook['id']}/deliveries/{$delivery->id}/redeliver")->assertStatus(429)->assertJsonPath('error', 'webhook_redeliver_rate');
});

it('does not queue a delivery again while an attempt holds it, and counts attempts in the database, not from a stale copy', function () {
    [$user] = $this->customerWithOrganization();
    $hook = d4lEndpoint($this, $user);
    Queue::fake();
    $claimed = d4lDelivery($hook['id'], ['state' => 'failed', 'attempts' => 2, 'next_attempt_at' => now()->addMinute()]);

    $this->withHeader('Idempotency-Key', 'rd-claimed')->postJson("/v1/webhooks/{$hook['id']}/deliveries/{$claimed->id}/redeliver")->assertStatus(202)->assertJsonPath('data.state', 'failed');
    Queue::assertNothingPushed();
    expect($claimed->fresh()->attempts)->toBe(2);

    // two workers: the copy in hand says 0 attempts, the row says 3 — the attempt is the fourth
    Http::fake(['hooks.example.cz/*' => Http::response('', 500)]);
    $stale = d4lDelivery($hook['id'], ['state' => 'pending', 'attempts' => 0, 'next_attempt_at' => now()]);
    DB::table('webhook_deliveries')->where('id', $stale->id)->update(['attempts' => 3]);
    app(WebhookDispatcher::class)->deliver($stale, WebhookEndpoint::query()->findOrFail($hook['id']));
    expect($stale->fresh()->attempts)->toBe(4);
});

it('holds back what is over the outbound ceiling of an endpoint without counting it as a failure', function () {
    [$user, $org] = $this->customerWithOrganization();
    $hook = d4lEndpoint($this, $user);
    Http::fake();
    for ($i = 0; $i < WebhookDispatcher::OUTBOUND_PER_MINUTE; $i++) {
        RateLimiter::hit(WebhookDispatcher::outboundKey($hook['id']), 60);
    }

    d4lRelay(GenericEvent::of('service.created', 'service', 'srv_c', ['product_key' => 'vps', 'family' => 'vps'], $org->id));

    Http::assertNothingSent();
    $delivery = WebhookDelivery::query()->sole();
    expect($delivery->state)->toBe('pending')->and($delivery->attempts)->toBe(0)->and($delivery->next_attempt_at->isFuture())->toBeTrue()
        ->and(WebhookEndpoint::query()->findOrFail($hook['id'])->failures)->toBe(0);
});

it('accepts https on 443 or 8443 only, and checks scheme and port again at every attempt', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz:8080/in'])->assertStatus(422)->assertJsonPath('error', 'webhook_port_not_allowed');
    $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz:8443/in'])->assertCreated();

    // endpoints written before these rules: plain http, another port
    Http::fake();
    foreach (['http://legacy.example.cz/in' => 'https', 'https://legacy.example.cz:2083/in' => 'port'] as $url => $why) {
        WebhookEndpoint::query()->create(['organization_id' => $org->id, 'url' => $url, 'secret' => 'whsec_legacy', 'events' => ['service.*'], 'state' => 'active', 'failures' => 0]);
    }
    WebhookEndpoint::query()->where('url', 'like', '%:8443/%')->update(['state' => 'disabled']);
    d4lRelay(GenericEvent::of('service.created', 'service', 'srv_p', ['product_key' => 'vps', 'family' => 'vps'], $org->id));

    Http::assertNothingSent();
    $errors = WebhookDelivery::query()->pluck('last_error')->all();
    expect($errors)->toHaveCount(2)->and(implode(' | ', $errors))->toContain('https')->toContain('port');
});

it('refuses IPv4-mapped IPv6 addresses outright', function () {
    $guard = app(EgressGuard::class);
    expect($guard->isPublic('::ffff:10.0.0.5'))->toBeFalse()
        ->and($guard->isPublic('::ffff:93.184.216.34'))->toBeFalse()
        ->and($guard->isPublic('93.184.216.34'))->toBeTrue();
    FakeHostResolver::$hosts['mapped.example.cz'] = ['::ffff:192.168.1.10'];
    expect(fn () => $guard->check('https://mapped.example.cz/in'))->toThrow(DomainError::class);
});

it('waits out the whole backoff: the first attempt and one retry per backoff step, dead only when the last retry fails', function () {
    expect(WebhookDispatcher::retryScheduleMinutes())->toBe([1, 6, 36, 156, 876])
        ->and(WebhookDispatcher::scheduledAttempts())->toBe(count(WebhookDispatcher::BACKOFF_MINUTES) + 1);
    [$user, $org] = $this->customerWithOrganization();
    d4lEndpoint($this, $user);
    Http::fake(['hooks.example.cz/*' => Http::response('down', 503)]);
    $start = now()->toImmutable();

    d4lRelay(GenericEvent::of('service.created', 'service', 'srv_b', ['product_key' => 'vps', 'family' => 'vps'], $org->id));
    $delivery = WebhookDelivery::query()->sole();
    foreach (WebhookDispatcher::retryScheduleMinutes() as $i => $offset) {
        $delivery->refresh();
        expect($delivery->state)->toBe('failed')->and($delivery->attempts)->toBe($i + 1)
            ->and((int) round($start->diffInMinutes($delivery->next_attempt_at, true)))->toBe($offset);
        $this->travelTo($delivery->next_attempt_at);
        app(WebhookDispatcher::class)->retryDue();
    }

    $delivery->refresh();
    expect($delivery->state)->toBe('dead')->and($delivery->attempts)->toBe(6)->and($delivery->next_attempt_at)->toBeNull();
    expect(Http::recorded())->toHaveCount(6);
});
