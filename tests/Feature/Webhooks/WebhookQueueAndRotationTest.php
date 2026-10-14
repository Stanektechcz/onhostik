<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\Webhooks\DeliverWebhook;
use Onhost\Domain\Notifications\Webhooks\WebhookQueue;
use Onhost\Domain\Notifications\Webhooks\WebhookSigner;
use Onhost\Domain\Platform\QueueLaneHeartbeat;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * G7 (TASK-0115), two webhook follow-ups:
 *  · deliveries go to a queue lane of their own (`webhooks`), so a burst of slow customer endpoints does not hold the `default`
 *    lane — but only while a worker loops on that lane; an installation that runs no `onhost-queue@webhooks` keeps delivering
 *    from the default queue instead of piling deliveries up where nobody works;
 *  · rotating the signing secret has an overlap: for `onhost.webhooks.secret_overlap_minutes` the previous secret still signs,
 *    in `X-ONhost-Signature-Previous`, next to the new one in `X-ONhost-Signature`. A receiver accepts a delivery when either
 *    signature verifies with the secret it holds, so it can switch secrets without losing a delivery.
 */

beforeEach(fn () => Http::preventStrayRequests());

function g7HookSignIn(mixed $test, mixed $user): void
{

    $test->actingAs($user, 'sanctum');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

}

function g7HookEvent(string $organizationId, string $id = 'inv_g7'): void
{
    app(OutboxPublisher::class)->publish(GenericEvent::of('invoice.issued', 'invoice', $id, ['number' => 'F-2026-0777', 'type' => 'invoice', 'total' => ['minor' => 1000, 'currency' => 'CZK'], 'due_at' => '2026-10-18', 'notify' => true], $organizationId));
    app(OutboxPublisher::class)->relayPending();
}

it('queues a delivery on the webhooks lane while a worker loops on it, and on the default queue otherwise', function () {
    [$user, $org] = $this->customerWithOrganization();
    g7HookSignIn($this, $user);
    $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost', 'events' => ['invoice.*']])->assertCreated();
    Queue::fake();

    expect(WebhookQueue::name())->toBeNull(); // nobody works the lane: the connection's default queue
    g7HookEvent($org->id, 'inv_g7_a');
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->queue === null);

    app(QueueLaneHeartbeat::class)->beat('webhooks'); // `onhost-queue@webhooks` loops
    expect(WebhookQueue::name())->toBe('webhooks');
    g7HookEvent($org->id, 'inv_g7_b');
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->queue === 'webhooks');

    config()->set('onhost.webhooks.queue', ''); // switched off on purpose: the default queue again
    expect(WebhookQueue::name())->toBeNull();
    $this->travel(QueueLaneHeartbeat::STALE_MINUTES + 1)->minutes(); // and a lane whose worker stopped looping is not used
    config()->set('onhost.webhooks.queue', 'webhooks');
    expect(WebhookQueue::name())->toBeNull();
});

it('keeps the previous secret signing next to the new one for the overlap window, then only the new one', function () {
    config()->set('onhost.webhooks.secret_overlap_minutes', 60);
    [$user, $org] = $this->customerWithOrganization();
    g7HookSignIn($this, $user);
    $hook = $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost', 'events' => ['invoice.*']])->assertCreated()->json('data');
    $old = (string) $hook['secret'];
    $rotated = $this->postJson("/v1/webhooks/{$hook['id']}/rotate-secret", [], ['Idempotency-Key' => 'g7-rotate-1'])->assertOk()->json('data');
    $new = (string) $rotated['secret'];
    expect($new)->not->toBe($old)->and($rotated['previous_secret_valid_until'])->not->toBeNull()
        ->and(json_encode($rotated))->not->toContain($old) // the old secret is never shown again
        ->and(json_encode(OutboxMessage::query()->pluck('payload')->all()))->not->toContain($old)->not->toContain($new);
    Http::fake(['hooks.example.cz/*' => Http::response('', 204)]);

    g7HookEvent($org->id, 'inv_g7_overlap');
    [$request] = Http::recorded()->all()[0];
    $timestamp = $request->header('X-ONhost-Timestamp')[0];
    $body = $request->body();
    $current = $request->header('X-ONhost-Signature')[0];
    $previous = $request->header('X-ONhost-Signature-Previous')[0] ?? '';
    // a receiver that already holds the new secret verifies the usual header; one that still holds the old one, the previous
    expect(WebhookSigner::verify($new, $timestamp, $body, $current))->toBeTrue()
        ->and(WebhookSigner::verify($old, $timestamp, $body, $previous))->toBeTrue()
        ->and(WebhookSigner::verifyAny($old, $timestamp, $body, [$current, $previous]))->toBeTrue()
        ->and(WebhookSigner::verifyAny('whsec_somebody_else', $timestamp, $body, [$current, $previous]))->toBeFalse();

    $this->travel(61)->minutes();
    g7HookEvent($org->id, 'inv_g7_after');
    [$later] = Http::recorded()->all()[1];
    expect($later->header('X-ONhost-Signature-Previous'))->toBe([])
        ->and(WebhookSigner::verifyAny($old, $later->header('X-ONhost-Timestamp')[0], $later->body(), [$later->header('X-ONhost-Signature')[0]]))->toBeFalse()
        ->and(WebhookEndpoint::query()->findOrFail($hook['id'])->previousSecret())->toBeNull();
});

