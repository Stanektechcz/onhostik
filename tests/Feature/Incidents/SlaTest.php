<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\SlaCredit;
use Onhost\Domain\Incidents\Models\SlaMeasurement;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/** Three external probe locations for one component; returns [probe => token]. */
function slaProbes(string $component = 'portal'): array
{
    $out = [];
    foreach (['probe-cz-external', 'probe-sk-external', 'probe-eu-external'] as $location) {
        $token = 'prb_'.$location;
        $probe = SlaProbe::query()->create(['key' => "{$component}-https-{$location}", 'component_key' => $component, 'kind' => 'http', 'target' => 'https://portal.onhost.cz/healthz', 'location' => $location, 'expected' => ['status' => 200], 'interval_seconds' => 60, 'token_hash' => hash('sha256', $token), 'state' => 'active']);
        $out[$probe->id] = $token;
    }

    return $out;
}

function businessCloudService(Organization $org): Service
{
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS Business', 'hostname' => 'vps1.onhost.cloud', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'business', 'activated_at' => now()->subMonths(2)]);
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 100000, 'state' => 'active', 'current_period_start' => now()->startOfMonth(), 'current_period_end' => now()->startOfMonth()->addMonth(), 'next_renewal_at' => now()->startOfMonth()->addMonth()->subDays(7)]);

    return $service;
}

it('accepts probe results with the probe token; a 2-of-3 quorum failure auto-opens an incident and recovery moves it to monitoring', function () {
    $probes = slaProbes('portal');
    $at = now()->toIso8601String();
    $this->postJson('/v1/probes/results', ['results' => [['at' => $at, 'ok' => true]]])->assertUnauthorized();

    foreach ($probes as $token) {
        $this->withToken($token)->postJson('/v1/probes/results', ['results' => [['at' => $at, 'ok' => true, 'latency_ms' => 120]]])->assertStatus(202)->assertJsonPath('data.down', false);
    }
    expect(Incident::query()->count())->toBe(0);

    $tokens = array_values($probes);
    $later = now()->addMinute()->toIso8601String();
    $this->withToken($tokens[0])->postJson('/v1/probes/results', ['results' => [['at' => $later, 'ok' => false, 'detail' => 'HTTP 503']]])->assertStatus(202)->assertJsonPath('data.down', false); // one location alone is not an outage
    $this->withToken($tokens[1])->postJson('/v1/probes/results', ['results' => [['at' => $later, 'ok' => false, 'detail' => 'timeout']]])->assertStatus(202)->assertJsonPath('data.down', true);
    // duplicates (same probe + timestamp) are ignored
    $this->withToken($tokens[1])->postJson('/v1/probes/results', ['results' => [['at' => $later, 'ok' => false]]])->assertStatus(202)->assertJsonPath('data.duplicates', 1);

    $incident = Incident::query()->where('source', 'probes')->firstOrFail();
    expect($incident->severity)->toBe('p2')->and($incident->components)->toBe(['portal'])->and($incident->state)->toBe('DETECTED')->and(StatusComponent::query()->find('portal')->state)->toBe('partial_outage');
    expect(Incident::query()->count())->toBe(1);

    $this->travelTo(now()->addMinutes(2));
    $recovered = now()->toIso8601String();
    foreach ($tokens as $token) {
        $this->withToken($token)->postJson('/v1/probes/results', ['results' => [['at' => $recovered, 'ok' => true]]])->assertStatus(202);
    }
    expect($incident->fresh()->state)->toBe('MONITORING');
    expect(app(SlaService::class)->settleAutoIncidents(now()->addMinutes(20)))->toBe(1);
    expect($incident->fresh()->state)->toBe('RESOLVED')->and(StatusComponent::query()->find('portal')->state)->toBe('operational');
});

