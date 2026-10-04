<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\Webhooks\DeliverWebhook;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\FakeHostResolver;

/*
 * D4 (TASK-0077): a customer webhook carries the PUBLIC face of an event, from the queue, signed the one way the docs
 * describe, written through the bus, and a customer hears when their endpoint was suspended.
 */

beforeEach(fn () => Http::preventStrayRequests());

function d4Relay(GenericEvent $event): void
{
    app(OutboxPublisher::class)->publish($event);
    app(OutboxPublisher::class)->relayPending();
}

function d4SignIn(mixed $test, mixed $user): void
{
    $test->actingAs($user, 'sanctum');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
}

/** @return array<string, mixed> */
function d4Endpoint(mixed $test, array $events = ['*'], string $url = 'https://hooks.example.cz/onhost'): array
{
    return $test->postJson('/v1/webhooks', ['url' => $url, 'events' => $events])->assertCreated()->json('data');
}

/** @return list<array{0: Request, 1: mixed}> */
function d4Sent(): array
{
    return Http::recorded()->all();
}

it('signs a delivery exactly as the API docs say, and the receiver can verify it with the secret it was shown once', function () {
    [$user, $org] = $this->customerWithOrganization();
    d4SignIn($this, $user);
    $hook = d4Endpoint($this, ['invoice.*']);
    Http::fake(['hooks.example.cz/*' => Http::response('', 204)]);

    d4Relay(GenericEvent::of('invoice.issued', 'invoice', 'inv_d4', ['number' => 'F-2026-0042', 'type' => 'invoice', 'total' => ['minor' => 121000, 'currency' => 'CZK'], 'due_at' => '2026-10-18', 'notify' => true], $org->id));

    expect(d4Sent())->toHaveCount(1);
    [$request] = d4Sent()[0];
    $delivery = WebhookDelivery::query()->sole();
    $timestamp = $request->header('X-ONhost-Timestamp')[0] ?? '';
    $body = $request->body();
    expect($request->method())->toBe('POST')
        ->and($request->url())->toBe('https://hooks.example.cz/onhost')
        ->and($request->header('Content-Type')[0])->toBe('application/json')
        ->and($request->header('X-ONhost-Event')[0])->toBe('invoice.issued')
        ->and($request->header('X-ONhost-Delivery')[0])->toBe($delivery->id)
        ->and(ctype_digit($timestamp) && abs(time() - (int) $timestamp) < 60)->toBeTrue()
        ->and($request->header('X-ONhost-Signature')[0])->toBe('v1='.hash_hmac('sha256', $timestamp.'.'.$body, $hook['secret']))
        ->and($request->header('X-ONhost-Signature')[0])->not->toBe('v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_somebody_else'))
        ->and(json_decode($body, true))->toBe([
            'id' => $delivery->id, 'event' => 'invoice.issued', 'created_at' => $delivery->created_at->toIso8601String(),
            'data' => ['aggregate' => ['type' => 'invoice', 'id' => 'inv_d4'], 'organization_id' => $org->id, 'payload' => ['number' => 'F-2026-0042', 'type' => 'invoice', 'total' => ['minor' => 121000, 'currency' => 'CZK'], 'due_at' => '2026-10-18']],
        ])
        ->and($delivery->state)->toBe('delivered');

    // the documented format is the sent one, and the format that was documented but never sent is gone
    $docs = (string) file_get_contents(base_path('docs/api/README.md'));
    expect($docs)->toContain('X-ONhost-Signature: v1=')->toContain('X-ONhost-Timestamp')->not->toContain('t=<ts>,v1=');
});

it('sends from the queue: the outbox relay writes the delivery and makes no HTTP call itself', function () {
    [$user, $org] = $this->customerWithOrganization();
    d4SignIn($this, $user);
    d4Endpoint($this);
    Http::fake();
    Queue::fake();

    d4Relay(GenericEvent::of('service.created', 'service', 'srv_q', ['product_key' => 'vps', 'family' => 'vps', 'order_item_id' => 'oi_1'], $org->id));

    $delivery = WebhookDelivery::query()->sole();
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->deliveryId === $delivery->id);
    Http::assertNothingSent();
    expect($delivery->state)->toBe('pending')->and($delivery->payload['payload'])->toBe(['product_key' => 'vps', 'family' => 'vps']);
});

