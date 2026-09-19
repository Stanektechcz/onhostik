<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\LoadShedding;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * Overviews go first (Brain card H139). When operations pile up behind their due time, reports, analytics and
 * cross-service overviews are refused — plainly, with Retry-After, never served stale — so that actions, restores,
 * access management and payments keep their share of the platform. Staff can force it on or off during an incident.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    config(['onhost.provisioning.backlog.threshold' => 3, 'onhost.provisioning.backlog.age_minutes' => 5]);
});

it('sheds reports while operations pile up and gives them back when the backlog is gone, without touching the critical paths', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $finance = $this->staff('platform_owner');
    $load = app(LoadShedding::class);

    // a normal day: everything answers
    $this->actingAs($finance, 'sanctum');
    $this->getJson('/v1/staff/reports/mrr')->assertOk();
    $this->artisan('onhost:integrations:health')->assertExitCode(0);
    expect($load->active())->toBeFalse()->and(OutboxMessage::query()->where('name', 'like', 'platform.load_shedding.%')->count())->toBe(0);

    // four operations are overdue for ten minutes: the scheduled pass sees it, requests only read its verdict
    foreach (range(1, 4) as $i) {
        Operation::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'kind' => 'service.action', 'workflow' => 'x', 'state' => Operation::PENDING, 'idempotency_key' => "h139-{$i}", 'queue' => 'provider-aapanel', 'queued_at' => now()->subMinutes(12), 'next_run_at' => now()->subMinutes(10), 'desired' => []]);
    }
    $this->artisan('onhost:integrations:health')->assertExitCode(0);
    expect($load->active())->toBeTrue()->and($load->state()['signal'])->toMatchArray(['overloaded' => true, 'stale' => 4, 'threshold' => 3])
        ->and(OutboxMessage::query()->where('name', 'platform.load_shedding.started')->count())->toBe(1);

    // what can wait is refused, plainly
    foreach (['/v1/staff/reports/mrr', '/v1/staff/reports/revenue', '/v1/staff/reports/churn', '/v1/staff/reports/collections', '/v1/staff/chargebacks/analytics'] as $report) {
        $refused = $this->getJson($report)->assertStatus(503)->assertJsonPath('error', 'load_shedding')->assertJsonPath('degraded', true);
        expect($refused->headers->get('Retry-After'))->toBe('120')->and($refused->headers->get('Cache-Control'))->toContain('no-store');
    }
    // what cannot wait is untouched: the operations board staff need right now, the queue of chargeback decisions
    $this->getJson('/v1/staff/provisioning/load')->assertOk()->assertJsonPath('data.active', true)->assertJsonPath('data.mode', 'auto');
    $this->getJson('/v1/staff/chargebacks')->assertOk();

    // the customer: the cross-service overview waits, the service itself, its backups and its actions do not
    Operation::query()->where('service_id', $service->id)->update(['service_id' => null]); // the pile belongs to others; this site is free to act
    $this->actingAs($owner, 'sanctum');
    $this->getJson('/v1/monitors')->assertStatus(503)->assertJsonPath('error', 'load_shedding');
    $this->getJson("/v1/services/{$service->id}")->assertOk();
    $this->getJson("/v1/services/{$service->id}/backups")->assertOk();
    Queue::fake();
    $this->withHeader('Idempotency-Key', 'h139-act')->postJson("/v1/services/{$service->id}/actions", ['action' => 'backup'])->assertStatus(202);

    // the pile is worked off: reports come back by themselves, and the second pass says nothing twice
    Operation::query()->where('idempotency_key', 'like', 'h139-_')->update(['state' => Operation::SUCCEEDED, 'finished_at' => now()]);
    $this->artisan('onhost:integrations:health')->assertExitCode(0);
    $this->artisan('onhost:integrations:health')->assertExitCode(0);
    expect($load->active())->toBeFalse()->and(OutboxMessage::query()->where('name', 'platform.load_shedding.ended')->count())->toBe(1);
    $this->actingAs($finance, 'sanctum');
    $this->getJson('/v1/staff/reports/mrr')->assertOk();
});

it('lets incident staff force shedding on or off with a reason, and hand it back to the measurement', function () {
    $sre = $this->staff('sre');
    $this->actingAs($sre, 'sanctum');
    app(StepUpService::class)->grant($sre, 'totp', null, '127.0.0.1');
    $finance = $this->staff('billing_finance_admin');

    $this->putJson('/v1/staff/provisioning/load', ['mode' => 'on'])->assertUnprocessable(); // an override says why
    $this->withHeader('Idempotency-Key', 'load-on')->putJson('/v1/staff/provisioning/load', ['mode' => 'on', 'reason' => 'databáze pod tlakem, migrace dat'])->assertOk()->assertJsonPath('active', true)->assertJsonPath('mode', 'on');
    $this->actingAs($finance, 'sanctum');
    $this->getJson('/v1/staff/reports/mrr')->assertStatus(503);
    $this->putJson('/v1/staff/provisioning/load', ['mode' => 'off', 'reason' => 'chci reporty hned'])->assertForbidden(); // finance does not hold the incident switch

    // forced off: even a measured overload does not shed (staff decided the reports are needed now)
    $this->actingAs($sre, 'sanctum');
    $this->withHeader('Idempotency-Key', 'load-off')->putJson('/v1/staff/provisioning/load', ['mode' => 'off', 'reason' => 'uzávěrka, reporty mají přednost'])->assertOk()->assertJsonPath('active', false);
    app(LoadShedding::class)->observe(['alert' => true, 'stale' => 99, 'threshold' => 3, 'age_minutes' => 5]);
    expect(app(LoadShedding::class)->active())->toBeFalse();

    // back to the measurement, which still says overloaded
    $this->withHeader('Idempotency-Key', 'load-auto')->putJson('/v1/staff/provisioning/load', ['mode' => 'auto'])->assertOk()->assertJsonPath('mode', 'auto')->assertJsonPath('active', true);

    // a verdict nobody refreshes expires: a stopped scheduler must not keep the reports dark
    $this->travel(16)->minutes();
    expect(app(LoadShedding::class)->active())->toBeFalse();
});
