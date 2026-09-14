<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/*
 * On-call escalation (audit §5q-1): an operational event pages once, a repeat does not page again, nobody acknowledging
 * within the window re-pages with a higher severity (at most N times), an acknowledgement from the console or the
 * pager's webhook stops it, the recovery event resolves the alert on both sides.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config()->set('onhost.oncall.provider', 'pagerduty');
    config()->set('onhost.oncall.secret_ref', 'env://ONHOST_ONCALL');
    config()->set('onhost.oncall.inbound_secret', 'pd-webhook-secret');
    config()->set('onhost.oncall.escalate_after_minutes', 15);
    config()->set('onhost.oncall.max_escalations', 2);
    app(SecretStore::class)->write(SecretRef::parse('env://ONHOST_ONCALL'), ['routing_key' => 'R0UT1NGKEY']);
    Http::fake(['https://events.pagerduty.com/v2/enqueue' => Http::response(['status' => 'success', 'dedup_key' => 'x'], 202)]);
});

function oncallPages(string $action): array
{
    $rows = [];
    Http::assertSent(function (Request $r) use (&$rows, $action) {
        if ($r->url() === 'https://events.pagerduty.com/v2/enqueue' && ($r['event_action'] ?? '') === $action) {
            $rows[] = $r->data();
        }

        return true;
    });

    return $rows;
}

it('pages once per subject, escalates unacknowledged alerts and resolves on recovery or acknowledgement', function () {
    featureGameService($this->customerWithOrganization()[1]); // the lab game panel instance
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $outbox = app(OutboxPublisher::class);

    // the integration goes down: the router writes the internal notice, the on-call service pages with the same title
    $outbox->publish(GenericEvent::of('integration.down', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'error' => 'timeout']));
    $outbox->relayPending();
    $alert = OnCallAlert::query()->firstOrFail();
    expect($alert->state)->toBe('open')->and($alert->severity)->toBe('hot')->and($alert->provider)->toBe('pagerduty')->and($alert->provider_ref)->toBe($alert->dedup_key)->and($alert->title)->toBe('Integrace nedostupná: pterodactyl-games01')->and($alert->escalate_after)->not->toBeNull();
    $pages = oncallPages('trigger');
    expect($pages)->toHaveCount(1)->and($pages[0]['routing_key'])->toBe('R0UT1NGKEY')->and($pages[0]['dedup_key'])->toBe($alert->dedup_key)->and($pages[0]['payload']['severity'])->toBe('critical')->and($pages[0]['payload']['summary'])->toBe('Integrace nedostupná: pterodactyl-games01');

    // the same event again while the alert is open: counted, not paged
    $outbox->publish(GenericEvent::of('integration.down', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'error' => 'timeout']));
    $outbox->relayPending();
    expect(OnCallAlert::query()->count())->toBe(1)->and(data_get($alert->refresh()->meta, 'repeats'))->toBe(1)->and(oncallPages('trigger'))->toHaveCount(1);

    // nothing within the window: nothing happens; past it the minute pass re-pages with the escalation number
    $this->travel(10)->minutes();
    expect(app(OnCallService::class)->escalateDue())->toBe(['escalated' => 0, 'exhausted' => 0]);
    $this->travel(6)->minutes();
    Artisan::call('onhost:oncall:escalate');
    $alert->refresh();
    expect($alert->state)->toBe('escalated')->and($alert->escalations)->toBe(1)->and($alert->escalate_after)->not->toBeNull();
    $pages = oncallPages('trigger');
    expect($pages)->toHaveCount(2)->and($pages[1]['payload']['summary'])->toStartWith('[eskalace 1] ');
    $outbox->relayPending();
    $note = Notification::query()->where('event', 'oncall.alert.escalated')->firstOrFail();
    expect($note->title)->toBe('Eskalace on-call 1: Integrace nedostupná: pterodactyl-games01')->and($note->severity)->toBe('hot')->and($note->body)->toContain('pager pagerduty');
    expect(app(AutomationLedger::class)->last(OnCallService::RULE)['stats'])->toMatchArray(['escalated' => 1]);

    // the second escalation is the last one: the deadline is cleared and the console keeps it hot
    $this->travel(16)->minutes();
    expect(app(OnCallService::class)->escalateDue())->toBe(['escalated' => 0, 'exhausted' => 1]);
    expect($alert->refresh()->escalations)->toBe(2)->and($alert->escalate_after)->toBeNull();
    $this->travel(30)->minutes();
    expect(app(OnCallService::class)->escalateDue())->toBe(['escalated' => 0, 'exhausted' => 0]);

    // the on-call acknowledges from the console: the pager hears it, the deadline is gone
    $this->actingAs($this->staff('sre'), 'sanctum');
    $list = $this->getJson('/v1/staff/oncall/alerts')->assertOk()->json();
    expect($list['data'][0]['id'])->toBe($alert->id)->and($list['data'][0]['escalations'])->toBe(2)->and($list['status'])->toMatchArray(['provider' => 'pagerduty', 'configured' => true, 'active' => 1, 'escalate_after_minutes' => 15]);
    $this->withHeader('Idempotency-Key', 'onc-1')->postJson("/v1/staff/oncall/alerts/{$alert->id}/ack")->assertOk()->assertJsonPath('state', 'acked');
    expect(oncallPages('acknowledge'))->toHaveCount(1)->and($alert->refresh()->acked_by)->not->toBeNull();
    $this->withHeader('Idempotency-Key', 'onc-2')->postJson("/v1/staff/oncall/alerts/{$alert->id}/ack")->assertOk()->assertJsonPath('state', 'acked'); // idempotent

    // the recovery event resolves it on both sides
    $outbox->publish(GenericEvent::of('integration.recovered', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider]));
    $outbox->relayPending();
    $outbox->relayPending(); // the resolution event was published while relaying
    expect($alert->refresh()->state)->toBe('resolved')->and($alert->resolved_by)->toBe('event:integration.recovered')->and(oncallPages('resolve'))->toHaveCount(1);
    expect(Notification::query()->where('event', 'oncall.alert.resolved')->exists())->toBeTrue();
    $this->withHeader('Idempotency-Key', 'onc-3')->postJson("/v1/staff/oncall/alerts/{$alert->id}/ack")->assertStatus(409)->assertJsonPath('error', 'oncall_alert_resolved');

    // a new outage after the resolution opens a new alert and pages again
    $outbox->publish(GenericEvent::of('integration.down', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'error' => 'again']));
    $outbox->relayPending();
    expect(OnCallAlert::query()->count())->toBe(2)->and(oncallPages('trigger'))->toHaveCount(4); // open, two escalations, the new outage
});

it('takes the acknowledgement from the pager webhook only when it is signed, and resolves from the console', function () {
    featureGameService($this->customerWithOrganization()[1]); // the lab game panel instance
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $outbox = app(OutboxPublisher::class);
    $outbox->publish(GenericEvent::of('platform.queue.stalled', 'queue', 'default', ['last_seen_at' => now()->subMinutes(9)->toIso8601String()]));
    $outbox->relayPending();
    $alert = OnCallAlert::query()->firstOrFail();

    // PagerDuty v3: the body signature decides
    $body = json_encode(['event' => ['event_type' => 'incident.acknowledged', 'agent' => ['summary' => 'Petr SRE'], 'data' => ['incident_key' => $alert->dedup_key]]]);
    $this->call('POST', '/v1/webhooks/oncall/pagerduty', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAGERDUTY_SIGNATURE' => 'v1=deadbeef'], $body)->assertStatus(401);
    $this->call('POST', '/v1/webhooks/oncall/pagerduty', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);
    $signature = 'v1='.hash_hmac('sha256', $body, 'pd-webhook-secret');
    $this->call('POST', '/v1/webhooks/oncall/pagerduty', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAGERDUTY_SIGNATURE' => 'v1=other,'.$signature], $body)->assertStatus(202)->assertJsonPath('data.handled', true)->assertJsonPath('data.action', 'ack');
    expect($alert->refresh()->state)->toBe('acked')->and($alert->acked_by)->toBe('provider:pagerduty:Petr SRE');
    $this->call('POST', '/v1/webhooks/oncall/pagerduty', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAGERDUTY_SIGNATURE' => $signature], $body)->assertStatus(202)->assertJsonPath('data.handled', true); // a repeat is harmless
    $unknown = json_encode(['event' => ['event_type' => 'incident.acknowledged', 'data' => ['incident_key' => 'nope']]]);
    $this->call('POST', '/v1/webhooks/oncall/pagerduty', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAGERDUTY_SIGNATURE' => 'v1='.hash_hmac('sha256', $unknown, 'pd-webhook-secret')], $unknown)->assertStatus(202)->assertJsonPath('data.handled', false);

    // Opsgenie: the inbound token header; close resolves
    $this->postJson('/v1/webhooks/oncall/opsgenie', ['action' => 'Close', 'alert' => ['alias' => $alert->dedup_key, 'username' => 'jana']])->assertStatus(401);
    $this->withHeader('X-ONhost-Oncall-Token', 'pd-webhook-secret')->postJson('/v1/webhooks/oncall/opsgenie', ['action' => 'Close', 'alert' => ['alias' => $alert->dedup_key, 'username' => 'jana']])->assertStatus(202)->assertJsonPath('data.action', 'resolve');
    expect($alert->refresh()->state)->toBe('resolved')->and($alert->resolved_by)->toBe('provider:opsgenie:jana');

    // console-only mode (no provider): alerts still open and escalate without any HTTP call; the test page opens a synthetic alert
    config()->set('onhost.oncall.provider', '');
    $service = app(OnCallService::class);
    $outbox->publish(GenericEvent::of('node.bmc.alert', 'node', 'node_x', ['node' => 'cz1-game01', 'problems' => ['psu 2 failed']]));
    $outbox->relayPending();
    $bmc = OnCallAlert::query()->where('event', 'node.bmc.alert')->firstOrFail();
    expect($bmc->provider)->toBeNull()->and($bmc->provider_ref)->toBeNull()->and($service->status()['configured'])->toBeFalse();
    $this->actingAs($this->staff('sre'), 'sanctum');
    $this->withHeader('Idempotency-Key', 'onc-t')->postJson('/v1/staff/oncall/test')->assertCreated()->assertJsonPath('event', 'oncall.test')->assertJsonPath('paged', false);
    $this->withHeader('Idempotency-Key', 'onc-r')->postJson("/v1/staff/oncall/alerts/{$bmc->id}/resolve", ['note' => 'PSU vyměněn'])->assertOk()->assertJsonPath('state', 'resolved');
    $this->getJson('/v1/staff/oncall/alerts?state=resolved')->assertOk()->assertJsonCount(2, 'data');

    // the rule off: the minute pass does nothing
    app(AutomationLedger::class)->setEnabled(OnCallService::RULE, false, 'test');
    $outbox->publish(GenericEvent::of('capacity.forecast.low', 'capacity', 'game:cz1', ['role' => 'game', 'region' => 'cz1', 'days_left' => 5]));
    $outbox->relayPending();
    $this->travel(20)->minutes();
    expect($service->escalateDue())->toBe(['escalated' => 0, 'exhausted' => 0])->and(OnCallAlert::query()->where('event', 'capacity.forecast.low')->firstOrFail()->state)->toBe('open');
    $this->actingAs($this->customerWithOrganization()[0], 'sanctum');
    $this->getJson('/v1/staff/oncall/alerts')->assertForbidden();
});