it('computes SLO windows with error budget and burn-rate alerts, excluding SLA-excluded maintenance', function () {
    $probes = slaProbes('portal');
    $component = StatusComponent::query()->find('portal');
    $now = now()->startOfMinute();
    foreach (array_keys($probes) as $i => $probeId) {
        $probe = SlaProbe::query()->find($probeId);
        for ($m = 59; $m >= 0; $m--) {
            $failing = $m < 5 && $i < 2; // last five minutes: two of three locations fail
            SlaMeasurement::query()->create(['probe_id' => $probe->id, 'component_key' => 'portal', 'location' => $probe->location, 'measured_at' => $now->copy()->subMinutes($m), 'ok' => ! $failing, 'latency_ms' => 100]);
        }
    }

    $windows = app(SlaService::class)->computeWindows($component, $now);
    expect($windows['1h']->total)->toBe(60)->and($windows['1h']->good)->toBe(55)->and(round($windows['1h']->availability_pct, 2))->toBe(91.67)
        ->and($windows['5m']->availability_pct)->toBe(0.0)->and($windows['30d']->policy_state)->toBe('freeze');
    expect(OutboxMessage::query()->where('name', 'sla.burn_rate')->exists())->toBeTrue()->and(OutboxMessage::query()->where('name', 'sla.budget.exhausted')->exists())->toBeTrue();

    // the same five minutes inside an approved, SLA-excluded maintenance window do not count
    Maintenance::query()->create(['number' => 'MNT-2026-0001', 'title' => 'Deploy', 'components' => ['portal'], 'starts_at' => $now->copy()->subMinutes(6), 'ends_at' => $now->copy()->addMinute(), 'rollback' => 'x', 'sla_treatment' => 'excluded', 'state' => 'completed']);
    $windows = app(SlaService::class)->computeWindows($component, $now);
    expect($windows['1h']->total)->toBe(53)->and((float) $windows['1h']->availability_pct)->toBe(100.0)->and($windows['30d']->policy_state)->toBe('normal');

    $this->actingAs($this->staff('sre'), 'sanctum');
    $report = $this->getJson('/v1/staff/reports/slo')->assertOk();
    $portal = collect($report->json('data.components'))->firstWhere('component', 'portal');
    expect((float) $portal['objective'])->toBe(99.95)->and($portal['probe_locations'])->toHaveCount(3)->and((float) $portal['windows']['1h']['availability'])->toBe(100.0);
});

it('computes SLA credits from the versioned policy and issues them as a credit note plus non-refundable wallet credit after step-up', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [$customer, $org] = $this->customerWithOrganization();
    $service = businessCloudService($org);
    $sre = $this->staff('sre');
    $this->actingAs($sre, 'sanctum');

    $opened = $this->postJson('/v1/staff/incidents', ['title' => 'Výpadek cloud CZ1', 'severity' => 'p1', 'components' => ['cloud-cz1'], 'affected_services' => [$service->id], 'started_at' => now()->subHours(10)->toIso8601String()])->assertCreated();
    $id = $opened->json('id');
    $this->postJson("/v1/staff/incidents/{$id}/sla-credits")->assertStatus(409); // not resolved yet
    $this->postJson("/v1/staff/incidents/{$id}/resolve", ['note' => 'Obnoveno'])->assertOk();

    $candidates = $this->postJson("/v1/staff/incidents/{$id}/sla-credits")->assertOk();
    expect($candidates->json())->toHaveCount(1);
    $credit = $candidates->json('0');
    // 10 h downtime in the month → ~98.6 % availability → business band "< 99.0 → 25 %" of the 1 000 CZK monthly price
    expect($credit['credit_percent'])->toBe(25)->and($credit['amount']['minor'])->toBe(25000)->and($credit['state'])->toBe('candidate')->and($credit['availability_pct'])->toBeLessThan(99.0)->and($credit['calculation']['policy'])->toBe('sla-business@v1');
    expect($this->postJson("/v1/staff/incidents/{$id}/sla-credits")->json())->toHaveCount(1); // idempotent

    $this->postJson("/v1/staff/sla-credits/{$credit['id']}/issue")->assertForbidden()->assertJsonPath('error', 'step_up_required'); // issuing money needs a fresh step-up
    app(StepUpService::class)->grant($sre, 'totp', null, '127.0.0.1');
    $this->postJson("/v1/staff/sla-credits/{$credit['id']}/issue")->assertStatus(409); // must be approved first
    $this->postJson("/v1/staff/sla-credits/{$credit['id']}/approve")->assertOk()->assertJsonPath('state', 'approved');
    $issued = $this->postJson("/v1/staff/sla-credits/{$credit['id']}/issue")->assertOk()->assertJsonPath('state', 'issued');

    $note = Invoice::query()->findOrFail($issued->json('credit_note_id'));
    expect($note->type)->toBe('credit_note')->and($note->state)->toBe(Invoice::ISSUED)->and($note->total_minor)->toBe(-30250)->and($note->number)->toStartWith('DK');
    $balances = app(WalletService::class)->balances($org, 'CZK');
    expect($balances['promo']->minor)->toBe(25000)->and($balances['available']->minor)->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'sla-credit')->where('to', $customer->email)->exists())->toBeTrue();

    $this->actingAs($customer, 'sanctum');
    $mine = $this->withHeader('X-Organization', $org->id)->getJson('/v1/sla-credits')->assertOk()->assertHeader('X-Total-Count', '1');
    expect($mine->json('data.0.incident'))->toBe($opened->json('number'))->and($mine->json('data.0.calculation'))->toBeNull();
    expect(SlaCredit::query()->count())->toBe(1);
});