it('puts only the allow-listed fields of each event family on the wire — no operation ids, nodes, vendors, staff or error text', function () {
    [$user, $org] = $this->customerWithOrganization();
    d4SignIn($this, $user);
    d4Endpoint($this);
    Http::fake(['hooks.example.cz/*' => Http::response('', 200)]);
    $money = ['minor' => 50000, 'currency' => 'CZK'];
    // [event, aggregate type, what the platform published, what the customer receives]
    $cases = [
        ['order.placed', 'order', ['number' => 'O-1', 'state' => 'NEW', 'total' => $money, 'mode' => 'card', 'requester_id' => 'usr_internal', 'operation_id' => 'op_internal'], ['number' => 'O-1', 'state' => 'NEW', 'total' => $money, 'mode' => 'card']],
        ['order.review.required', 'order', ['number' => 'O-2', 'score' => 87, 'reasons' => ['velocity', 'proxy'], 'total' => $money], ['number' => 'O-2']],
        ['service.activated', 'service', ['product_key' => 'vps', 'family' => 'vps', 'access' => ['ipv4' => '192.0.2.9', 'node' => 'pve-node-3'], 'order_item_id' => 'oi_1', 'operation_id' => 'op_internal'], ['product_key' => 'vps', 'family' => 'vps', 'access' => ['ipv4' => '192.0.2.9']]],
        ['service.migrated', 'service', ['label' => 'eshop', 'hostname' => 'eshop.cz', 'family' => 'web', 'from_node' => 'pve-node-3', 'to_node' => 'pve-node-4', 'address' => '10.0.0.5'], ['label' => 'eshop', 'hostname' => 'eshop.cz', 'family' => 'web']],
        ['service.suspended', 'service', ['product_key' => 'web', 'reason' => 'dunning', 'state' => 'SUSPENDED', 'operation_id' => 'op_internal'], ['product_key' => 'web', 'state' => 'SUSPENDED', 'reason' => 'dunning']],
        ['service.terminated', 'service', ['product_key' => 'web', 'reason' => 'ISPConfig node pve-node-3 said: client 42 locked', 'state' => 'TERMINATED'], ['product_key' => 'web', 'state' => 'TERMINATED']],
        ['invoice.issued', 'invoice', ['number' => 'F-1', 'type' => 'invoice', 'total' => $money, 'due_at' => '2026-10-18', 'notify' => true], ['number' => 'F-1', 'type' => 'invoice', 'total' => $money, 'due_at' => '2026-10-18']],
        ['wallet.runway.low', 'wallet', ['days' => 5, 'depletes_at' => '2026-10-09', 'shortfall' => $money, 'available' => $money, 'renewals_30d' => [['service' => 'srv_x']], 'next_renewal' => ['id' => 'srv_x']], ['days' => 5, 'depletes_at' => '2026-10-09', 'shortfall' => $money, 'available' => $money]],
        ['domain.external_expiry_notice', 'domain', ['fqdn' => 'shop.cz', 'days' => 30, 'days_left' => 29, 'expires_at' => '2026-11-02', 'registrar' => 'WEDOS', 'account' => 'acct-onhost-1', 'connection_id' => 'rgc_1'], ['fqdn' => 'shop.cz', 'days_left' => 29, 'expires_at' => '2026-11-02']],
        ['dns.zone.committed', 'dns_zone', ['name' => 'shop.cz', 'version' => 4, 'serial' => 2026100401, 'records' => 12, 'provider' => 'powerdns-node-3'], ['name' => 'shop.cz', 'version' => 4, 'serial' => 2026100401]],
        ['subscription.renewal_failed', 'subscription', ['service_id' => 'srv_x', 'required' => $money, 'cause' => 'insufficient_credit', 'period_end' => '2026-10-31', 'attempt' => 2], ['service_id' => 'srv_x', 'required' => $money, 'cause' => 'insufficient_credit', 'period_end' => '2026-10-31', 'attempt' => 2]],
        ['dunning.opened', 'dunning_case', ['invoice_id' => 'inv_x', 'service_id' => 'srv_x', 'due_at' => '2026-10-01'], ['invoice_id' => 'inv_x', 'service_id' => 'srv_x', 'due_at' => '2026-10-01']],
        ['ticket.replied', 'ticket', ['number' => 'T-1', 'subject' => 'Help', 'email' => 'jana@shop.cz', 'author_type' => 'staff', 'state' => 'WAITING_CUSTOMER', 'excerpt' => 'see node pve-node-3'], ['number' => 'T-1', 'state' => 'WAITING_CUSTOMER', 'author_type' => 'staff']], // the subject is free text
        ['incident.updated', 'incident', ['number' => 'INC-7', 'title' => 'Web nodes slow', 'severity' => 'major', 'components' => ['web'], 'note' => 'Investigating', 'state' => 'investigating', 'state_label' => 'Prověřujeme'], ['number' => 'INC-7', 'severity' => 'major', 'components' => ['web'], 'state' => 'investigating']], // titles and notes are staff free text: the status page has them
        ['maintenance.scheduled', 'maintenance', ['number' => 'M-1', 'title' => 'Kernel', 'components' => ['vps'], 'starts_at' => '2026-10-10T22:00:00+02:00', 'ends_at' => '2026-10-10T23:00:00+02:00', 'impact' => 'reboot'], ['number' => 'M-1', 'components' => ['vps'], 'starts_at' => '2026-10-10T22:00:00+02:00', 'ends_at' => '2026-10-10T23:00:00+02:00', 'impact' => 'reboot']],
        ['monitoring.up', 'service', ['monitor_id' => 'mon_1', 'incident_id' => 'minc_1', 'url' => 'https://shop.cz/', 'minutes' => 4, 'notify' => true], ['monitor_id' => 'mon_1', 'url' => 'https://shop.cz/', 'minutes' => 4]],
        ['deploy.failed', 'service', ['deployment_id' => 'dep_1', 'ref' => 'main', 'sha' => 'abc1234', 'release' => 'r5', 'error' => 'composer failed in /www/wwwroot/pve-node-3'], ['deployment_id' => 'dep_1', 'ref' => 'main', 'sha' => 'abc1234']],
        ['staging.failed', 'service', ['action' => 'push', 'error' => 'rsync to pve-node-3 failed'], ['action' => 'push']],
        ['import.succeeded', 'service', ['import_id' => 'imp_1', 'kind' => 'url', 'stats' => ['files' => 10, 'databases' => 1, 'node' => 'pve-node-3'], 'error' => null], ['import_id' => 'imp_1', 'kind' => 'url', 'stats' => ['files' => 10, 'databases' => 1]]],
        ['certificate.failed', 'service', ['certificate_id' => 'crt_1', 'domains' => ['shop.cz'], 'error' => 'acme: rate limited by provider', 'renewal' => true], ['certificate_id' => 'crt_1', 'domains' => ['shop.cz'], 'renewal' => true]],
        ['cdn.disabled', 'service', ['domain' => 'shop.cz', 'nameservers_switched' => false, 'provider' => 'bunny'], ['domain' => 'shop.cz', 'nameservers_switched' => false]],
        ['backup.offsite', 'service', ['backup_id' => 'bkp_1', 'disk' => 's3-wasabi-eu'], ['backup_id' => 'bkp_1']],
        ['sla.credit.issued', 'service', ['amount' => $money, 'incident' => 'INC-7', 'percent' => 10, 'service_id' => 'srv_x', 'credit_note_id' => 'inv_cn'], ['amount' => $money, 'percent' => 10, 'service_id' => 'srv_x', 'credit_note_id' => 'inv_cn']],
        ['order.approval.rejected', 'order', ['number' => 'O-3', 'reason' => 'Ask Petr from finance first', 'decider' => 'Jana', 'requester_id' => 'usr_internal'], ['number' => 'O-3']],
        ['service.staff_panel_login', 'service', ['service' => 'eshop', 'ticket_number' => 'T-9', 'ticket_id' => 'tic_internal', 'staff_name' => 'Karel Admin', 'reason' => 'checking the php-fpm pool on pve-node-3', 'consented' => true, 'at' => '2026-10-04T10:00:00+02:00'], ['ticket_number' => 'T-9', 'consented' => true, 'at' => '2026-10-04T10:00:00+02:00']],
        ['monitoring.down', 'service', ['monitor_id' => 'mon_2', 'incident_id' => 'minc_2', 'url' => 'https://shop.cz/', 'error' => 'cURL error 7: Failed to connect to 10.0.0.5', 'notify' => true], ['monitor_id' => 'mon_2', 'url' => 'https://shop.cz/']],
        ['deploy.started', 'service', ['deployment_id' => 'dep_2', 'ref' => 'Bearer abcdefghijklmnop', 'trigger' => 'push'], ['deployment_id' => 'dep_2', 'ref' => 'Bearer [redacted]', 'trigger' => 'push']], // a secret pasted into a field is masked like everywhere else
        ['app.deployed', 'service', ['deployment_id' => 'adp_1', 'digest' => 'sha256:abc'], ['deployment_id' => 'adp_1', 'digest' => 'sha256:abc']],
    ];
    foreach ($cases as [$event, $type, $raw, $public]) {
        d4Relay(GenericEvent::of($event, $type, $type.'_d4', $raw, $org->id));
    }

    foreach ($cases as [$event, $type, , $public]) {
        $delivery = WebhookDelivery::query()->where('event', $event)->sole();
        expect($delivery->payload)->toBe(['aggregate' => ['type' => $type, 'id' => $type.'_d4'], 'organization_id' => $org->id, 'payload' => $public], "{$event} carries other fields");
    }
    $wire = implode("\n", array_map(fn ($pair) => $pair[0]->body(), d4Sent()));
    expect(d4Sent())->toHaveCount(count($cases));
    foreach (['operation_id', 'requester_id', 'score', 'reasons', 'from_node', 'to_node', 'registrar', 'connection_id', 'correlation_id', 'excerpt', '"error"', '"provider"', '"disk"', 'pve-node', 'WEDOS', 'wasabi', 'bunny', '10.0.0.5', 'jana@shop.cz', 'Web nodes slow', 'Investigating', 'Prověřujeme', 'Kernel', 'Petr', 'Karel', 'abcdefghijklmnop'] as $internal) {
        expect($wire)->not->toContain($internal);
    }
});