it('rotates at once when the overlap is switched off, and a second rotation keeps only the secret it replaced', function () {
    [$user, $org] = $this->customerWithOrganization();
    g7HookSignIn($this, $user);
    $hook = $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost', 'events' => ['invoice.*']])->assertCreated()->json('data');

    config()->set('onhost.webhooks.secret_overlap_minutes', 0);
    $first = $this->postJson("/v1/webhooks/{$hook['id']}/rotate-secret", [], ['Idempotency-Key' => 'g7-rotate-a'])->assertOk()->json('data');
    expect($first['previous_secret_valid_until'])->toBeNull()
        ->and(WebhookEndpoint::query()->findOrFail($hook['id'])->previousSecret())->toBeNull();

    config()->set('onhost.webhooks.secret_overlap_minutes', 30);
    $second = $this->postJson("/v1/webhooks/{$hook['id']}/rotate-secret", [], ['Idempotency-Key' => 'g7-rotate-b'])->assertOk()->json('data');
    $third = $this->postJson("/v1/webhooks/{$hook['id']}/rotate-secret", [], ['Idempotency-Key' => 'g7-rotate-c'])->assertOk()->json('data');
    expect(WebhookEndpoint::query()->findOrFail($hook['id'])->previousSecret())->toBe($second['secret'])
        ->and(WebhookEndpoint::query()->findOrFail($hook['id'])->previousSecret())->not->toBe($first['secret'])
        ->and($third['secret'])->not->toBe($second['secret']);
});

it('ends a leaked secret at once when the rotation asks for no overlap', function () {
    config()->set('onhost.webhooks.secret_overlap_minutes', 60);
    [$user, $org] = $this->customerWithOrganization();
    g7HookSignIn($this, $user);
    $hook = $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost', 'events' => ['invoice.*']])->assertCreated()->json('data');

    $rotated = $this->postJson("/v1/webhooks/{$hook['id']}/rotate-secret", ['overlap' => false], ['Idempotency-Key' => 'g7-rotate-leak'])->assertOk()->json('data');
    Http::fake(['hooks.example.cz/*' => Http::response('', 204)]);
    g7HookEvent($org->id, 'inv_g7_leak');

    [$request] = Http::recorded()->all()[0];
    expect($rotated['previous_secret_valid_until'])->toBeNull()
        ->and($request->header('X-ONhost-Signature-Previous'))->toBe([])
        ->and(WebhookSigner::verify((string) $rotated['secret'], $request->header('X-ONhost-Timestamp')[0], $request->body(), $request->header('X-ONhost-Signature')[0]))->toBeTrue();
    $this->postJson("/v1/webhooks/{$hook['id']}/rotate-secret", ['overlap' => 'nope'], ['Idempotency-Key' => 'g7-rotate-bad'])->assertUnprocessable();
});

it('documents the queue lane and the overlap where operators and receivers read them', function () {
    $docs = (string) file_get_contents(base_path('docs/api/README.md'));
    $unit = (string) file_get_contents(base_path('infra/systemd/onhost-queue@.service'));
    expect($docs)->toContain('X-ONhost-Signature-Previous')->toContain('either signature')
        ->and($unit)->toContain('onhost-queue@webhooks')
        ->and(file_exists(base_path('docs/runbooks/webhooks.md')))->toBeTrue();
});