it('never sends the staff side of an event, reconciliation or anything not in the catalogue', function () {
    [$user, $org] = $this->customerWithOrganization();
    d4SignIn($this, $user);
    d4Endpoint($this); // `*`
    Http::fake();
    foreach (['order.settlement_failed', 'order.fulfilment_failed', 'sla.burn_rate', 'domain.reconcile.missing_remote', 'domain.registry_notice', 'service.purge.leftover', 'service.suspend.incomplete', 'dns.drift.detected', 'ticket.assigned', 'ticket.escalated', 'maintenance.unapproved', 'node.drained'] as $event) {
        d4Relay(GenericEvent::of($event, 'x', 'x_1', ['node' => 'pve-node-3', 'error' => 'boom'], $org->id));
    }

    expect(WebhookDelivery::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('creates endpoints through the bus: a fresh step-up, only events of the catalogue, and the audit keeps the host but not a chat token', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(WebhookEndpoint::query()->count())->toBe(0);

    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost', 'events' => ['node.drained']])->assertStatus(422)->assertJsonPath('error', 'webhook_event_unknown')->assertJsonPath('unknown', ['node.drained']);
    $this->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost', 'events' => ['platform.*']])->assertStatus(422)->assertJsonPath('error', 'webhook_event_unknown');
    $created = $this->postJson('/v1/webhooks', ['url' => 'https://discord.com/api/webhooks/123/abcDEF_token', 'events' => ['service.*', 'invoice.issued', 'service.*']])->assertCreated()->json('data');
    expect($created['events'])->toBe(['service.*', 'invoice.issued'])->and($created['secret'])->toStartWith('whsec_');

    $audit = AuditEvent::query()->where('action', 'webhook.create')->where('result', 'succeeded')->sole();
    expect(json_encode($audit->detail))->toContain('discord.com')->not->toContain('abcDEF_token')->not->toContain($created['secret']);
    $list = $this->getJson('/v1/webhooks')->assertOk()->assertJsonMissingPath('data.0.secret');
    expect($list->json('events'))->toContain('service.activated')->not->toContain('order.settlement_failed')
        ->and($list->json('signature.headers'))->toBe(['X-ONhost-Timestamp', 'X-ONhost-Signature']);
});

it('rotates the secret after a step-up; every attempt from then on is signed with the new one', function () {
    [$user, $org] = $this->customerWithOrganization();
    d4SignIn($this, $user);
    $hook = d4Endpoint($this);
    Http::fake(['hooks.example.cz/*' => Http::response('', 200)]);

    $rotated = $this->postJson("/v1/webhooks/{$hook['id']}/rotate-secret")->assertOk()->json('data');
    expect($rotated['secret'])->toStartWith('whsec_')->not->toBe($hook['secret']);
    $this->postJson("/v1/webhooks/{$hook['id']}/ping")->assertStatus(202)->assertJsonPath('data.event', 'webhook.ping');

    [$request] = d4Sent()[0];
    $timestamp = $request->header('X-ONhost-Timestamp')[0];
    expect($request->header('X-ONhost-Signature')[0])->toBe('v1='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $rotated['secret']))
        ->and($request->header('X-ONhost-Event')[0])->toBe('webhook.ping')
        ->and(json_decode($request->body(), true)['data']['payload'])->toBe(['endpoint_id' => $hook['id']]);
    expect(AuditEvent::query()->where('action', 'webhook.rotate_secret')->where('result', 'succeeded')->sole()->step_up_method)->toBe('totp');
});

it('sends a delivery again with the same id and body, once per request — a retried request and a delivery already queued add nothing', function () {
    [$user, $org] = $this->customerWithOrganization();
    d4SignIn($this, $user);
    $hook = d4Endpoint($this);
    Http::fake(['hooks.example.cz/*' => Http::response('', 200)]);
    d4Relay(GenericEvent::of('domain.renewed', 'domain', 'dom_1', ['fqdn' => 'shop.cz', 'expires_at' => '2027-10-04', 'years' => 1], $org->id));
    $delivery = WebhookDelivery::query()->sole();
    expect(d4Sent())->toHaveCount(1);

    $url = "/v1/webhooks/{$hook['id']}/deliveries/{$delivery->id}/redeliver";
    $this->withHeader('Idempotency-Key', 'redeliver-1')->postJson($url)->assertStatus(202);
    $this->withHeader('Idempotency-Key', 'redeliver-1')->postJson($url)->assertStatus(202); // the same request retried
    expect(d4Sent())->toHaveCount(2);
    [$first] = d4Sent()[0];
    [$again] = d4Sent()[1];
    expect($again->header('X-ONhost-Delivery')[0])->toBe($delivery->id)->and($again->body())->toBe($first->body())
        ->and(WebhookDelivery::query()->count())->toBe(1)->and($delivery->fresh()->state)->toBe('delivered');

    // a delivery that is already waiting for its attempt is not queued a second time
    Queue::fake();
    $this->withHeader('Idempotency-Key', 'redeliver-2')->postJson($url)->assertStatus(202)->assertJsonPath('data.state', 'pending');
    $this->withHeader('Idempotency-Key', 'redeliver-3')->postJson($url)->assertStatus(202)->assertJsonPath('data.state', 'pending');
    Queue::assertPushed(DeliverWebhook::class, 1);
});

it('suspends an endpoint that keeps failing and tells the customer; turning it on again starts from a clean count', function () {
    [$user, $org] = $this->customerWithOrganization();
    d4SignIn($this, $user);
    $hook = d4Endpoint($this, ['service.*'], 'https://hooks.example.cz/in/secret-path-token');
    WebhookEndpoint::query()->whereKey($hook['id'])->update(['failures' => 19]);
    Http::fake(['hooks.example.cz/*' => Http::sequence()->push('down', 503)->push('', 200)]);

    d4Relay(GenericEvent::of('service.created', 'service', 'srv_1', ['product_key' => 'vps', 'family' => 'vps'], $org->id));
    app(OutboxPublisher::class)->relayPending(); // the suspension event itself

    $endpoint = WebhookEndpoint::query()->findOrFail($hook['id']);
    expect($endpoint->state)->toBe('suspended')->and($endpoint->failures)->toBe(20);
    $event = OutboxMessage::query()->where('name', 'webhook.endpoint.suspended')->sole();
    expect($event->organization_id)->toBe($org->id)->and($event->payload)->toBe(['host' => 'hooks.example.cz', 'failures' => 20, 'last_status' => 503]);
    expect(Notification::query()->where('organization_id', $org->id)->where('audience', 'customer')->where('title', 'like', 'Webhook pozastaven%')->count())->toBe(1)
        ->and(MailOutbox::query()->where('template_key', 'webhook-suspended')->where('organization_id', $org->id)->count())->toBe(1);
    $this->getJson('/v1/webhooks')->assertJsonPath('data.0.state', 'suspended');

    // nothing more is sent to it, and a test event is refused until it is turned on again
    d4Relay(GenericEvent::of('service.created', 'service', 'srv_2', ['product_key' => 'vps', 'family' => 'vps'], $org->id));
    expect(WebhookDelivery::query()->count())->toBe(1);
    $this->postJson("/v1/webhooks/{$hook['id']}/ping")->assertStatus(409)->assertJsonPath('error', 'webhook_not_active');

    $this->postJson("/v1/webhooks/{$hook['id']}/enable")->assertOk()->assertJsonPath('data.state', 'active')->assertJsonPath('data.failures', 0);
    $this->postJson("/v1/webhooks/{$hook['id']}/ping")->assertStatus(202);
    expect(WebhookDelivery::query()->where('event', 'webhook.ping')->sole()->state)->toBe('delivered');
    // a removed endpoint stays removed
    $this->deleteJson("/v1/webhooks/{$hook['id']}")->assertOk()->assertJsonPath('data.state', 'disabled');
    // (another key: within the same minute, the same request without one is the first one retried and gets its answer)
    $this->withHeader('Idempotency-Key', 'enable-again')->postJson("/v1/webhooks/{$hook['id']}/enable")->assertStatus(409)->assertJsonPath('error', 'webhook_disabled');
});

it('keeps another organization away from an endpoint and its deliveries', function () {
    [$owner, $org] = $this->customerWithOrganization();
    d4SignIn($this, $owner);
    $hook = d4Endpoint($this);
    Http::fake(['hooks.example.cz/*' => Http::response('', 200)]);
    d4Relay(GenericEvent::of('domain.renewed', 'domain', 'dom_1', ['fqdn' => 'shop.cz', 'expires_at' => '2027-10-04', 'years' => 1], $org->id));
    $delivery = WebhookDelivery::query()->sole();

    [$stranger] = $this->customerWithOrganization(['email' => 'stranger@other.cz']);
    d4SignIn($this, $stranger);
    $this->getJson("/v1/webhooks/{$hook['id']}/deliveries")->assertNotFound();
    $this->postJson("/v1/webhooks/{$hook['id']}/ping")->assertNotFound();
    $this->postJson("/v1/webhooks/{$hook['id']}/rotate-secret")->assertNotFound();
    $this->postJson("/v1/webhooks/{$hook['id']}/deliveries/{$delivery->id}/redeliver")->assertNotFound();
    $this->deleteJson("/v1/webhooks/{$hook['id']}")->assertNotFound();
    expect(d4Sent())->toHaveCount(1)->and(WebhookEndpoint::query()->findOrFail($hook['id'])->state)->toBe('active');
});

it('checks the destination at every attempt: a name that now points into the management network gets nothing', function () {
    [$user, $org] = $this->customerWithOrganization();
    d4SignIn($this, $user);
    d4Endpoint($this, ['*'], 'https://hooks.rebind-example.cz/in');
    Http::fake();
    FakeHostResolver::$hosts['hooks.rebind-example.cz'] = ['10.0.0.5'];

    d4Relay(GenericEvent::of('service.created', 'service', 'srv_1', ['product_key' => 'vps', 'family' => 'vps'], $org->id));

    $delivery = WebhookDelivery::query()->sole();
    expect($delivery->state)->toBe('failed')->and((string) $delivery->last_error)->toContain('cannot be used');
    Http::assertNothingSent();
});
